<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    public function extractTextFromImage(UploadedFile $uploadedFile): string
    {
        $apiKey = config('services.ocr_space.key');
        $apiUrl = 'https://api.ocr.space/parse/image';

        if (!$apiKey) {
            throw new \RuntimeException('OCR API key tidak dikonfigurasi');
        }

        $response = Http::retry(3, 2000)
            ->asMultipart()
            ->timeout(60)
            ->attach('file', file_get_contents($uploadedFile->getRealPath()), $uploadedFile->getClientOriginalName())
            ->post($apiUrl, [
                'apikey' => $apiKey,
                'language' => 'eng',
                'OCREngine' => '2',
                'isTable' => 'true',
                'scale' => 'true',
            ]);

        if (!$response->ok()) {
            Log::warning('OCR API communication error', ['status' => $response->status()]);
            throw new \RuntimeException('Gagal menghubungi layanan OCR');
        }

        $responseData = $response->json();

        if (isset($responseData['IsErroredOnProcessing']) && $responseData['IsErroredOnProcessing']) {
            Log::error('OCR processing error', ['details' => $responseData['ErrorMessage'] ?? 'unknown']);
            throw new \RuntimeException('Gagal memproses dokumen di layanan OCR');
        }

        if (!isset($responseData['ParsedResults'][0]['ParsedText'])) {
            throw new \RuntimeException('Hasil OCR tidak valid');
        }

        return (string) $responseData['ParsedResults'][0]['ParsedText'];
    }
}
