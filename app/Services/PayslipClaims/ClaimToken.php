<?php

namespace App\Services\PayslipClaims;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ClaimToken
{
    /**
     * Generate a deterministic 12-char uppercase hex token for a run+employee pair.
     * No DB storage needed — reproducible anytime from run_id and employee_id.
     */
    public static function generate(int $runId, int $employeeId): string
    {
        return strtoupper(
            substr(hash_hmac('sha256', "{$runId}:{$employeeId}", config('app.key')), 0, 12)
        );
    }

    /**
     * Return a PNG QR code as a base64 data URI (for embedding in dompdf HTML).
     */
    public static function qrDataUri(string $token): string
    {
        $options = new QROptions([
            'outputType'       => QRCode::OUTPUT_IMAGE_PNG,
            'imageBase64'      => true,
            'scale'            => 3,
            'eccLevel'         => QRCode::ECC_H,
            'imageTransparent' => false,
        ]);
        return (new QRCode($options))->render($token);
    }
}
