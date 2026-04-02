<?php

namespace App\Services\PayslipClaims;

class ClaimSheetScanner
{
    private array $lastDebug = [];

    public function getLastDebug(): array
    {
        return $this->lastDebug;
    }

    /**
     * Scan one page of a claim sheet.
     *
     * @param string $path          Absolute path to the image file.
     * @param int    $expectedRows  Number of data rows on this page.
     * @param array  $rowTokens     12-char tokens ordered the same as the printed rows.
     *
     * @return array  Per-row results, each containing:
     *   status        — 'confirmed' | 'needs_review' | 'unclaimed'
     *   confidence    — 0.0–1.0
     *   qr_found      — bool
     *   token_found   — string|null   (what the QR decoded to)
     *   token_match   — bool          (decoded == expected for this row position)
     *   checkbox_ink  — float
     *   sig_ink       — float
     *   claimed       — bool          (backward compat)
     *   ink_ratio     — float         (backward compat)
     *   ink_ratio_strict — float      (backward compat)
     */
    public function scanImageFile(string $path, int $expectedRows, array $rowTokens = []): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) throw new \RuntimeException('Unable to read image.');

        $img = @imagecreatefromstring($raw);
        if (!$img) throw new \RuntimeException('Unsupported image format.');

        $img = $this->applyExifOrientation($path, $img);

        $result = $this->scanImage($img, $expectedRows, $rowTokens);
        $this->lastDebug = $result['debug'] ?? [];
        return $result['rows'];
    }

    // ── Core scan ─────────────────────────────────────────────────────────────

    private function scanImage($img, int $expectedRows, array $rowTokens): array
    {
        // ── 1. Scale to a comfortable working width ───────────────────────────
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > 1500) {
            $scale = 1500 / $w;
            $img   = imagescale($img, (int) round($w * $scale), (int) round($h * $scale), IMG_BILINEAR_FIXED);
            $w     = imagesx($img);
            $h     = imagesy($img);
        }

        // ── 2. Grayscale copy for density scanning ────────────────────────────
        $gray = imagecreatetruecolor($w, $h);
        imagecopy($gray, $img, 0, 0, 0, 0, $w, $h);
        imagefilter($gray, IMG_FILTER_GRAYSCALE);

        $step = 2;

        // ── 3. Detect horizontal table borders (full-width density) ───────────
        $rowDens = [];
        for ($y = 0; $y < $h; $y++) {
            $black = 0; $total = 0;
            for ($x = 0; $x < $w; $x += $step) {
                $total++;
                if ((imagecolorat($gray, $x, $y) & 0xFF) < 180) $black++;
            }
            $rowDens[$y] = $total > 0 ? $black / $total : 0.0;
        }

        $hLines = $this->pickDenseLines($rowDens, 0.50, 6);
        $hLines = array_values(array_filter($hLines, fn ($y) => $y > $h * 0.04 && $y < $h * 0.97));
        if (\count($hLines) < 2) {
            $hLines = $this->pickDenseLines($rowDens, 0.30, 6);
            $hLines = array_values(array_filter($hLines, fn ($y) => $y > $h * 0.04 && $y < $h * 0.97));
        }
        if (\count($hLines) < 2) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'no_hlines', $gray, $w, $h);
        }

        $tableTop    = $hLines[0];
        $tableBottom = $hLines[\count($hLines) - 1];
        if ($tableBottom - $tableTop < $h * 0.05) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'table_too_small', $gray, $w, $h);
        }

        // ── 4. Detect vertical table borders ─────────────────────────────────
        $colDens = [];
        for ($x = 0; $x < $w; $x++) {
            $black = 0; $total = 0;
            for ($y = $tableTop; $y <= $tableBottom; $y += $step) {
                $total++;
                if ((imagecolorat($gray, $x, $y) & 0xFF) < 180) $black++;
            }
            $colDens[$x] = $total > 0 ? $black / $total : 0.0;
        }

        $vLines = $this->pickDenseLines($colDens, 0.50, 6);
        if (\count($vLines) < 2) $vLines = $this->pickDenseLines($colDens, 0.30, 6);

        $leftCandidates  = array_filter($vLines, fn ($x) => $x < $w * 0.20);
        $rightCandidates = array_filter($vLines, fn ($x) => $x > $w * 0.80);
        if (empty($leftCandidates) || empty($rightCandidates)) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'no_vlines', $gray, $w, $h);
        }

        $tableLeft  = (int) min($leftCandidates);
        $tableRight = (int) max($rightCandidates);
        $tableW     = max(1, $tableRight - $tableLeft);
        $tableH     = max(1, $tableBottom - $tableTop);

        // ── 5. Find the header/data separator ────────────────────────────────
        $approxRowH   = $tableH / max(1, $expectedRows + 1);
        $headerBottom = $this->findHeaderSeparator($rowDens, $tableTop, $approxRowH, $tableBottom);

        // ── 6. Column positions from the new CSS layout ───────────────────────
        // Sheet columns (% of table width):
        //   #(5%) | EmpID(10%) | Name(26%) | Area(15%) | QR(12%) | Sig(22%) | □(7%) | Date(3%)
        // Cumulative left edges:
        //   QR  : 56 % → 68 %
        //   Sig : 68 % → 90 %
        //   Rec : 90 % → 97 %
        $qrX1  = $tableLeft + (int) round($tableW * 0.57);
        $qrX2  = $tableLeft + (int) round($tableW * 0.68);
        $sigX1 = $tableLeft + (int) round($tableW * 0.69);
        $sigX2 = $tableLeft + (int) round($tableW * 0.89);
        $recX1 = $tableLeft + (int) round($tableW * 0.91);
        $recX2 = $tableLeft + (int) round($tableW * 0.96);

        // ── 7. Row walker — find each separator independently ─────────────────
        // Recompute density restricted to the table's horizontal span so that
        // row separator lines (only ~70–80 % of the full image width) appear
        // at full strength instead of being diluted by the margins.
        $tableRowDens = [];
        for ($y = $tableTop; $y <= $tableBottom; $y++) {
            $black = 0; $total = 0;
            for ($x = $tableLeft; $x <= $tableRight; $x += $step) {
                $total++;
                if ((imagecolorat($gray, $x, $y) & 0xFF) < 180) $black++;
            }
            $tableRowDens[$y] = $total > 0 ? $black / $total : 0.0;
        }

        $approxDataH = ($tableBottom - $headerBottom) / max(1, $expectedRows);
        $boundaries  = [$headerBottom];
        for ($r = 1; $r < $expectedRows; $r++) {
            $expectedY   = (int) ($headerBottom + $r * $approxDataH);
            $searchRange = (int) ($approxDataH * 0.30);
            $searchStart = max($headerBottom + 1, $expectedY - $searchRange);
            $searchEnd   = min($tableBottom   - 1, $expectedY + $searchRange);
            $bestY = $expectedY;
            $bestD = -1.0;
            for ($sy = $searchStart; $sy <= $searchEnd; $sy++) {
                if (isset($tableRowDens[$sy]) && $tableRowDens[$sy] > $bestD) {
                    $bestD = $tableRowDens[$sy];
                    $bestY = $sy;
                }
            }
            $boundaries[] = $bestY;
        }
        $boundaries[] = $tableBottom;

        // ── 8. Per-row scan ───────────────────────────────────────────────────
        $rows         = [];
        $dbgRec       = [];
        $dbgSig       = [];
        $dbgQr        = [];
        $canReadQr    = !empty($rowTokens) && class_exists(\Zxing\QrReader::class);
        $sigPadX      = (int) max(2, round(($sigX2 - $sigX1) * 0.03));

        for ($i = 0; $i < $expectedRows; $i++) {
            $cellH   = $boundaries[$i + 1] - $boundaries[$i];
            $padY    = max(2, (int) ($cellH * 0.12));
            $y1      = $boundaries[$i]     + $padY;
            $y2      = $boundaries[$i + 1] - $padY;
            $expTok  = $rowTokens[$i] ?? null;

            if ($y2 <= $y1) {
                $rows[]  = $this->makeRow(false, 0.0, 0.0, null, $expTok, 'invalid_row');
                $dbgRec[] = 0; $dbgSig[] = 0; $dbgQr[] = 0;
                continue;
            }

            // Try to read QR from the QR column area of this row.
            $tokenFound = null;
            if ($canReadQr) {
                $tokenFound = $this->readQr($img, $qrX1, $y1, $qrX2, $y2);
            }

            // Checkbox ink: strict threshold < 140 (phone shadows stay > 160).
            ['strict' => $recInk] = $this->receivedInkRatios($gray, $recX1, $y1, $recX2, $y2);

            // Signature ink: inset to skip column border lines.
            $sigInk = $sigX2 > $sigX1 + 10
                ? $this->inkRatio($gray, $sigX1 + $sigPadX, $y1, $sigX2 - $sigPadX, $y2, 140)
                : 0.0;

            // Require meaningful ink — phone-photo shadows/texture stay well below these.
            // recInk: checkbox must be ≥15 % filled (genuine shade).
            // sigInk: signature must cover ≥5 % of the column (a real pen stroke).
            $hasInk = $recInk > 0.15 || $sigInk > 0.05;

            $rows[]   = $this->makeRow($hasInk, $recInk, $sigInk, $tokenFound, $expTok);
            $dbgRec[] = round($recInk,  4);
            $dbgSig[] = round($sigInk,  4);
            $dbgQr[]  = $tokenFound !== null ? 1 : 0;
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
            'qr_x1'          => $qrX1,
            'qr_x2'          => $qrX2,
            'row_h'          => round($approxDataH, 1),
            'row_ink_strict' => implode(',', $dbgRec),
            'row_sig_ink'    => implode(',', $dbgSig),
            'row_qr_found'   => implode(',', $dbgQr),
        ];

        return ['rows' => $rows, 'debug' => $debug];
    }

    // ── Row classification ────────────────────────────────────────────────────

    /**
     * Build a per-row result array with status + confidence.
     *
     * Decision table:
     *   QR match  + ink  → confirmed    (0.95) — definitive via QR + ink
     *   QR match  + none → unclaimed    (0.92) — definitively empty row
     *   QR mismatch      → needs_review (0.50) — row alignment problem; human must check
     *   No QR     + ink  → confirmed    (0.65) — ink-based; QR just unreadable in photo
     *   No QR     + none → unclaimed    (0.60) — nothing detected
     *
     * needs_review is reserved for genuine QR mismatches (wrong-row attribution).
     * Unreadable QR is normal for phone photos and falls back to ink detection.
     */
    private function makeRow(
        bool    $hasInk,
        float   $recInk,
        float   $sigInk,
        ?string $tokenFound,
        ?string $expectedToken,
        string  $forceReason = ''
    ): array {
        if ($forceReason !== '') {
            return [
                'status' => 'needs_review', 'confidence' => 0.10,
                'qr_found' => false, 'token_found' => null,
                'token_match' => false, 'token_expected' => $expectedToken,
                'checkbox_ink' => 0.0, 'sig_ink' => 0.0,
                'claimed' => false, 'ink_ratio' => 0.0, 'ink_ratio_strict' => 0.0,
            ];
        }

        $qrOk       = $tokenFound !== null;
        $tokenMatch = $qrOk && $expectedToken !== null && $tokenFound === $expectedToken;

        if ($tokenMatch && $hasInk) {
            [$status, $conf] = ['confirmed',    0.95];
        } elseif ($tokenMatch) {
            [$status, $conf] = ['unclaimed',    0.92];
        } elseif ($qrOk) {
            // QR was read but doesn't match the expected row — genuine misalignment.
            [$status, $conf] = ['needs_review', 0.50];
        } elseif ($hasInk) {
            // QR unreadable (normal for phone photos) — trust the ink detection.
            [$status, $conf] = ['confirmed',    0.65];
        } else {
            // No QR, no ink — unclaimed.
            [$status, $conf] = ['unclaimed',    0.60];
        }

        return [
            'status'           => $status,
            'confidence'       => $conf,
            'qr_found'         => $qrOk,
            'token_found'      => $tokenFound,
            'token_match'      => $tokenMatch,
            'token_expected'   => $expectedToken,
            'checkbox_ink'     => $recInk,
            'sig_ink'          => $sigInk,
            // Backward-compat fields used by the controller loop:
            'claimed'          => $status === 'confirmed',
            'ink_ratio'        => $recInk,
            'ink_ratio_strict' => $recInk,
        ];
    }

    // ── QR reading ────────────────────────────────────────────────────────────

    /**
     * Crop the QR column area of a row, upscale for better detection, decode.
     * Returns the decoded string or null on failure.
     */
    private function readQr($img, int $x1, int $y1, int $x2, int $y2): ?string
    {
        if ($x2 <= $x1 + 8 || $y2 <= $y1 + 8) return null;

        try {
            $cropW = $x2 - $x1;
            $cropH = $y2 - $y1;

            // Upscale so the smallest dimension is at least 120 px — gives the
            // QR decoder enough pixels to locate finder patterns reliably.
            $scale = max(1.0, 120.0 / min($cropW, $cropH));
            $destW = (int) round($cropW * $scale);
            $destH = (int) round($cropH * $scale);

            $crop  = imagecreatetruecolor($destW, $destH);
            imagefill($crop, 0, 0, imagecolorallocate($crop, 255, 255, 255));
            imagecopyresampled($crop, $img, 0, 0, $x1, $y1, $destW, $destH, $cropW, $cropH);

            ob_start();
            imagepng($crop);
            $png = ob_get_clean();
            imagedestroy($crop);

            $reader  = new \Zxing\QrReader((string) $png, \Zxing\QrReader::SOURCE_TYPE_BLOB);
            $decoded = $reader->text();

            return ($decoded && \is_string($decoded) && $decoded !== '') ? strtoupper(trim($decoded)) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Fallback ─────────────────────────────────────────────────────────────

    /**
     * Called when table geometry cannot be detected.
     * Falls back to strict-ink-only scan using proportional positions.
     */
    private function fallbackResult(int $expectedRows, array $rowTokens, string $reason, $gray, int $w, int $h): array
    {
        // Approximate positions from CSS layout (% of image width).
        $recX1 = (int) round($w * 0.91);
        $recX2 = (int) round($w * 0.96);
        $sigX1 = (int) round($w * 0.69);
        $sigX2 = (int) round($w * 0.89);

        $tableTop    = (int) round($h * 0.18);
        $tableBottom = (int) round($h * 0.94);
        $rowH        = ($tableBottom - $tableTop) / max(1, $expectedRows);
        $sigPadX     = (int) max(2, round(($sigX2 - $sigX1) * 0.03));

        $rows = [];
        for ($i = 0; $i < $expectedRows; $i++) {
            $y1 = $tableTop + (int) ($i * $rowH) + 6;
            $y2 = $tableTop + (int) (($i + 1) * $rowH) - 6;

            ['strict' => $recInk] = $this->receivedInkRatios($gray, $recX1, $y1, $recX2, $y2);
            $sigInk  = $y2 > $y1 ? $this->inkRatio($gray, $sigX1 + $sigPadX, $y1, $sigX2 - $sigPadX, $y2, 140) : 0.0;
            $hasInk  = $recInk > 0.15 || $sigInk > 0.05;

            // No QR available in fallback — everything with ink becomes needs_review.
            $rows[] = $this->makeRow($hasInk, $recInk, $sigInk, null, $rowTokens[$i] ?? null);
        }

        return [
            'rows'  => $rows,
            'debug' => ['fallback_reason' => $reason],
        ];
    }

    // ── Geometry helpers ──────────────────────────────────────────────────────

    private function findHeaderSeparator(array $rowDens, int $tableTop, float $approxRowH, int $tableBottom): int
    {
        $hZoneStart   = $tableTop + (int) ($approxRowH * 0.35);
        $hZoneEnd     = $tableTop + (int) ($approxRowH * 1.80);
        $headerBottom = null;
        $bestD        = 0.0;
        for ($y = $hZoneStart; $y < $hZoneEnd; $y++) {
            if (isset($rowDens[$y]) && $rowDens[$y] > $bestD) {
                $bestD        = $rowDens[$y];
                $headerBottom = $y;
            }
        }
        $headerBottom = $headerBottom ?? ($tableTop + (int) $approxRowH);
        return max(
            $tableTop + (int) ($approxRowH * 0.30),
            min($headerBottom, $tableBottom - (int) ($approxRowH * 0.50))
        );
    }

    private function applyExifOrientation(string $path, $img)
    {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) return $img;
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) return $img;
        try {
            $exif = @exif_read_data($path);
            $ori  = (int) ($exif['Orientation'] ?? 1);
            if ($ori === 3) return @imagerotate($img, 180,  0xFFFFFF) ?: $img;
            if ($ori === 6) return @imagerotate($img, -90,  0xFFFFFF) ?: $img;
            if ($ori === 8) return @imagerotate($img,  90,  0xFFFFFF) ?: $img;
        } catch (\Throwable) {}
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
        for ($i = 1, $n = \count($candidates); $i < $n; $i++) {
            if ($candidates[$i] - $candidates[$i - 1] <= $mergeGap) {
                $group[] = $candidates[$i];
            } else {
                $lines[] = (int) round(array_sum($group) / \count($group));
                $group   = [$candidates[$i]];
            }
        }
        $lines[] = (int) round(array_sum($group) / \count($group));
        return $lines;
    }

    private function inkRatio($img, int $x1, int $y1, int $x2, int $y2, int $threshold): float
    {
        $iw = imagesx($img); $ih = imagesy($img);
        $x1 = max(0, min($iw - 1, $x1)); $x2 = max(0, min($iw - 1, $x2));
        $y1 = max(0, min($ih - 1, $y1)); $y2 = max(0, min($ih - 1, $y2));
        $black = 0; $total = 0;
        for ($y = $y1; $y <= $y2; $y += 2) {
            for ($x = $x1; $x <= $x2; $x += 2) {
                $total++;
                if ((imagecolorat($img, $x, $y) & 0xFF) < $threshold) $black++;
            }
        }
        return $total > 0 ? $black / $total : 0.0;
    }

    private function receivedBoxInnerRect(int $x1, int $y1, int $x2, int $y2, float $ratio = 0.70): array
    {
        $cw   = max(1, $x2 - $x1);
        $ch   = max(1, $y2 - $y1);
        $size = (int) round(min($cw, $ch) * $ratio);
        $bx1  = (int) round($x1 + ($cw - $size) / 2);
        $by1  = (int) round($y1 + ($ch - $size) / 2);
        $in   = (int) max(1, round($size * 0.12));
        return [$bx1 + $in, $by1 + $in, $bx1 + $size - $in, $by1 + $size - $in];
    }

    private function receivedInkRatios($img, int $x1, int $y1, int $x2, int $y2): array
    {
        $best = 0.0;
        foreach ([0.30, 0.45, 0.60, 0.75] as $ratio) {
            [$rx1, $ry1, $rx2, $ry2] = $this->receivedBoxInnerRect($x1, $y1, $x2, $y2, $ratio);
            if ($rx2 - $rx1 < 4 || $ry2 - $ry1 < 4) continue;
            $best = max($best, $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, 140));
        }
        return ['strict' => $best, 'soft' => $best];
    }
}
