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

    /**
     * Quickly probe the first few rows of a claim sheet image and return any decoded
     * QR tokens. Used for page identification before the full scan — allows matching
     * the uploaded image to the correct employee slice even when the filename page
     * number doesn't align with the all-employee page index (e.g. after filtered download).
     *
     * @param  string $path      Absolute path to the image file.
     * @param  int    $maxRows   How many rows to probe (default 6 covers most small pages).
     * @return string[]          Decoded token strings (already uppercased/trimmed).
     */
    public function probeQrTokens(string $path, int $maxRows = 6): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) return [];

        $img = @imagecreatefromstring($raw);
        if (!$img) return [];

        $img = $this->applyExifOrientation($path, $img);

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > 1500) {
            $scale = 1500 / $w;
            $img   = imagescale($img, (int) round($w * $scale), (int) round($h * $scale));
            $w     = imagesx($img);
            $h     = imagesy($img);
        }

        // Fixed QR column X range matching CSS layout (52–64%).
        $qrX1     = (int) round($w * 0.52);
        $qrX2     = (int) round($w * 0.64);
        // Use CSS row height (17mm / 297mm) — valid for ≤15 rows on A4 portrait.
        $rowH     = max(30, (int) round($h * 17.0 / 297.0));
        $tableTop = (int) round($h * 0.17) + $rowH; // skip table header row

        $tokens = [];
        for ($i = 0; $i < $maxRows; $i++) {
            $qrY1 = $tableTop + $i * $rowH + 2;
            $qrY2 = $tableTop + ($i + 1) * $rowH - 2;
            if ($qrY2 > $h - 2) break;
            $decoded = $this->readQr($img, $qrX1, $qrY1, $qrX2, $qrY2);
            if ($decoded !== null) {
                $tokens[] = $decoded;
            }
        }

        imagedestroy($img);
        return $tokens;
    }

    // ── Core scan ─────────────────────────────────────────────────────────────

    private function scanImage($img, int $expectedRows, array $rowTokens): array
    {
        // 1) Scale to a comfortable working width.
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > 1500) {
            $scale = 1500 / $w;
            $img   = imagescale($img, (int) round($w * $scale), (int) round($h * $scale));
            $w     = imagesx($img);
            $h     = imagesy($img);
        }

        // 2) Grayscale copy for density scanning.
        $gray = imagecreatetruecolor($w, $h);
        imagecopy($gray, $img, 0, 0, 0, 0, $w, $h);
        imagefilter($gray, IMG_FILTER_GRAYSCALE);

        $step = 2;

        // 3) Detect horizontal table borders (full-width density).
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
        if (count($hLines) < 2) {
            $hLines = $this->pickDenseLines($rowDens, 0.30, 6);
            $hLines = array_values(array_filter($hLines, fn ($y) => $y > $h * 0.04 && $y < $h * 0.97));
        }
        if (count($hLines) < 2) {
            $hLines = $this->pickDenseLines($rowDens, 0.15, 6);
            $hLines = array_values(array_filter($hLines, fn ($y) => $y > $h * 0.04 && $y < $h * 0.97));
        }
        if (count($hLines) < 2) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'no_hlines', $img, $gray, $w, $h);
        }

        $tableTop    = $hLines[0];
        $tableBottom = $hLines[count($hLines) - 1];
        if ($tableBottom - $tableTop < $h * 0.05) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'table_too_small', $img, $gray, $w, $h);
        }

        // 4) Detect vertical table borders.
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
        if (count($vLines) < 2) $vLines = $this->pickDenseLines($colDens, 0.30, 6);

        $leftCandidates  = array_filter($vLines, fn ($x) => $x < $w * 0.20);
        $rightCandidates = array_filter($vLines, fn ($x) => $x > $w * 0.80);
        if (empty($leftCandidates) || empty($rightCandidates)) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'no_vlines', $img, $gray, $w, $h);
        }

        $tableLeft  = (int) min($leftCandidates);
        $tableRight = (int) max($rightCandidates);
        $tableW     = max(1, $tableRight - $tableLeft);
        $tableH     = max(1, $tableBottom - $tableTop);

        // If detected geometry is implausible for our generated claim-sheet layout,
        // use fixed geometry fallback (still with QR reading) to avoid false negatives.
        if ($tableTop > (int) round($h * 0.55) || $tableTop < (int) round($h * 0.08) || $tableH < (int) round($h * 0.25)) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'table_misaligned', $img, $gray, $w, $h);
        }

        // 5) Find header/data separator.
        $approxRowH   = $tableH / max(1, $expectedRows + 1);
        $headerBottom = $this->findHeaderSeparator($rowDens, $tableTop, $approxRowH, $tableBottom);

        // 6) Row boundaries.
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
            $searchEnd   = min($tableBottom - 1, $expectedY + $searchRange);
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

        // 7) Per-row scan: auto-try both layouts (with/without # column).
        $layouts = [
            ['name' => 'no_no_col',          'qr1' => 0.52,  'qr2' => 0.64,  'sig1' => 0.64,  'sig2' => 0.93,  'rec1' => 0.94,  'rec2' => 0.99],
            ['name' => 'no_no_col_left',     'qr1' => 0.505, 'qr2' => 0.625, 'sig1' => 0.625, 'sig2' => 0.915, 'rec1' => 0.925, 'rec2' => 0.985],
            ['name' => 'no_no_col_right',    'qr1' => 0.535, 'qr2' => 0.655, 'sig1' => 0.655, 'sig2' => 0.945, 'rec1' => 0.955, 'rec2' => 0.995],
            ['name' => 'with_no_col',        'qr1' => 0.57,  'qr2' => 0.69,  'sig1' => 0.69,  'sig2' => 0.93,  'rec1' => 0.94,  'rec2' => 0.99],
            ['name' => 'with_no_col_left',   'qr1' => 0.555, 'qr2' => 0.675, 'sig1' => 0.675, 'sig2' => 0.915, 'rec1' => 0.925, 'rec2' => 0.985],
            ['name' => 'with_no_col_right',  'qr1' => 0.585, 'qr2' => 0.705, 'sig1' => 0.705, 'sig2' => 0.945, 'rec1' => 0.955, 'rec2' => 0.995],
        ];

        $canReadQr = !empty($rowTokens) && class_exists(\Zxing\QrReader::class);
        $bestRows = [];
        $bestScore = -INF;
        $bestCutoff = 0.15;
        $bestDbgRec = [];
        $bestDbgSig = [];
        $bestDbgQr = [];
        $bestLayout = 'no_no_col';
        $bestRecX1 = 0;
        $bestRecX2 = 0;
        $bestQrX1 = 0;
        $bestQrX2 = 0;
        $bestQrFoundCount = 0;

        foreach ($layouts as $layout) {
            $qrX1  = $tableLeft + (int) round($tableW * $layout['qr1']);
            $qrX2  = $tableLeft + (int) round($tableW * $layout['qr2']);
            $sigX1 = $tableLeft + (int) round($tableW * $layout['sig1']);
            $sigX2 = $tableLeft + (int) round($tableW * $layout['sig2']);
            $recX1 = $tableLeft + (int) round($tableW * $layout['rec1']);
            $recX2 = $tableLeft + (int) round($tableW * $layout['rec2']);
            $sigPadX = (int) max(2, round(($sigX2 - $sigX1) * 0.03));

            $rows = [];
            $rowMetrics = [];
            $dbgRec = [];
            $dbgSig = [];
            $dbgQr = [];
            $qrCount = 0;
            $tokenMatchCount = 0;
            $recSum = 0.0;
            $recMax = 0.0;

            for ($i = 0; $i < $expectedRows; $i++) {
                $cellH   = $boundaries[$i + 1] - $boundaries[$i];
                $padY    = max(2, (int) ($cellH * 0.12));
                $y1      = $boundaries[$i] + $padY;
                $y2      = $boundaries[$i + 1] - $padY;
                $expTok  = $rowTokens[$i] ?? null;

                if ($y2 <= $y1) {
                    $rowMetrics[] = [
                        'force' => 'invalid_row',
                        'recInk' => 0.0,
                        'sigInk' => 0.0,
                        'tokenFound' => null,
                        'expectedToken' => $expTok,
                    ];
                    $dbgRec[] = 0;
                    $dbgSig[] = 0;
                    $dbgQr[] = 0;
                    continue;
                }

                $tokenFound = null;
                if ($canReadQr) {
                    // Use the full cell bounds (just 2px border margin) for QR reading.
                    // The 12% content-padding used for ink ratios clips the QR image —
                    // the printed QR is ~14mm tall in a ~17mm row, so it gets cut off.
                    $qrY1 = $boundaries[$i] + 2;
                    $qrY2 = $boundaries[$i + 1] - 2;
                    $tokenFound = $this->readQr($img, $qrX1, $qrY1, $qrX2, $qrY2);
                }

                ['strict' => $recStrict, 'soft' => $recSoft] = $this->receivedInkRatios($gray, $recX1, $y1, $recX2, $y2);
                $recInk = max($recStrict, $recSoft * 0.92);
                $sigInk = $sigX2 > $sigX1 + 10
                    ? $this->inkRatio($gray, $sigX1 + $sigPadX, $y1, $sigX2 - $sigPadX, $y2, 140)
                    : 0.0;

                $rowMetrics[] = [
                    'force' => '',
                    'recInk' => $recInk,
                    'sigInk' => $sigInk,
                    'tokenFound' => $tokenFound,
                    'expectedToken' => $expTok,
                ];
                $dbgRec[] = round($recInk, 4);
                $dbgSig[] = round($sigInk, 4);
                $dbgQr[]  = $tokenFound !== null ? 1 : 0;
            }

            $pageCutoff = $this->calibrateCheckboxCutoff(array_map(
                fn ($m) => (float) ($m['recInk'] ?? 0.0),
                array_filter($rowMetrics, fn ($m) => ($m['force'] ?? '') === '')
            ));

            foreach ($rowMetrics as $m) {
                $force = (string) ($m['force'] ?? '');
                $recInk = (float) ($m['recInk'] ?? 0.0);
                $sigInk = (float) ($m['sigInk'] ?? 0.0);
                $tokenFound = $m['tokenFound'] ?? null;
                $expectedToken = $m['expectedToken'] ?? null;
                $hasCheckedBox = $recInk >= $pageCutoff;
                // Lightly shaded boxes in scanned PDFs can fall below page k-means cutoff.
                // When QR definitely matches this row, allow a softer checkbox floor to
                // reduce false negatives without trusting QR alone.
                $rows[] = $this->makeRow($hasCheckedBox, $recInk, $sigInk, $tokenFound, $expectedToken, $force);

                if ($tokenFound !== null) $qrCount++;
                if ($tokenFound !== null && $expectedToken !== null && $tokenFound === $expectedToken) $tokenMatchCount++;
                $recSum += $recInk;
                $recMax = max($recMax, $recInk);
            }

            // Strongly prefer true token matches to avoid wrong-row detections.
            $score = ($tokenMatchCount * 6.0) + ($qrCount * 1.5) + $recSum + ($recMax * 2.0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRows = $rows;
                $bestCutoff = $pageCutoff;
                $bestDbgRec = $dbgRec;
                $bestDbgSig = $dbgSig;
                $bestDbgQr = $dbgQr;
                $bestLayout = (string) $layout['name'];
                $bestRecX1 = $recX1;
                $bestRecX2 = $recX2;
                $bestQrX1 = $qrX1;
                $bestQrX2 = $qrX2;
                $bestQrFoundCount = $qrCount;
            }
        }

        // If the line-detected geometry found zero QR decodes, retry with fixed
        // claim-sheet geometry before giving up. This stabilizes scans where
        // table line detection drifts but QR positions are still predictable.
        if ($canReadQr && $bestQrFoundCount === 0) {
            return $this->fallbackResult($expectedRows, $rowTokens, 'qr_not_found_main', $img, $gray, $w, $h);
        }

        $debug = [
            'img_w'           => $w,
            'img_h'           => $h,
            'table_left'      => $tableLeft,
            'table_right'     => $tableRight,
            'table_top'       => $tableTop,
            'table_bottom'    => $tableBottom,
            'header_bottom'   => $headerBottom,
            'layout_used'     => $bestLayout,
            'rec_x1'          => $bestRecX1,
            'rec_x2'          => $bestRecX2,
            'qr_x1'           => $bestQrX1,
            'qr_x2'           => $bestQrX2,
            'row_h'           => round($approxDataH, 1),
            'checkbox_cutoff' => round($bestCutoff, 4),
            'row_ink_strict'  => implode(',', $bestDbgRec),
            'row_sig_ink'     => implode(',', $bestDbgSig),
            'row_qr_found'    => implode(',', $bestDbgQr),
        ];

        return ['rows' => $bestRows, 'debug' => $debug];
    }
    private function makeRow(
        bool    $hasCheckedBox,
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

        // QR is the only reliable confirmation signal.
        // Checkbox ink alone is not trustworthy: the 4 mm box is easily
        // contaminated by signature bleed, scan noise, or border artifacts.
        if ($tokenMatch && $hasCheckedBox) {
            // QR matches this employee AND checkbox is shaded — confirmed.
            [$status, $conf] = ['confirmed',    0.98];
        } elseif ($tokenMatch) {
            // QR matches but no checkbox ink — employee present but not shaded.
            [$status, $conf] = ['unclaimed',    0.95];
        } elseif ($qrOk && $hasCheckedBox) {
            // QR decoded but token doesn't match expected row — possible mis-sort or
            // wrong page assigned; flag for manual review.
            [$status, $conf] = ['needs_review', 0.70];
        } elseif ($qrOk) {
            // QR decoded, no token match, no checkbox — unclaimed.
            [$status, $conf] = ['unclaimed',    0.80];
        } elseif ($hasCheckedBox) {
            // No QR found. Only surface as review when checkbox ink is strongly
            // present; this keeps recall for clearly marked rows while avoiding
            // noisy border artifacts from being suggested as claimers.
            if ($recInk >= 0.55) {
                [$status, $conf] = ['needs_review', 0.52];
            } else {
                [$status, $conf] = ['unclaimed',    0.60];
            }
        } else {
            // No QR, no checkbox ink — unclaimed.
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
            $iw = imagesx($img);
            $ih = imagesy($img);
            foreach ([0, 4] as $pad) {
                $sx1 = max(0, $x1 - $pad);
                $sy1 = max(0, $y1 - $pad);
                $sx2 = min($iw - 1, $x2 + $pad);
                $sy2 = min($ih - 1, $y2 + $pad);
                $cropW = $sx2 - $sx1;
                $cropH = $sy2 - $sy1;
                if ($cropW <= 8 || $cropH <= 8) continue;

                // Upscale so the smallest dimension is at least 300 px — the QR
                // version used by claim tokens needs ~255 px minimum in each dimension
                // for ZXing to reliably locate finder patterns.
                // Cap at 500 px wide to keep ZXing fast (pure PHP is slow on large images).
                $scale = max(1.0, 300.0 / min($cropW, $cropH));
                $scale = min($scale, 500.0 / max(1, $cropW));
                $scale = max($scale, 1.0);
                $destW = (int) round($cropW * $scale);
                $destH = (int) round($cropH * $scale);

                $crop = imagecreatetruecolor($destW, $destH);
                imagefill($crop, 0, 0, imagecolorallocate($crop, 255, 255, 255));
                imagecopyresampled($crop, $img, 0, 0, $sx1, $sy1, $destW, $destH, $cropW, $cropH);

                $decoded = $this->decodeQrWithVariants($crop);
                imagedestroy($crop);
                if ($decoded !== null) return $decoded;
            }
            return null;

        } catch (\Throwable) {
            return null;
        }
    }

    private function decodeQrWithVariants($crop): ?string
    {
        $decoded = $this->decodeQrFromGd($crop);
        if ($decoded !== null) return $decoded;

        $v1 = imagecreatetruecolor(imagesx($crop), imagesy($crop));
        imagecopy($v1, $crop, 0, 0, 0, 0, imagesx($crop), imagesy($crop));
        $this->applySharpenFilter($v1);
        imagefilter($v1, \IMG_FILTER_CONTRAST, -20);
        $decoded = $this->decodeQrFromGd($v1);
        imagedestroy($v1);
        if ($decoded !== null) return $decoded;

        $v2 = imagecreatetruecolor(imagesx($crop), imagesy($crop));
        imagecopy($v2, $crop, 0, 0, 0, 0, imagesx($crop), imagesy($crop));
        imagefilter($v2, \IMG_FILTER_GRAYSCALE);
        imagefilter($v2, \IMG_FILTER_CONTRAST, -35);
        $decoded = $this->decodeQrFromGd($v2);
        imagedestroy($v2);
        return $decoded;
    }

    private function applySharpenFilter($img): void
    {
        // GD has no IMG_FILTER_SHARPEN constant; use convolution for sharpening.
        if (\function_exists('imageconvolution')) {
            imageconvolution(
                $img,
                [
                    [-1, -1, -1],
                    [-1, 16, -1],
                    [-1, -1, -1],
                ],
                8,
                0
            );
            return;
        }

        // Fallback approximation when convolution is unavailable.
        imagefilter($img, \IMG_FILTER_SMOOTH, -6);
    }

    private function decodeQrFromGd($crop): ?string
    {
        try {
            ob_start();
            imagepng($crop);
            $png = ob_get_clean();
            // Pass false to disable Imagick — GD is faster for small crops and
            // avoids the Imagick luminance source timeout on high-res images.
            $reader  = new \Zxing\QrReader((string) $png, \Zxing\QrReader::SOURCE_TYPE_BLOB, false);
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
    private function fallbackResult(int $expectedRows, array $rowTokens, string $reason, $img, $gray, int $w, int $h): array
    {
        // Approximate positions from CSS layout (% of image width/height).
        // Try both variants (with/without # column) and pick the one with best token matches.
        $layouts = [
            ['name' => 'fallback_no_no_col',   'qr1' => 0.52, 'qr2' => 0.64, 'sig1' => 0.64, 'sig2' => 0.93, 'rec1' => 0.94, 'rec2' => 0.99],
            ['name' => 'fallback_with_no_col', 'qr1' => 0.57, 'qr2' => 0.69, 'sig1' => 0.69, 'sig2' => 0.93, 'rec1' => 0.94, 'rec2' => 0.99],
        ];
        // For pages where rows fit without vertical compression (≤15 rows × 17mm = 255mm < 269mm
        // printable height), use the actual CSS row height (17mm / 297mm × image height).
        // For denser pages the browser compresses rows proportionally to fit the page.
        $fullHeightThreshold = 15;
        if ($expectedRows <= $fullHeightThreshold) {
            $rowH = (int) round($h * 17.0 / 297.0);
        } else {
            $rowH = (int) round($h * 0.76 / $expectedRows);
        }
        $rowH = max(30, $rowH);
        // Add one rowH to skip the table header row (column labels) and land on the first data row.
        $tableTop    = (int) round($h * 0.18) + $rowH;
        $tableBottom = min((int) round($h * 0.94), $tableTop + (int) round(($expectedRows + 0.5) * $rowH));
        $canReadQr = !empty($rowTokens) && class_exists(\Zxing\QrReader::class);
        // PDF rasterization/scans often include white page margins, so using full-image
        // width shifts fixed columns too far right. Anchor fallback columns to detected
        // content bounds instead.
        [$contentLeft, $contentRight] = $this->estimateContentBoundsX($gray, $w, $h);
        $contentW = max(1, $contentRight - $contentLeft);
        $bestRows = [];
        $bestScore = -INF;
        $bestLayout = 'fallback_no_no_col';
        $bestCutoff = 0.15;
        $bestDbgQr = [];
        $bestFirstMismatch = null;
        $bestRowOffset = 0;

        foreach ($layouts as $layout) {
            $qrX1 = $contentLeft + (int) round($contentW * $layout['qr1']);
            $qrX2 = $contentLeft + (int) round($contentW * $layout['qr2']);
            $sigX1 = $contentLeft + (int) round($contentW * $layout['sig1']);
            $sigX2 = $contentLeft + (int) round($contentW * $layout['sig2']);
            $recX1 = $contentLeft + (int) round($contentW * $layout['rec1']);
            $recX2 = $contentLeft + (int) round($contentW * $layout['rec2']);
            $sigPadX = (int) max(2, round(($sigX2 - $sigX1) * 0.03));

            $rowMetrics = [];
            for ($i = 0; $i < $expectedRows; $i++) {
                $cellTop    = $tableTop + (int) ($i * $rowH);
                $cellBottom = min($tableBottom, $tableTop + (int) (($i + 1) * $rowH));
                $y1 = $cellTop + 6;
                $y2 = $cellBottom - 6;

                ['strict' => $recStrict, 'soft' => $recSoft] = $this->receivedInkRatios($gray, $recX1, $y1, $recX2, $y2);
                $recInk = max($recStrict, $recSoft * 0.92);
                $sigInk  = $y2 > $y1 ? $this->inkRatio($gray, $sigX1 + $sigPadX, $y1, $sigX2 - $sigPadX, $y2, 140) : 0.0;
                $tokenFound = null;
                if ($canReadQr) {
                    $qrY1 = $cellTop + 2;
                    $qrY2 = $cellBottom - 2;
                    $tokenFound = $this->readQr($img, $qrX1, $qrY1, $qrX2, $qrY2);
                }

                $rowMetrics[] = [
                    'recInk' => $recInk,
                    'sigInk' => $sigInk,
                    'expectedToken' => $rowTokens[$i] ?? null,
                    'tokenFound' => $tokenFound,
                ];
            }

            $pageCutoff = $this->calibrateCheckboxCutoff(array_map(
                fn ($m) => (float) ($m['recInk'] ?? 0.0),
                $rowMetrics
            ));

            // Some scans are vertically shifted by ~1 row after print+scan. Try small
            // row offsets and keep the best token alignment.
            $layoutBest = null;
            foreach ([0, 1, -1, 2, -2] as $rowOffset) {
                $rows = [];
                $qrCount = 0;
                $tokenMatchCount = 0;
                $recSum = 0.0;
                $recMax = 0.0;
                $dbgQr = [];
                $firstMismatch = null;

                foreach ($rowMetrics as $rowIdx => $m) {
                    $recInk = (float) ($m['recInk'] ?? 0.0);
                    $sigInk = (float) ($m['sigInk'] ?? 0.0);
                    $tokenFound = $m['tokenFound'] ?? null;
                    $expectedToken = $rowTokens[$rowIdx + $rowOffset] ?? null;
                    $hasCheckedBox = $recInk >= $pageCutoff;
                    $rows[] = $this->makeRow($hasCheckedBox, $recInk, $sigInk, $tokenFound, $expectedToken);

                    if ($tokenFound !== null) $qrCount++;
                    if ($tokenFound !== null && $expectedToken !== null && $tokenFound === $expectedToken) $tokenMatchCount++;
                    if ($tokenFound !== null && $expectedToken !== null && $tokenFound !== $expectedToken && $firstMismatch === null) {
                        $foundAtRow = array_search($tokenFound, $rowTokens, true);
                        if ($foundAtRow !== false) {
                            $off = (int) $foundAtRow - $rowIdx;
                            $firstMismatch = "r{$rowIdx}:got=pg_r{$foundAtRow}(off={$off}),exp=r{$rowIdx}";
                        } else {
                            $firstMismatch = "r{$rowIdx}:got=" . substr($tokenFound, 0, 6) . "(FOREIGN),exp=" . substr((string) $expectedToken, 0, 6);
                        }
                    }
                    $recSum += $recInk;
                    $recMax = max($recMax, $recInk);
                    $dbgQr[] = $tokenFound !== null ? 1 : 0;
                }

                $score = ($tokenMatchCount * 8.0) + ($qrCount * 1.5) + $recSum + ($recMax * 2.0);
                if (!$layoutBest || $score > $layoutBest['score']) {
                    $layoutBest = [
                        'score' => $score,
                        'rows' => $rows,
                        'dbgQr' => $dbgQr,
                        'firstMismatch' => $firstMismatch,
                        'rowOffset' => $rowOffset,
                    ];
                }
            }

            if (!$layoutBest) {
                continue;
            }

            $score = (float) $layoutBest['score'];
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRows = (array) $layoutBest['rows'];
                $bestLayout = (string) $layout['name'];
                $bestCutoff = $pageCutoff;
                $bestDbgQr = (array) $layoutBest['dbgQr'];
                $bestFirstMismatch = $layoutBest['firstMismatch'] ?? null;
                $bestRowOffset = (int) ($layoutBest['rowOffset'] ?? 0);
            }
        }

        return [
            'rows'  => $bestRows,
            'debug' => array_filter([
                'fallback_reason' => $reason,
                'layout_used' => $bestLayout,
                'content_left' => $contentLeft,
                'content_right' => $contentRight,
                'checkbox_cutoff' => round($bestCutoff, 4),
                'row_qr_found' => implode(',', $bestDbgQr),
                'row_offset' => $bestRowOffset,
                'first_mismatch' => $bestFirstMismatch ?? null,
            ], fn ($v) => $v !== null),
        ];
    }

    private function estimateContentBoundsX($gray, int $w, int $h): array
    {
        $stepY = 4;
        $dens = [];
        for ($x = 0; $x < $w; $x++) {
            $black = 0;
            $total = 0;
            for ($y = 0; $y < $h; $y += $stepY) {
                $total++;
                if ((imagecolorat($gray, $x, $y) & 0xFF) < 238) $black++;
            }
            $dens[$x] = $total > 0 ? $black / $total : 0.0;
        }

        $candidates = [];
        foreach ($dens as $x => $d) {
            if ($d >= 0.006) $candidates[] = (int) $x;
        }
        if (empty($candidates)) {
            return [0, max(1, $w - 1)];
        }

        $left = (int) min($candidates);
        $right = (int) max($candidates);
        $pad = (int) max(6, round($w * 0.005));
        $left = max(0, $left - $pad);
        $right = min($w - 1, $right + $pad);

        if (($right - $left) < (int) round($w * 0.45)) {
            return [0, max(1, $w - 1)];
        }
        return [$left, $right];
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

    private function receivedBoxInnerRect(int $x1, int $y1, int $x2, int $y2, float $ratio = 0.70, float $dxFrac = 0.0, float $dyFrac = 0.0): array
    {
        $cw   = max(1, $x2 - $x1);
        $ch   = max(1, $y2 - $y1);
        $size = (int) round(min($cw, $ch) * $ratio);
        $centerX = $x1 + ($cw / 2.0) + ($cw * $dxFrac);
        $centerY = $y1 + ($ch / 2.0) + ($ch * $dyFrac);
        $bx1 = (int) round($centerX - ($size / 2.0));
        $by1 = (int) round($centerY - ($size / 2.0));
        $bx1 = max($x1, min($x2 - $size, $bx1));
        $by1 = max($y1, min($y2 - $size, $by1));
        $in   = (int) round($size * 0.12);
        return [$bx1 + $in, $by1 + $in, $bx1 + $size - $in, $by1 + $size - $in];
    }

    private function receivedInkRatios($img, int $x1, int $y1, int $x2, int $y2): array
    {
        $bestStrict = 0.0;
        $bestSoft = 0.0;
        // Search slightly around the nominal center because print/scans can shift
        // the tiny checkbox within the received column cell.
        foreach ([0.30, 0.45, 0.60, 0.75, 0.88] as $ratio) {
            foreach ([-0.18, 0.0, 0.18] as $dxFrac) {
                foreach ([-0.12, 0.0, 0.12] as $dyFrac) {
                    [$rx1, $ry1, $rx2, $ry2] = $this->receivedBoxInnerRect($x1, $y1, $x2, $y2, $ratio, $dxFrac, $dyFrac);
                    if ($rx2 - $rx1 < 4 || $ry2 - $ry1 < 4) continue;
                    $adaptiveThreshold = $this->adaptiveInkThreshold($img, $rx1, $ry1, $rx2, $ry2);
                    $bestStrict = max($bestStrict, $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, $adaptiveThreshold));
                    $bestSoft = max($bestSoft, $this->inkRatio($img, $rx1, $ry1, $rx2, $ry2, 145));
                }
            }
        }
        return ['strict' => $bestStrict, 'soft' => $bestSoft];
    }

    private function adaptiveInkThreshold($img, int $x1, int $y1, int $x2, int $y2): int
    {
        [$mean, $std] = $this->regionLumaStats($img, $x1, $y1, $x2, $y2);
        // Dark-ink threshold adjusted per box based on local brightness + contrast.
        $threshold = (int) round($mean - max(10.0, $std * 0.55));
        return max(85, min(185, $threshold));
    }

    private function regionLumaStats($img, int $x1, int $y1, int $x2, int $y2): array
    {
        $iw = imagesx($img); $ih = imagesy($img);
        $x1 = max(0, min($iw - 1, $x1)); $x2 = max(0, min($iw - 1, $x2));
        $y1 = max(0, min($ih - 1, $y1)); $y2 = max(0, min($ih - 1, $y2));

        $sum = 0.0;
        $sumSq = 0.0;
        $count = 0;
        for ($y = $y1; $y <= $y2; $y += 2) {
            for ($x = $x1; $x <= $x2; $x += 2) {
                $g = (float) (imagecolorat($img, $x, $y) & 0xFF);
                $sum += $g;
                $sumSq += $g * $g;
                $count++;
            }
        }
        if ($count <= 0) return [255.0, 0.0];
        $mean = $sum / $count;
        $variance = max(0.0, ($sumSq / $count) - ($mean * $mean));
        return [$mean, sqrt($variance)];
    }

    private function calibrateCheckboxCutoff(array $inks): float
    {
        $vals = array_values(array_filter(array_map(
            fn ($v) => is_numeric($v) ? (float) $v : null,
            $inks
        ), fn ($v) => $v !== null));

        $default = 0.15;
        $n = count($vals);
        if ($n < 4) return $default;

        sort($vals);
        $c1 = $vals[0];
        $c2 = $vals[$n - 1];

        for ($iter = 0; $iter < 8; $iter++) {
            $sum1 = 0.0; $sum2 = 0.0; $n1 = 0; $n2 = 0;
            foreach ($vals as $v) {
                if (abs($v - $c1) <= abs($v - $c2)) {
                    $sum1 += $v; $n1++;
                } else {
                    $sum2 += $v; $n2++;
                }
            }
            if ($n1 === 0 || $n2 === 0) return $default;
            $c1 = $sum1 / $n1;
            $c2 = $sum2 / $n2;
        }

        if (abs($c2 - $c1) < 0.04) return $default;

        $cut = ($c1 + $c2) / 2.0;
        return max(0.12, min(0.30, $cut));
    }
}
