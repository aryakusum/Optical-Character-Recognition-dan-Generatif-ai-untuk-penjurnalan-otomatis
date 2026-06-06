<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiJournalService
{
    private const ALLOWED_MODELS = [
        'gemini-2.5-flash',
        'gemini-2.5-pro',
        'gemini-3-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
        'gemini-1.5-pro',
    ];

    public function generateJournalEntries(string $dataTerstruktur, ?string $promptUser = null): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (!$apiKey) {
            throw new \RuntimeException('API key tidak dikonfigurasi');
        }

        $model = trim($model);
        if (!in_array($model, self::ALLOWED_MODELS)) {
            Log::warning('SECURITY: Invalid Gemini model attempted', ['model' => $model]);
            $model = 'gemini-2.5-flash';
        }

        $instruksi = <<<EOT
Kamu adalah akuntan. Proses data transaksi dan buat jurnal dalam format JSON.

Langkah:
1. Validasi: Bandingkan total_pembayaran dengan jumlah subtotal di daftar_item.
2. Kategorisasi: Tentukan kode akun untuk setiap item.
3. Buat jurnal: Debit untuk beban, Credit untuk kas/bank.
4. Pastikan Total Debit = Total Credit.

Output JSON:
{
    "validation_status": "VALID" atau "WARNING",
    "validation_note": "string",
    "date": "YYYY-MM-DD",
    "vendor": "string",
    "description": "string",
    "currency": "IDR",
    "total": number,
    "items": [
        {
            "name": "string",
            "qty": number,
            "price": number,
            "subtotal": number
        }
    ],
    "recommended_accounts": [
        {
            "code": "string",
            "name": "string",
            "type": "Debit" atau "Credit"
        }
    ],
    "lines": [
        {
            "account_code": "string",
            "account_name": "string",
            "debit": number,
            "credit": number
        }
    ]
}
EOT;

        $prompt = $instruksi . "\n\nData:\n" . trim($dataTerstruktur);
        if ($promptUser) {
            $sanitizedPrompt = strip_tags(trim($promptUser));
            $sanitizedPrompt = substr($sanitizedPrompt, 0, 2000);
            $prompt .= "\n\nInstruksi tambahan:\n" . $sanitizedPrompt;
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        try {
            $response = Http::retry(3, 2000)->connectTimeout(30)->timeout(120)->post($url, $payload);
        } catch (\Exception $e) {
            Log::error('Gemini API Connection Error', ['error' => 'Request failed or timed out']);
            throw new \RuntimeException('Koneksi ke layanan AI gagal atau timeout. Silakan coba lagi.');
        }

        if (!$response->ok()) {
            Log::error('Gemini API request failed', ['status' => $response->status()]);
            throw new \RuntimeException('Gagal menghubungi layanan AI');
        }

        $data = $response->json();
        $teks = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return $this->parseJsonResponse($teks);
    }

    private function parseJsonResponse(string $teks): array
    {
        $teks = trim($teks);

        $teks = preg_replace('/^```json\s*/is', '', $teks);
        $teks = preg_replace('/^```\s*/is', '', $teks);
        $teks = preg_replace('/\s*```\s*$/is', '', $teks);
        $teks = trim($teks);

        $hasil = json_decode($teks, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($hasil)) {
            return $hasil;
        }

        $posisiMulai = strpos($teks, '{');
        if ($posisiMulai !== false) {
            $jsonTeks = $this->ekstrakJsonObject($teks, $posisiMulai);

            $jsonTeks = preg_replace('/,\s*}/', '}', $jsonTeks);
            $jsonTeks = preg_replace('/,\s*]/', ']', $jsonTeks);

            $hasil = json_decode($jsonTeks, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($hasil)) {
                return $hasil;
            }
        }

        Log::error('Invalid JSON from Gemini', ['text_end' => substr($teks, -500)]);
        throw new \RuntimeException('Gagal memproses respons AI. Pastikan format output sesuai (JSON).');
    }

    private function ekstrakJsonObject(string $teks, int $posisiMulai): string
    {
        $depth = 0;
        $posisiAkhir = $posisiMulai;
        $dalamString = false;
        $escape = false;

        for ($i = $posisiMulai; $i < strlen($teks); $i++) {
            $char = $teks[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $dalamString = !$dalamString;
                continue;
            }

            if (!$dalamString) {
                if ($char === '{') $depth++;
                if ($char === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $posisiAkhir = $i;
                        break;
                    }
                }
            }
        }

        return substr($teks, $posisiMulai, $posisiAkhir - $posisiMulai + 1);
    }
}
