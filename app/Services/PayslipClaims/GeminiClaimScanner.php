<?php

namespace App\Services\PayslipClaims;

use Illuminate\Support\Facades\Http;

/**
 * Uses Gemini Vision (free tier) to detect which rows on a payslip claim sheet
 * have their Rec. checkbox shaded/filled by the employee.
 *
 * Returns an array indexed by emp_no => bool (true = shaded/claimed).
 */
class GeminiClaimScanner
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model  = (string) config('services.gemini.model', 'gemini-2.0-flash');
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Scan one page image and return which emp_nos have their Rec. box shaded.
     *
     * @param  string $imagePath  Absolute path to the PNG/JPG image.
     * @param  array  $slice      Ordered list of row arrays, each with 'emp_no' and 'name'.
     * @return array{emp_no: string, claimed: bool, confidence: float}[]
     */
    public function scanPage(string $imagePath, array $slice): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $imageData = @file_get_contents($imagePath);
        if ($imageData === false) {
            throw new \RuntimeException('Cannot read image file: ' . $imagePath);
        }

        $mime       = $this->detectMime($imagePath);
        $base64     = base64_encode($imageData);
        $rowList    = $this->buildRowList($slice);
        $prompt     = $this->buildPrompt($rowList);

        $payload = [
            'contents' => [[
                'parts' => [
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data'      => $base64,
                        ],
                    ],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0.0,
                'maxOutputTokens' => 2048,
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(60)
            ->withoutVerifying()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Gemini API error ' . $response->status() . ': ' . $response->body()
            );
        }

        $text = $this->extractText($response->json());
        \Illuminate\Support\Facades\Log::debug('GeminiRaw: ' . $text);
        $parsed = $this->parseResponse($text, $slice);
        \Illuminate\Support\Facades\Log::debug('GeminiParsed: ' . json_encode($parsed));
        return $parsed;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildRowList(array $slice): string
    {
        $lines = [];
        foreach ($slice as $i => $row) {
            $empNo = (string) ($row['emp_no'] ?? '');
            $name  = (string) ($row['name']   ?? '');
            $lines[] = ($i + 1) . '. EmpID=' . $empNo . ' ' . $name;
        }
        return implode("\n", $lines);
    }

    private function buildPrompt(string $rowList): string
    {
        return <<<PROMPT
This is a scanned payslip claim sheet. Look at the rightmost column labelled "Rec." (or "□ Rec."). Each row has a small square checkbox in that column.

IMPORTANT: Some checkboxes are FILLED/SHADED with dark ink (meaning the employee claimed their payslip). Others are completely EMPTY white squares (not claimed). Look carefully — even a small dark square or a box filled with dark color counts as claimed=true.

For each employee row listed below, examine their checkbox in the "Rec." column and determine if it is filled/shaded (claimed=true) or empty (claimed=false).

Rows on this page (in top-to-bottom order):
{$rowList}

Reply with ONLY a JSON array, no explanation. Each element:
- "emp_no": employee ID exactly as listed
- "claimed": true if checkbox is dark/filled/shaded, false if empty/white
- "confidence": 0.0-1.0

Example: [{"emp_no":"0035","claimed":true,"confidence":0.95},{"emp_no":"0004","claimed":false,"confidence":0.98}]
PROMPT;
    }

    private function extractText(array $json): string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ((array) $parts as $part) {
            $text .= (string) ($part['text'] ?? '');
        }
        return $text;
    }

    private function parseResponse(string $text, array $slice): array
    {
        // Extract the JSON array from the response, ignoring any markdown fences.
        $text = trim($text);
        // Try to find a JSON array directly in the text.
        if (preg_match('/\[[\s\S]*\]/u', $text, $m)) {
            $text = $m[0];
        }
        $text = trim($text);

        $decoded = json_decode($text, true);
        \Illuminate\Support\Facades\Log::debug('GeminiJsonErr: ' . json_last_error_msg() . ' | len=' . strlen($text) . ' | first200=' . substr($text, 0, 200));

        // Build a known emp_no set for validation.
        $knownEmpNos = array_flip(array_map(
            fn ($r) => (string) ($r['emp_no'] ?? ''),
            $slice
        ));

        $results = [];

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) continue;
                $empNo     = (string) ($item['emp_no']     ?? '');
                $claimed   = (bool)   ($item['claimed']    ?? false);
                $confidence = (float) ($item['confidence'] ?? 0.5);

                if ($empNo === '' || !isset($knownEmpNos[$empNo])) continue;

                $results[$empNo] = [
                    'emp_no'     => $empNo,
                    'claimed'    => $claimed,
                    'confidence' => min(1.0, max(0.0, $confidence)),
                ];
            }
        }

        // Fill in any missing rows as unclaimed.
        foreach ($slice as $row) {
            $empNo = (string) ($row['emp_no'] ?? '');
            if ($empNo !== '' && !isset($results[$empNo])) {
                $results[$empNo] = [
                    'emp_no'     => $empNo,
                    'claimed'    => false,
                    'confidence' => 0.5,
                ];
            }
        }

        return array_values($results);
    }

    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    }
}
