<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

// Service untuk ekstraksi data dokumen menggunakan Gemini Vision API
class GeminiVisionService
{
    private string $apiKey;
    private string $model;
    private int $maxRequestsPerMinute = 15; // Rate limit free tier

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.5-flash'));
    }

    // Ekstrak data dari gambar dokumen
    public function extractFromImage(UploadedFile $file): array
    {
        // Cek rate limit dulu
        $this->cekRateLimit();

        // Konversi gambar ke base64
        $imageBase64 = base64_encode(file_get_contents($file->getPathname()));
        $mimeType = $file->getMimeType();

        // Siapkan payload request
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->buatPromptEkstraksi()],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $imageBase64,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 4000,
                'responseMimeType' => 'application/json',
            ],
        ];

        // Kirim request ke Gemini API
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        Log::info('Gemini API Request', [
            'model' => $this->model,
            'url' => str_replace($this->apiKey, '***API_KEY***', $url),
        ]);

        $response = Http::timeout(60)->post($url, $payload);

        if (!$response->ok()) {
            $errorBody = $response->json();
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $errorBody,
                'headers' => $response->headers(),
            ]);

            $errorMessage = $errorBody['error']['message'] ?? 'Unknown error';
            $errorCode = $errorBody['error']['code'] ?? $response->status();

            throw new \RuntimeException("Gagal menghubungi Gemini API: {$errorCode} - {$errorMessage}");
        }

        // Ambil teks response
        $data = $response->json();
        $teksResponse = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        Log::info('Gemini response diterima', ['length' => strlen($teksResponse)]);

        return $this->parseJsonResponse($teksResponse);
    }

    // Cek apakah sudah melebihi rate limit
    private function cekRateLimit(): void
    {
        $key = 'gemini_requests';
        $requests = Cache::get($key, []);
        $sekarang = time();

        // Hapus request yang sudah lebih dari 1 menit
        $requests = array_filter($requests, function ($waktu) use ($sekarang) {
            return $waktu > ($sekarang - 60);
        });

        // Cek apakah sudah mencapai limit
        if (count($requests) >= $this->maxRequestsPerMinute) {
            $sisaWaktu = 60 - ($sekarang - min($requests));
            throw new \RuntimeException("Rate limit tercapai. Tunggu {$sisaWaktu} detik.");
        }

        // Catat request baru
        $requests[] = $sekarang;
        Cache::put($key, $requests, 120);
    }

    // Parse JSON dari response Gemini
    private function parseJsonResponse(string $teks): array
    {
        $teks = trim($teks);

        // Hapus markdown code blocks jika ada
        $teks = preg_replace('/^```json\s*/is', '', $teks);
        $teks = preg_replace('/^```\s*/is', '', $teks);
        $teks = preg_replace('/\s*```\s*$/is', '', $teks);
        $teks = trim($teks);

        // Coba decode langsung
        $hasil = json_decode($teks, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($hasil)) {
            return $hasil;
        }

        // Coba cari JSON object dalam teks
        $posisiMulai = strpos($teks, '{');
        if ($posisiMulai !== false) {
            $jsonTeks = $this->ekstrakJsonObject($teks, $posisiMulai);

            // Perbaiki masalah umum JSON (trailing comma)
            $jsonTeks = preg_replace('/,\s*}/', '}', $jsonTeks);
            $jsonTeks = preg_replace('/,\s*]/', ']', $jsonTeks);

            $hasil = json_decode($jsonTeks, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($hasil)) {
                return $hasil;
            }
        }

        // Jika gagal, kembalikan data default
        Log::warning('Gagal parse JSON dari Gemini', ['teks' => substr($teks, 0, 200)]);

        return [
            'tipe_dokumen' => 'unknown',
            'confidence' => 0,
            'tanggal_transaksi' => null,
            'total_pembayaran' => null,
            'raw_text' => $teks,
        ];
    }

    // Ekstrak JSON object dari teks dengan bracket matching
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

    // Buat prompt untuk ekstraksi data dokumen
    private function buatPromptEkstraksi(): string
    {
        return <<<PROMPT
Ekstrak data dari gambar dokumen keuangan Indonesia ini ke format JSON.

Berikan HANYA JSON object (tanpa penjelasan) dengan struktur:
{
    "tipe_dokumen": "kuitansi" atau "invoice" atau "struk" atau "nota" atau "unknown",
    "confidence": angka 0-100,
    "tanggal_transaksi": "YYYY-MM-DD" atau null,
    "nomor_dokumen": "string" atau null,
    "nama_vendor": "string" atau null,
    "alamat_vendor": "string" atau null,
    "nama_penerima": "string" atau null,
    "total_pembayaran": angka tanpa format,
    "terbilang": "string" atau null,
    "daftar_item": [{"nama": "string", "qty": angka, "harga_satuan": angka, "subtotal": angka}],
    "pajak": {"ppn": angka atau null, "pph": angka atau null},
    "cara_pembayaran": "tunai" atau "transfer" atau "kartu" atau null,
    "keterangan": "string",
    "raw_text": "semua teks yang terbaca"
}

Aturan:
- Angka harus numerik (17000 bukan "17.000")
- Tanggal format YYYY-MM-DD
- Jika tidak terbaca, isi null
PROMPT;
    }
}
