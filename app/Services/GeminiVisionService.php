<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiVisionService
{
    private string $apiKey;
    private string $model;
    private int $maxRequestsPerMinute = 15;

    private const ALLOWED_MODELS = [
        'gemini-2.5-flash',
        'gemini-2.5-pro',
        'gemini-3-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
        'gemini-1.5-pro',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $model = trim(config('services.gemini.model', 'gemini-2.5-flash'));

        if (!in_array($model, self::ALLOWED_MODELS)) {
            Log::warning('SECURITY: Invalid Gemini model in config', ['model' => $model]);
            $model = 'gemini-2.5-flash';
        }

        $this->model = $model;
    }

    public function extractFromImage(UploadedFile $file): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('API key tidak dikonfigurasi');
        }

        $this->cekRateLimit();

        $imageBase64 = base64_encode(file_get_contents($file->getPathname()));
        $mimeType = $file->getMimeType();

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new \RuntimeException('Tipe gambar tidak didukung');
        }

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
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        Log::info('Gemini Vision request', ['model' => $this->model]);

        try {
            $response = Http::retry(3, 2000)->connectTimeout(30)->timeout(120)->post($url, $payload);
        } catch (\Exception $e) {
            Log::error('Gemini Vision API Connection Error', ['error' => 'Request failed or timed out']);
            throw new \RuntimeException('Koneksi ke layanan AI gagal atau timeout. Silakan coba lagi.');
        }

        if (!$response->ok()) {
            Log::error('Gemini Vision API error', ['status' => $response->status()]);
            throw new \RuntimeException('Gagal menghubungi layanan AI');
        }

        $data = $response->json();
        $teksResponse = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return $this->parseJsonResponse($teksResponse);
    }

    private function cekRateLimit(): void
    {
        $key = 'gemini_requests';
        $requests = Cache::get($key, []);
        $sekarang = time();

        $requests = array_filter($requests, function ($waktu) use ($sekarang) {
            return $waktu > ($sekarang - 60);
        });

        if (count($requests) >= $this->maxRequestsPerMinute) {
            $sisaWaktu = 60 - ($sekarang - min($requests));
            throw new \RuntimeException("Rate limit tercapai. Tunggu {$sisaWaktu} detik.");
        }

        $requests[] = $sekarang;
        Cache::put($key, $requests, 120);
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

        Log::warning('Gagal parse JSON dari Gemini', ['teks' => substr($teks, 0, 200)]);

        return [
            'tipe_dokumen' => 'unknown',
            'confidence' => 0,
            'tanggal_transaksi' => null,
            'total_pembayaran' => null,
            'raw_text' => $teks,
        ];
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
