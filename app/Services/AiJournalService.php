<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiJournalService
{
    // Fungsi utama: kirim data ke AI Gemini dan ambil output jurnal
    public function generateJournalEntries(string $dataTerstruktur, ?string $promptUser = null): array
    {
        $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $modelUsed = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.5-flash'));

        // Instruksi & prompt dibuat sederhana
        $instruksi = 'Convert to journal JSON: {date, vendor, description, currency, lines: [{account_code, account_name, debit, credit}]}. Ensure debits = credits.';
        $prompt = $instruksi . "\n\nData:\n" . trim($dataTerstruktur);

        $apiPayload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [ ['text' => $prompt] ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 4000,
            ],
        ];
        $urlEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelUsed . ':generateContent?key=' . $apiKey;
        $response = Http::timeout(60)->post($urlEndpoint, $apiPayload);

        if (!$response->ok()) {
            throw new \RuntimeException('Gemini request failed: ' . $response->status() . ' - ' . $response->body());
        }

        $responseData = $response->json();
        $responseText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Hilangkan formatting markdown di awal-akhir
        $responseText = preg_replace('/^```json\s*/', '', $responseText);
        $responseText = preg_replace('/\s*```$/', '', $responseText);
        $responseText = trim($responseText);

        $hasil = json_decode($responseText, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($hasil)) {
            throw new \RuntimeException('Invalid AI response format. Content: ' . $responseText);
        }

        return $hasil;
    }
}


