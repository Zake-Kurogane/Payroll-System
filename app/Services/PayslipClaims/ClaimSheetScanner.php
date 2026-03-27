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

        // Respect EXIF orientation when available (common for phone photos).
        $img = $this->applyExifOrientation($path, $img);

        // Some uploads are rotated (e.g., page photographed sideways). Try scan variants and
        // pick the best one to avoid mapping ink from "Area" text into the signature column.
        $scan = $this->scanBestOrientation($img, $expectedRows);
        if (!empty($scan)) {
            return $scan;
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
        // "Black" threshold is used to estimate ink coverage. Use a stricter threshold to avoid
        // treating scan shadows / paper texture as signatures.
        $blackThresholdSoft = 180;
        $blackThresholdStrict = 140;

        // 1) Find horizontal table lines (dense black pixels across width).
        $rowDens = [];
        for ($y = 0; $y < $h; $y += 1) {
            $black = 0;
            $total = 0;
            for ($x = 0; $x < $w; $x += $sampleStep) {
                $c = imagecolorat($img, $x, $y);
                $v = $c & 0xFF;
                $total++;
                if ($v < $blackThresholdSoft) $black++;
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

        $rowLines = $this->pickRowLinesWindowWithHeaderInk(
            $img,
            $hLines,
            $expectedRows + 2,
            $sigLeft,
            $rightBorder,
            $blackThresholdStrict
        );
        $tableTop = ($rowLines[0] ?? $hLines[0]);
        $tableBottom = ($rowLines[count($rowLines) - 1] ?? $hLines[count($hLines) - 1]);

        // 2) Find vertical table lines within the table block.
        $colDens = [];
        for ($x = 0; $x < $w; $x += 1) {
            $black = 0;
            $total = 0;
            for ($y = $tableTop; $y <= $tableBottom; $y += $sampleStep) {
                $c = imagecolorat($img, $x, $y);
                $v = $c & 0xFF;
                $total++;
                if ($v < $blackThresholdSoft) $black++;
            }
            $colDens[$x] = $total > 0 ? ($black / $total) : 0.0;
        }

        $vLines = $this->pickDenseLines($colDens, 0.65, 6);
        if (count($vLines) < 2) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        $leftBorder = $vLines[0];
        $rightBorder = $vLines[count($vLines) - 1];
        // Prefer a signature-column boundary close to the expected template width (~20% of the TABLE).
        // Using the full image width can be very wrong when photos include large margins.
        $tableW = max(1, $rightBorder - $leftBorder);
        $expectedSigLeft = (int) round($rightBorder - ($tableW * 0.20));
        $sigLeft = null;
        $bestDist = PHP_INT_MAX;
        foreach ($vLines as $vx) {
            if ($vx >= $rightBorder) continue;
            $dist = abs($vx - $expectedSigLeft);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $sigLeft = $vx;
            }
        }
        if ($sigLeft === null) $sigLeft = $expectedSigLeft;
        if ($rightBorder <= $sigLeft) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        $results = [];
        // Padding to avoid counting printed borders as "ink" (dynamic per-row to prevent cropping signatures).
        $padX = 10;
        $minPadY = 2;
        $maxPadY = 8;

        // 3) Row boundaries: prefer actual table lines when we have enough of them (avoids row drift).
        if (!empty($rowLines) && count($rowLines) >= ($expectedRows + 2)) {
            for ($i = 0; $i < $expectedRows; $i++) {
                $rowTop = (int) ($rowLines[$i + 1] ?? 0);
                $rowBot = (int) ($rowLines[$i + 2] ?? 0);
                $bandH = max(1, $rowBot - $rowTop);
                $padY = (int) round(min($maxPadY, max($minPadY, $bandH * 0.08)));
                $y1 = $rowTop + $padY;
                $y2 = $rowBot - $padY;
                $x1 = $sigLeft + $padX;
                $x2 = $rightBorder - $padX;

                if ($y2 <= $y1 || $x2 <= $x1) {
                    $results[] = ['claimed' => false, 'ink_ratio' => 0.0];
                    continue;
                }

                $inkSoft = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdSoft);
                $inkStrict = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdStrict);
                $results[] = [
                    'claimed' => $inkStrict > 0.0025,
                    'ink_ratio' => $inkSoft,
                    'ink_ratio_strict' => $inkStrict,
                ];
            }

            return $results;
        }

        // Fallback: stable proportion-based rows.
        $headerBottom = $hLines[1] ?? $hLines[0];
        $dataTop = (int) $headerBottom;
        $dataBottom = (int) $tableBottom;
        if ($dataBottom <= $dataTop) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }
        $rowH = ($dataBottom - $dataTop) / max(1, $expectedRows);
        if ($rowH < 8) {
            return $this->fallbackScanByProportions($img, $expectedRows);
        }

        for ($i = 0; $i < $expectedRows; $i++) {
            $rowTop = (int) round($dataTop + ($i * $rowH));
            $rowBot = (int) round($dataTop + (($i + 1) * $rowH));
            $bandH = max(1, $rowBot - $rowTop);
            $padY = (int) round(min($maxPadY, max($minPadY, $bandH * 0.08)));
            $y1 = $rowTop + $padY;
            $y2 = $rowBot - $padY;
            $x1 = $sigLeft + $padX;
            $x2 = $rightBorder - $padX;

            if ($y2 <= $y1 || $x2 <= $x1) {
                $results[] = ['claimed' => false, 'ink_ratio' => 0.0];
                continue;
            }

            $inkSoft = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdSoft);
            $inkStrict = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdStrict);
            $results[] = [
                'claimed' => $inkStrict > 0.0025,
                'ink_ratio' => $inkSoft,
                'ink_ratio_strict' => $inkStrict,
            ];
        }

        return $results;
    }

    private function scanBestOrientation($img, int $expectedRows): array
    {
        // 0, 90 CW, 90 CCW, 180
        $variants = [
            ['angle' => 0, 'img' => $img],
        ];

        // Rotate copies; use white background for uncovered pixels.
        if (function_exists('imagerotate')) {
            $white = imagecolorallocate($img, 255, 255, 255);
            $variants[] = ['angle' => 90, 'img' => @imagerotate($img, -90, $white)];  // CW
            $variants[] = ['angle' => -90, 'img' => @imagerotate($img, 90, $white)];  // CCW
            $variants[] = ['angle' => 180, 'img' => @imagerotate($img, 180, $white)];
        }

        $best = null;
        foreach ($variants as $v) {
            if (!$v['img']) continue;
            $res = $this->scanImage($v['img'], $expectedRows);
            if (empty($res['results'])) continue;

            // Heuristic scoring:
            // - Prefer non-fallback geometry (more reliable)
            // - Prefer lower strict ink average (avoids "everything is ink" cases on blank signatures)
            $score = 0.0;
            $score += ($res['used_fallback'] ? 10.0 : 0.0);
            $score += (float) ($res['ink_strict_avg'] ?? 0.0) * 1000.0;

            if ($best === null || $score < $best['score']) {
                $best = ['score' => $score, 'results' => $res['results']];
            }
        }

        return $best['results'] ?? [];
    }

    private function scanImage($img, int $expectedRows): array
    {
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
        $blackThresholdSoft = 180;
        $blackThresholdStrict = 140;

        $usedFallback = false;

        // 1) Find horizontal table lines (dense black pixels across width).
        $rowDens = [];
        for ($y = 0; $y < $h; $y += 1) {
            $black = 0;
            $total = 0;
            for ($x = 0; $x < $w; $x += $sampleStep) {
                $c = imagecolorat($img, $x, $y);
                $v = $c & 0xFF;
                $total++;
                if ($v < $blackThresholdSoft) $black++;
            }
            $rowDens[$y] = $total > 0 ? ($black / $total) : 0.0;
        }

        $hLines = $this->pickDenseLines($rowDens, 0.65, 6);
        if (count($hLines) < 4) {
            $usedFallback = true;
            $results = $this->fallbackScanByProportions($img, $expectedRows);
            return $this->withInkStats($results, $usedFallback);
        }

        $minY = (int) round($h * 0.10);
        $maxY = (int) round($h * 0.95);
        $hLines = array_values(array_filter($hLines, fn ($y) => $y >= $minY && $y <= $maxY));
        if (count($hLines) < 4) {
            $usedFallback = true;
            $results = $this->fallbackScanByProportions($img, $expectedRows);
            return $this->withInkStats($results, $usedFallback);
        }

        $rowLines = $this->pickRowLinesWindowWithHeaderInk(
            $img,
            $hLines,
            $expectedRows + 2,
            $sigLeft,
            $rightBorder,
            $blackThresholdStrict
        );
        $tableTop = ($rowLines[0] ?? $hLines[0]);
        $tableBottom = ($rowLines[count($rowLines) - 1] ?? $hLines[count($hLines) - 1]);

        // 2) Find vertical table lines within the table block.
        $colDens = [];
        for ($x = 0; $x < $w; $x += 1) {
            $black = 0;
            $total = 0;
            for ($y = $tableTop; $y <= $tableBottom; $y += $sampleStep) {
                $c = imagecolorat($img, $x, $y);
                $v = $c & 0xFF;
                $total++;
                if ($v < $blackThresholdSoft) $black++;
            }
            $colDens[$x] = $total > 0 ? ($black / $total) : 0.0;
        }

        $vLines = $this->pickDenseLines($colDens, 0.65, 6);
        if (count($vLines) < 2) {
            $usedFallback = true;
            $results = $this->fallbackScanByProportions($img, $expectedRows);
            return $this->withInkStats($results, $usedFallback);
        }

        $leftBorder = $vLines[0];
        $rightBorder = $vLines[count($vLines) - 1];
        $tableW = max(1, $rightBorder - $leftBorder);
        $expectedSigLeft = (int) round($rightBorder - ($tableW * 0.20));
        $sigLeft = null;
        $bestDist = PHP_INT_MAX;
        foreach ($vLines as $vx) {
            if ($vx >= $rightBorder) continue;
            $dist = abs($vx - $expectedSigLeft);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $sigLeft = $vx;
            }
        }
        if ($sigLeft === null) $sigLeft = $expectedSigLeft;
        if ($rightBorder <= $sigLeft) {
            $usedFallback = true;
            $results = $this->fallbackScanByProportions($img, $expectedRows);
            return $this->withInkStats($results, $usedFallback);
        }

        $results = [];
        $padX = 10;
        $minPadY = 2;
        $maxPadY = 8;

        // 3) Prefer actual row lines when available (prevents off-by-one / drift).
        if (!empty($rowLines) && count($rowLines) >= ($expectedRows + 2)) {
            for ($i = 0; $i < $expectedRows; $i++) {
                $rowTop = (int) ($rowLines[$i + 1] ?? 0);
                $rowBot = (int) ($rowLines[$i + 2] ?? 0);
                $bandH = max(1, $rowBot - $rowTop);
                $padY = (int) round(min($maxPadY, max($minPadY, $bandH * 0.08)));
                $y1 = $rowTop + $padY;
                $y2 = $rowBot - $padY;
                $x1 = $sigLeft + $padX;
                $x2 = $rightBorder - $padX;

                if ($y2 <= $y1 || $x2 <= $x1) {
                    $results[] = ['claimed' => false, 'ink_ratio' => 0.0, 'ink_ratio_strict' => 0.0];
                    continue;
                }

                $inkSoft = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdSoft);
                $inkStrict = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdStrict);
                $results[] = [
                    'claimed' => $inkStrict > 0.0025,
                    'ink_ratio' => $inkSoft,
                    'ink_ratio_strict' => $inkStrict,
                ];
            }

            return $this->withInkStats($results, $usedFallback);
        }

        // Fallback: proportion-based rows.
        $headerBottom = $hLines[1] ?? $hLines[0];
        $dataTop = (int) $headerBottom;
        $dataBottom = (int) $tableBottom;
        if ($dataBottom <= $dataTop) {
            $usedFallback = true;
            $results = $this->fallbackScanByProportions($img, $expectedRows);
            return $this->withInkStats($results, $usedFallback);
        }
        $rowH = ($dataBottom - $dataTop) / max(1, $expectedRows);
        if ($rowH < 8) {
            $usedFallback = true;
            $results = $this->fallbackScanByProportions($img, $expectedRows);
            return $this->withInkStats($results, $usedFallback);
        }

        for ($i = 0; $i < $expectedRows; $i++) {
            $rowTop = (int) round($dataTop + ($i * $rowH));
            $rowBot = (int) round($dataTop + (($i + 1) * $rowH));
            $bandH = max(1, $rowBot - $rowTop);
            $padY = (int) round(min($maxPadY, max($minPadY, $bandH * 0.08)));
            $y1 = $rowTop + $padY;
            $y2 = $rowBot - $padY;
            $x1 = $sigLeft + $padX;
            $x2 = $rightBorder - $padX;

            if ($y2 <= $y1 || $x2 <= $x1) {
                $results[] = ['claimed' => false, 'ink_ratio' => 0.0, 'ink_ratio_strict' => 0.0];
                continue;
            }

            $inkSoft = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdSoft);
            $inkStrict = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdStrict);
            $results[] = [
                'claimed' => $inkStrict > 0.0025,
                'ink_ratio' => $inkSoft,
                'ink_ratio_strict' => $inkStrict,
            ];
        }

        return $this->withInkStats($results, $usedFallback);
    }

    private function pickRowLinesWindowWithHeaderInk($img, array $lines, int $need, int $sigLeft, int $rightBorder, int $blackThresholdStrict): array
    {
        $lines = array_values(array_unique(array_map('intval', $lines)));
        sort($lines);
        $n = count($lines);
        if ($n < $need) return [];

        $best = null;
        for ($s = 0; $s <= ($n - $need); $s++) {
            $win = array_slice($lines, $s, $need);
            $diffs = [];
            for ($i = 1; $i < count($win); $i++) {
                $diffs[] = $win[$i] - $win[$i - 1];
            }
            if (count($diffs) < 2) continue;

            // Ignore the header height (first gap). Focus on the data-row gaps.
            $dataDiffs = array_slice($diffs, 1);
            $mean = array_sum($dataDiffs) / max(1, count($dataDiffs));
            if ($mean < 8) continue;

            $var = 0.0;
            foreach ($dataDiffs as $d) {
                $var += ($d - $mean) * ($d - $mean);
            }
            $std = sqrt($var / max(1, count($dataDiffs)));

            // Extra anchoring: prefer windows where the header cell contains printed ink ("Signature"),
            // which strongly indicates `win[0]..win[1]` is the header band (prevents off-by-one shifts).
            $padX = 12;
            $padY = 4;
            $x1 = $sigLeft + $padX;
            $x2 = $rightBorder - $padX;
            $y1 = (int) $win[0] + $padY;
            $y2 = (int) $win[1] - $padY;
            $headerInk = 0.0;
            if ($y2 > $y1 && $x2 > $x1) {
                $headerInk = $this->inkRatio($img, $x1, $y1, $x2, $y2, $blackThresholdStrict);
            }

            // Lower is better. Reward higher header ink.
            $score = ($std * 10.0) - ($headerInk * 1000.0);

            if ($best === null || $score < $best['score']) {
                $best = ['score' => $score, 'win' => $win];
            }
        }

        return $best['win'] ?? [];
    }

    private function withInkStats(array $results, bool $usedFallback): array
    {
        $strict = array_map(fn ($r) => (float) ($r['ink_ratio_strict'] ?? 0), $results);
        $avg = !empty($strict) ? (array_sum($strict) / max(1, count($strict))) : 0.0;
        return [
            'results' => $results,
            'used_fallback' => $usedFallback,
            'ink_strict_avg' => $avg,
        ];
    }

    private function applyExifOrientation(string $path, $img)
    {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) return $img;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg'], true)) return $img;

        try {
            $exif = @exif_read_data($path);
            $ori = (int) ($exif['Orientation'] ?? 1);
            if ($ori === 3) return @imagerotate($img, 180, 0xFFFFFF) ?: $img;
            if ($ori === 6) return @imagerotate($img, -90, 0xFFFFFF) ?: $img; // 90 CW
            if ($ori === 8) return @imagerotate($img, 90, 0xFFFFFF) ?: $img;  // 90 CCW
        } catch (\Throwable $e) {
            return $img;
        }

        return $img;
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

        // Approximate the signature column and table region (template has ~20% signature column).
        $x1 = (int) round($w * 0.78);
        $x2 = (int) round($w * 0.97);
        $tableTop = (int) round($h * 0.22);
        $tableBottom = (int) round($h * 0.92);
        $rowH = (int) max(10, floor(($tableBottom - $tableTop) / max(1, $expectedRows)));

        $results = [];
        for ($i = 0; $i < $expectedRows; $i++) {
            // Extra padding to avoid counting printed borders.
            $y1 = $tableTop + ($i * $rowH) + 8;
            $y2 = $tableTop + (($i + 1) * $rowH) - 8;
            $inkSoft = $this->inkRatio($img, $x1 + 12, $y1, $x2 - 12, $y2, 180);
            $inkStrict = $this->inkRatio($img, $x1 + 12, $y1, $x2 - 12, $y2, 140);
            $results[] = [
                'claimed' => $inkStrict > 0.0025,
                'ink_ratio' => $inkSoft,
                'ink_ratio_strict' => $inkStrict,
            ];
        }
        return $results;
    }
}
