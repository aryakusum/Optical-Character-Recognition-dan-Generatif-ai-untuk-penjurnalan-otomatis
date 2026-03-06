<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

// Service untuk generate jurnal menggunakan AI Gemini
class AiJournalService
{
    // Generate jurnal dari data terstruktur
    public function generateJournalEntries(string $dataTerstruktur, ?string $promptUser = null): array
    {
        $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.5-flash'));

        // Prompt instruksi untuk AI
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

        // Gabungkan prompt dengan data
        $prompt = $instruksi . "\n\nData:\n" . trim($dataTerstruktur);
        if ($promptUser) {
            $prompt .= "\n\nInstruksi tambahan:\n" . $promptUser;
        }

        // Kirim request ke Gemini
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,               // Rendah untuk konsistensi
                'maxOutputTokens' => 4000,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $response = Http::timeout(60)->post($url, $payload);

        if (!$response->ok()) {
            throw new \RuntimeException('Gemini request failed: ' . $response->status());
        }

        $data = $response->json();
        $teks = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Bersihkan markdown wrapper jika ada
        $teks = preg_replace('/^```json\s*/i', '', $teks);
        $teks = preg_replace('/\s*```$/i', '', $teks);
        $teks = trim($teks);

        // Parse JSON hasil
        $hasil = json_decode($teks, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        return $hasil;
    }
}
