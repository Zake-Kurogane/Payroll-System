<?php

namespace App\Services\PayslipClaims;

class ClaimSheetScanner
{
    /** Geometry/debug info from the last scan, for storing in processed_summary. */
    private array $lastDebug = [];

    public function getLastDebug(): array
    {
        return $this->lastDebug;
    }

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
        $best = $this->scanBestOrientation($img, $expectedRows);
        if (!empty($best['results'])) {
            $this->lastDebug = $best['debug'] ?? [];
            return $best['results'];
        }

        $res = $this->scanImage($img, $expectedRows);
        $this->lastDebug = $res['debug'] ?? [];
        return (array) ($res['results'] ?? []);
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

            // Prefer non-fallback geometry. Tiebreak: first orientation tried (0°) wins
            // because we only replace on strict improvement (score <, not <=).
            // Do NOT use ink values — with only the received box checked (mostly blank),
            // a wrong orientation that samples blank areas scores lower and would win incorrectly.
            $score = ($res['used_fallback'] ? 1.0 : 0.0);

            if ($best === null || $score < $best['score']) {
                $best = ['score' => $score, 'results' => $res['results'], 'debug' => $res['debug'] ?? []];
            }
        }

        return $best ?? [];
    }

    private function scanImage($img, int $expectedRows): array
    {
        $w = imagesx($img);
        $h = imagesy($img);

        $targetW = 1200;
        if ($w > $targetW) {
            $scale = $targetW / $w;
            $newW  = (int) round($w * $scale);
            $newH  = (int) round($h * $scale);
            $img   = imagescale($img, $newW, $newH, IMG_BILINEAR_FIXED);
            $w     = imagesx($img);
            $h     = imagesy($img);
        }

        imagefilter($img, IMG_FILTER_GRAYSCALE);

        $step = 2;

        // ── 1. Detect all dense horizontal lines ──────────────────────────────
        // Threshold 0.50: solid table-border/row-separator lines (continuous ink
        // spanning full width) clearly exceed this; title-text rows do not.
        // Fall back to 0.30 for very faint borders (camera photos, low contrast).
        $rowDens = [];
        for ($y = 0; $y < $h; $y++) {
            $black = 0;
            $total = 0;
            for ($x = 0; $x < $w; $x += $step) {
                $total++;
                if ((imagecolorat($img, $x, $y) & 0xFF) < 180) $black++;
            }
            $rowDens[$y] = $total > 0 ? $black / $total : 0.0;
        }

        $hLines = $this->pickDenseLines($rowDens, 0.50, 6);
        $hLines = array_values(array_filter($hLines, fn ($y) => $y > $h * 0.04 && $y < $h * 0.97));
        if (count($hLines) < 2) {
            // Camera photos / faint scans: lower threshold
            $hLines = $this->pickDenseLines($rowDens, 0.30, 6);
            $hLines = array_values(array_filter($hLines, fn ($y) => $y > $h * 0.04 && $y < $h * 0.97));
        }
        if (count($hLines) < 2) {
            return $this->withInkStats($this->fallbackScanByProportions($img, $expectedRows), true);
        }

        // First detected line = table top border; last = table bottom border.
        // This works regardless of how many rows the page has, because the outer
        // borders are always the first and last dense horizontal structures.
        $tableTop    = $hLines[0];
        $tableBottom = $hLines[count($hLines) - 1];

        if ($tableBottom - $tableTop < (int) ($h * 0.05)) {
            return $this->withInkStats($this->fallbackScanByProportions($img, $expectedRows), true);
        }

        // ── 2. Detect outer vertical borders ─────────────────────────────────
        // Scan only within the table's vertical span to avoid picking up non-table
        // vertical structures above/below the table.
        $colDens = [];
        for ($x = 0; $x < $w; $x++) {
            $black = 0;
            $total = 0;
            for ($y = $tableTop; $y <= $tableBottom; $y += $step) {
                $total++;
                if ((imagecolorat($img, $x, $y) & 0xFF) < 180) $black++;
            }
            $colDens[$x] = $total > 0 ? $black / $total : 0.0;
        }

        $vLines = $this->pickDenseLines($colDens, 0.50, 6);
        if (count($vLines) < 2) {
            $vLines = $this->pickDenseLines($colDens, 0.30, 6);
        }

        // Left border = leftmost v-line in left 20 % of image (excludes all
        // internal column separators).
        // Right border = rightmost v-line in right 20 % of image.
        // This prevents the Signature/Received column separator (~92 % from left)
        // from being mistaken for the outer right border.
        $leftCandidates  = array_filter($vLines, fn ($x) => $x < $w * 0.20);
        $rightCandidates = array_filter($vLines, fn ($x) => $x > $w * 0.80);
        if (empty($leftCandidates) || empty($rightCandidates)) {
            return $this->withInkStats($this->fallbackScanByProportions($img, $expectedRows), true);
        }

        $tableLeft  = (int) min($leftCandidates);
        $tableRight = (int) max($rightCandidates);
        $tableW     = max(1, $tableRight - $tableLeft);
        $tableH     = max(1, $tableBottom - $tableTop);

        // ── 3. Find the header / data separator ──────────────────────────────
        // Each row (header included) is ~10 mm on the printed sheet, so the
        // header separator is within 0.35–1.8 × approxRowH below the table top.
        // Scan rowDens directly in that zone and pick the densest pixel.
        $approxRowH   = $tableH / max(1, $expectedRows + 1);
        $hZoneStart   = $tableTop + (int) ($approxRowH * 0.35);
        $hZoneEnd     = $tableTop + (int) ($approxRowH * 1.80);
        $headerBottom = null;
        $headerBestD  = 0.0;
        for ($y = $hZoneStart; $y < $hZoneEnd; $y++) {
            if (isset($rowDens[$y]) && $rowDens[$y] > $headerBestD) {
                $headerBestD  = $rowDens[$y];
                $headerBottom = $y;
            }
        }
        $headerBottom = $headerBottom ?? ($tableTop + (int) $approxRowH);
        $headerBottom = max(
            $tableTop + (int) ($approxRowH * 0.30),
            min($headerBottom, $tableBottom - (int) ($approxRowH * 0.50))
        );

        // ── 4. Column positions from the CSS proportions ──────────────────────
        // .col-rec 8 % (starts at 92 %)
        $padX  = 8;
        $recX1 = $tableLeft + (int) round($tableW * 0.925) + ($padX - 3);
        $recX2 = $tableRight - ($padX - 3);

        // Background reference: signature column (.col-sign 14%, starts at 78%).
        // Used for local-contrast check: genuine ink = received box much darker than
        // the background next to it; shadow gradient = both similarly dark.
        $bgX1ref = $tableLeft + (int) round($tableW * 0.78);
        $bgX2ref = $recX1 - 2;

        // ── 5. Equal-height row scanning ─────────────────────────────────────
        $dataH = max(1, $tableBottom - $headerBottom);
        $rowH  = $dataH / max(1, $expectedRows);
        $padY  = max(2, (int) ($rowH * 0.08));

        // Thresholds for received-box ink:
        // strict (<140): clearly dark pen/pencil marks — if ≥ 6% pixels, definitely claimed.
        // soft (<180):   JPEG-compressed gray ink — require local contrast to reject shadow.
        // vsoft (<200):  lightly shaded boxes — require stronger contrast signal.
        $recClaimThresholdStrict = 0.060;
        $recClaimThresholdSoft   = 0.080;
        $recClaimThresholdVSoft  = 0.100;
        // Minimum contrast (received − background) required for soft/vsoft claims.
        // Shadow gradients are gradual so adjacent columns have similar ink; genuine
        // ink is a localized dark spot on white, giving contrast > 0.15.
        $contrastMinSoft  = 0.15;
        $contrastMinVSoft = 0.12;

        $results      = [];
        $rowInkSoft   = [];
        $rowInkStrict = [];
        $rowBgInk     = [];
        for ($i = 0; $i < $expectedRows; $i++) {
            $y1 = (int) ($headerBottom + $i * $rowH) + $padY;
            $y2 = (int) ($headerBottom + ($i + 1) * $rowH) - $padY;

            if ($y2 <= $y1 || $recX2 <= $recX1 + 4) {
                $results[]      = ['claimed' => false, 'ink_ratio' => 0.0, 'ink_ratio_strict' => 0.0];
                $rowInkSoft[]   = 0.0;
                $rowInkStrict[] = 0.0;
                $rowBgInk[]     = 0.0;
                continue;
            }

            $recInkStrict = 0.0;
            $recInkSoft   = 0.0;
            $recInkVSoft  = 0.0;
            [$rx1, $ry1, $rx2, $ry2] = $this->receivedBoxInnerRect($recX1, $y1, $recX2, $y2);
            if ($rx2 > $rx1 && $ry2 > $ry1) {
                $recInkStrict = $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, 140);
                $recInkSoft   = $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, 180);
                $recInkVSoft  = $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, 200);
            }

            // Background ink in the signature column (immediately left of received box).
            $bgInkSoft  = 0.0;
            $bgInkVSoft = 0.0;
            if ($bgX2ref > $bgX1ref + 10 && $y2 > $y1) {
                $bgInkSoft  = $this->inkRatio($img, $bgX1ref, $y1, $bgX2ref, $y2, 180);
                $bgInkVSoft = $this->inkRatio($img, $bgX1ref, $y1, $bgX2ref, $y2, 200);
            }

            // Local contrast: how much darker is the received box vs its neighbour?
            $contrastSoft  = max(0.0, $recInkSoft  - $bgInkSoft);
            $contrastVSoft = max(0.0, $recInkVSoft - $bgInkVSoft);

            // Decision:
            // 1. Clear dark ink (< 140): always genuine regardless of background.
            // 2. Gray ink (< 180): accept only if box is clearly darker than background.
            // 3. Light shading (< 200): accept if box is still notably darker than background.
            $claimed = $recInkStrict > $recClaimThresholdStrict
                    || $recInkSoft  > $recClaimThresholdSoft  && $contrastSoft  > $contrastMinSoft
                    || $recInkVSoft > $recClaimThresholdVSoft && $contrastVSoft > $contrastMinVSoft;

            $results[] = [
                'claimed'          => $claimed,
                'ink_ratio'        => $recInkSoft,
                'ink_ratio_strict' => $recInkStrict,
            ];
            $rowInkSoft[]   = round($recInkSoft,  4);
            $rowInkStrict[] = round($recInkStrict, 4);
            $rowBgInk[]     = round($bgInkSoft,   4);
        }

        $debug = [
            'img_w'          => $w,
            'img_h'          => $h,
            'table_left'     => $tableLeft,
            'table_right'    => $tableRight,
            'table_top'      => $tableTop,
            'table_bottom'   => $tableBottom,
            'header_bottom'  => $headerBottom,
            'rec_x1'         => $recX1,
            'rec_x2'         => $recX2,
            'row_h'          => round($rowH, 1),
            'row_ink_soft'   => implode(',', $rowInkSoft),
            'row_ink_strict' => implode(',', $rowInkStrict),
            'row_bg_ink'     => implode(',', $rowBgInk),
        ];

        return $this->withInkStats($results, false, $debug);
    }

    private function withInkStats(array $results, bool $usedFallback, array $debug = []): array
    {
        $strict = array_map(fn ($r) => (float) ($r['ink_ratio_strict'] ?? 0), $results);
        $soft   = array_map(fn ($r) => (float) ($r['ink_ratio']        ?? 0), $results);
        $softMax   = !empty($soft)   ? max($soft)   : 0.0;
        $strictMax = !empty($strict) ? max($strict) : 0.0;
        return [
            'results'       => $results,
            'used_fallback' => $usedFallback,
            'ink_soft_max'  => $softMax,
            'ink_strict_max'=> $strictMax,
            'debug'         => $debug,
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

    private function receivedBoxInnerRect(int $cellX1, int $cellY1, int $cellX2, int $cellY2): array
    {
        $cellW = max(1, $cellX2 - $cellX1);
        $cellH = max(1, $cellY2 - $cellY1);

        // The printed box is centered and roughly square. Sample the inner area to avoid counting the border.
        $boxSize = (int) round(min($cellW, $cellH) * 0.78);
        $boxX1 = (int) round($cellX1 + (($cellW - $boxSize) / 2));
        $boxY1 = (int) round($cellY1 + (($cellH - $boxSize) / 2));
        $boxX2 = $boxX1 + $boxSize;
        $boxY2 = $boxY1 + $boxSize;

        $inset = (int) max(2, round($boxSize * 0.12));

        return [
            $boxX1 + $inset,
            $boxY1 + $inset,
            $boxX2 - $inset,
            $boxY2 - $inset,
        ];
    }

    private function fallbackScanByProportions($img, int $expectedRows): array
    {
        $w = imagesx($img);
        $h = imagesy($img);

        // Approximate the received column position (.col-rec starts at 92 %).
        $recCellX1 = (int) round($w * 0.92);
        $recCellX2 = (int) round($w * 0.97);
        $tableTop = (int) round($h * 0.22);
        $tableBottom = (int) round($h * 0.92);
        $rowH = (int) max(10, floor(($tableBottom - $tableTop) / max(1, $expectedRows)));

        $results = [];
        for ($i = 0; $i < $expectedRows; $i++) {
            // Extra padding to avoid counting printed borders.
            $y1 = $tableTop + ($i * $rowH) + 8;
            $y2 = $tableTop + (($i + 1) * $rowH) - 8;

            $recInkSoft = 0.0;
            $recInkStrict = 0.0;
            if (($recCellX2 - 8) > ($recCellX1 + 8) && $y2 > $y1) {
                [$rx1, $ry1, $rx2, $ry2] = $this->receivedBoxInnerRect($recCellX1 + 8, $y1, $recCellX2 - 8, $y2);
                if ($rx2 > $rx1 && $ry2 > $ry1) {
                    $recInkSoft = $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, 180);
                    $recInkStrict = $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, 140);
                }
            }

            $inkSoft = $recInkSoft;
            $inkStrict = $recInkStrict;
            $claimed = $recInkStrict > 0.02 || $recInkSoft > 0.06;
            $results[] = [
                'claimed' => $claimed,
                'ink_ratio' => $inkSoft,
                'ink_ratio_strict' => $inkStrict,
            ];
        }
        return $results;
    }
}
