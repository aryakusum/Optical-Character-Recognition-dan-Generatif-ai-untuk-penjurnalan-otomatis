<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    // Fungsi utama: ekstrak text dari file gambar/pdf dengan OCR.space
    public function extractTextFromImage(UploadedFile $uploadedFile): string
    {
        $apiKey = config('services.ocr_space.key', env('OCR_SPACE_API_KEY'));
        $apiUrl = 'https://api.ocr.space/parse/image';

        // Kirim file ke API OCR.space
        $response = Http::asMultipart()
            ->timeout(60)
            ->attach('file', file_get_contents($uploadedFile->getRealPath()), $uploadedFile->getClientOriginalName())
            ->post($apiUrl, [
                'apikey' => $apiKey,
                'language' => 'eng',
                'OCREngine' => '2',
                'isTable' => 'true',
                'scale' => 'true',
            ]);

        // Cek respon error dari API
        if (!$response->ok()) {
            Log::warning('Gagal komunikasi ke OCR.space', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OCR request failed');
        }

        // Ambil hasil json dari response
        $responseData = $response->json();

        // Cek error dari provider OCR
        if (isset($responseData['IsErroredOnProcessing']) && $responseData['IsErroredOnProcessing']) {
            $detailError = $responseData['ErrorMessage'] ?? $responseData['ErrorDetails'] ?? 'OCR provider error';
            if (is_array($detailError)) {
                $detailError = implode('; ', $detailError);
            }
            throw new \RuntimeException('OCR error: ' . $detailError);
        }

        // Pastikan hasil valid
        if (!isset($responseData['ParsedResults'][0]['ParsedText'])) {
            throw new \RuntimeException('OCR response invalid');
        }

        // Return text hasil OCR
        return (string) $responseData['ParsedResults'][0]['ParsedText'];
    }
}


