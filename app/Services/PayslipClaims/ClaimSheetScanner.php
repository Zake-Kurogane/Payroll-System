<?php

namespace App\Services\PayslipClaims;

class ClaimSheetScanner
{
    public function scanImageFile(string $path, int $expectedRows): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read image.');
        }

        $img = @imagecreatefromstring($raw);
        if (!$img) {
            throw new \RuntimeException('Unsupported image format.');
        }

        $w = imagesx($img);
        $h = imagesy($img);

        $targetW = 1200;
        if ($w > $targetW) {
            $scale = $targetW / $w;
            $newW = (int) round($w * $scale);
            $newH = (int) round($h * $scale);
            $img = imagescale($img, $newW, $newH, IMG_BILINEAR_FIXED);
            $w = imagesx($img);
            $h = imagesy($img);
        }

        imagefilter($img, IMG_FILTER_GRAYSCALE);

        $sampleStep = 2;
        $blackThreshold = 180;

        // 1) Find horizontal table lines (dense black pixels across width).
        $rowDens = [];
        for ($y = 0; $y < $h; $y += 1) {
            $black = 0;
            $total = 0;
            for ($x = 0; $x < $w; $x += $sampleStep) {
                $c = imagecolorat($img, $x, $y);
                $v = $c & 0xFF;
                $total++;
                if ($v < $blackThreshold) $black++;
            }
            $rowDens[$y] = $total > 0 ? ($black / $total) : 0.0;
        }

        $hLines = $this->pickDenseLines($rowDens, 0.65, 6);
        if (count($hLines) < 4) {
            // Fallback: assume template geometry (less reliable).
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        // Try to pick the table block: ignore very top lines, keep the largest cluster.
        $minY = (int) round($h * 0.10);
        $maxY = (int) round($h * 0.95);
        $hLines = array_values(array_filter($hLines, fn ($y) => $y >= $minY && $y <= $maxY));
        if (count($hLines) < 4) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        $tableTop = $hLines[0];
        $tableBottom = $hLines[count($hLines) - 1];

        // 2) Find vertical table lines within the table block.
        $colDens = [];
        for ($x = 0; $x < $w; $x += 1) {
            $black = 0;
            $total = 0;
            for ($y = $tableTop; $y <= $tableBottom; $y += $sampleStep) {
                $c = imagecolorat($img, $x, $y);
                $v = $c & 0xFF;
                $total++;
                if ($v < $blackThreshold) $black++;
            }
            $colDens[$x] = $total > 0 ? ($black / $total) : 0.0;
        }

        $vLines = $this->pickDenseLines($colDens, 0.65, 6);
        if (count($vLines) < 2) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        $rightBorder = $vLines[count($vLines) - 1];
        $sigLeft = $vLines[count($vLines) - 2];
        if ($rightBorder <= $sigLeft) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        // 3) Row boundaries: assume 1 header row, then data rows.
        $headerBottom = $hLines[1] ?? $hLines[0];
        $boundaries = array_values(array_filter($hLines, fn ($y) => $y >= $headerBottom && $y <= $tableBottom));
        if (count($boundaries) < 3) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        $results = [];
        $rowsFound = min($expectedRows, count($boundaries) - 1);
        $padX = 6;
        $padY = 4;

        for ($i = 0; $i < $rowsFound; $i++) {
            $y1 = $boundaries[$i] + $padY;
            $y2 = $boundaries[$i + 1] - $padY;
            $x1 = $sigLeft + $padX;
            $x2 = $rightBorder - $padX;

            if ($y2 <= $y1 || $x2 <= $x1) {
                $results[] = ['claimed' => false, 'ink_ratio' => 0.0];
                continue;
            }

            $ink = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThreshold);
            $results[] = [
                'claimed' => $ink > 0.012,
                'ink_ratio' => $ink,
            ];
        }

        return $results;
    }

    private function pickDenseLines(array $densities, float $threshold, int $mergeGap): array
    {
        $candidates = [];
        foreach ($densities as $idx => $d) {
            if ($d >= $threshold) $candidates[] = (int) $idx;
        }
        if (!$candidates) return [];

        sort($candidates);
        $lines = [];
        $group = [$candidates[0]];
        for ($i = 1; $i < count($candidates); $i++) {
            $cur = $candidates[$i];
            $prev = $candidates[$i - 1];
            if (($cur - $prev) <= $mergeGap) {
                $group[] = $cur;
            } else {
                $lines[] = (int) round(array_sum($group) / count($group));
                $group = [$cur];
            }
        }
        $lines[] = (int) round(array_sum($group) / count($group));
        return $lines;
    }

    private function inkRatio($img, int $x1, int $y1, int $x2, int $y2, int $blackThreshold): float
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $x1 = max(0, min($w - 1, $x1));
        $x2 = max(0, min($w - 1, $x2));
        $y1 = max(0, min($h - 1, $y1));
        $y2 = max(0, min($h - 1, $y2));

        $step = 2;
        $black = 0;
        $total = 0;
        for ($y = $y1; $y <= $y2; $y += $step) {
            for ($x = $x1; $x <= $x2; $x += $step) {
                $c = imagecolorat($img, $x, $y);
                $v = $c & 0xFF;
                $total++;
                if ($v < $blackThreshold) $black++;
            }
        }
        return $total > 0 ? ($black / $total) : 0.0;
    }

    private function fallbackScanByProportions($img, int $expectedRows): array
    {
        $w = imagesx($img);
        $h = imagesy($img);

        // Approximate the signature column and table region.
        $x1 = (int) round($w * 0.78);
        $x2 = (int) round($w * 0.97);
        $tableTop = (int) round($h * 0.22);
        $tableBottom = (int) round($h * 0.92);
        $rowH = (int) max(10, floor(($tableBottom - $tableTop) / max(1, $expectedRows)));

        $results = [];
        for ($i = 0; $i < $expectedRows; $i++) {
            $y1 = $tableTop + ($i * $rowH) + 4;
            $y2 = $tableTop + (($i + 1) * $rowH) - 4;
            $ink = $this->inkRatio($img, $x1, $y1, $x2, $y2, 180);
            $results[] = [
                'claimed' => $ink > 0.012,
                'ink_ratio' => $ink,
            ];
        }
        return $results;
    }
}
