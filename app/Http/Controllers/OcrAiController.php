<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\OcrService;
use App\Services\AiJournalService;
use App\Services\TransactionExtractor;

class OcrAiController extends Controller
{
    // Depedensi utama controller: OCR, AI, dan Transaction Extractor
    protected OcrService $ocrService;
    protected AiJournalService $aiJournalService;
    protected TransactionExtractor $transactionExtractor;

    public function __construct(OcrService $ocrService, AiJournalService $aiJournalService, TransactionExtractor $transactionExtractor)
    {
        $this->ocrService = $ocrService;
        $this->aiJournalService = $aiJournalService;
        $this->transactionExtractor = $transactionExtractor;
    }

    // Tampilkan halaman upload bukti transaksi
    public function index()
    {
        return view('journal.upload');
    }

    // Proses file upload: OCR lalu ekstraksi data terstruktur dari text
    public function process(Request $request)
    {
        // Validasi input (gambar/pdf wajib)
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var UploadedFile $file */
        $uploadedFile = $request->file('image');

        try {
            // Ekstrak text OCR
            $hasilTextOcr = $this->ocrService->extractTextFromImage($uploadedFile);
            // Ekstrak data terstruktur (toko, total, items, dst)
            $dataTerstruktur = $this->transactionExtractor->extract($hasilTextOcr);

            return response()->json([
                'success' => true,
                'ocr_text' => $hasilTextOcr,
                'structured' => $dataTerstruktur,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal proses OCR/ekstraksi', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Proses generate jurnal memakai AI setelah data struk terstruktur OK
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'structured' => 'required|array',
            'prompt' => 'nullable|string',
        ]);

        try {
            $dataTerstruktur = $validated['structured'];
            $promptOpsional = $validated['prompt'] ?? null;
            $inputAi = json_encode($dataTerstruktur, JSON_UNESCAPED_UNICODE);
            // Panggil service AI Gemini
            $hasilJurnal = $this->aiJournalService->generateJournalEntries($inputAi, $promptOpsional);

            // Simpan hasil di session supaya bisa ditampilkan dalam bentuk tabel
            $request->session()->put('journal_data', $hasilJurnal);

            return response()->json([
                'success' => true,
                'journal' => $hasilJurnal,
                'table_url' => route('journal.table'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Tampilkan tabel hasil jurnal untuk diedit user
    public function showTable(Request $request)
    {
        $journalData = $request->session()->get('journal_data');
        if (!$journalData) {
            return redirect()->route('journal.index')->with('error', 'Belum ada data jurnal. Generate jurnal dulu.');
        }
        return view('journal.table', compact('journalData'));
    }
}


