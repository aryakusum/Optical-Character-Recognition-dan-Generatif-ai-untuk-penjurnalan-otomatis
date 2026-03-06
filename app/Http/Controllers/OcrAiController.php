<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Unit;
use App\Services\OcrService;
use App\Services\AiJournalService;
use App\Services\TransactionExtractor;
use App\Services\DocumentTypeDetector;
use App\Services\DocumentChecklistService;
use App\Services\AmountValidationService;
use App\Services\UnitAccountService;
use App\Services\GeminiVisionService;

// Controller untuk proses OCR dan pembuatan jurnal dengan AI
class OcrAiController extends Controller
{
    private OcrService $ocrService;
    private AiJournalService $aiJournalService;
    private TransactionExtractor $transactionExtractor;
    private DocumentTypeDetector $documentTypeDetector;
    private DocumentChecklistService $documentChecklistService;
    private AmountValidationService $amountValidationService;
    private UnitAccountService $unitAccountService;
    private GeminiVisionService $geminiVisionService;

    public function __construct(
        OcrService $ocrService,
        AiJournalService $aiJournalService,
        TransactionExtractor $transactionExtractor,
        DocumentTypeDetector $documentTypeDetector,
        DocumentChecklistService $documentChecklistService,
        AmountValidationService $amountValidationService,
        UnitAccountService $unitAccountService,
        GeminiVisionService $geminiVisionService
    ) {
        $this->ocrService = $ocrService;
        $this->aiJournalService = $aiJournalService;
        $this->transactionExtractor = $transactionExtractor;
        $this->documentTypeDetector = $documentTypeDetector;
        $this->documentChecklistService = $documentChecklistService;
        $this->amountValidationService = $amountValidationService;
        $this->unitAccountService = $unitAccountService;
        $this->geminiVisionService = $geminiVisionService;
    }

    // Tampilkan halaman upload dokumen
    public function index()
    {
        $units = Unit::active()->orderBy('name')->get();
        $user = Auth::user();

        return view('journal.upload', compact('units', 'user'));
    }

    // Proses dokumen yang diupload (gambar atau PDF)
    public function process(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $file = $request->file('image');
        $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf';

        try {
            // Gambar pakai Gemini Vision, PDF pakai OCR.space
            if (!$isPdf) {
                return $this->prosesGeminiVision($file);
            } else {
                return $this->prosesOcrSpace($file);
            }
        } catch (\Exception $e) {
            Log::error('Gagal proses dokumen', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Proses gambar dengan Gemini Vision API
    private function prosesGeminiVision(UploadedFile $file)
    {
        // Ekstrak data dari gambar
        $hasil = $this->geminiVisionService->extractFromImage($file);

        // Mapping ke format standar
        $dataTerstruktur = [
            'tanggal_transaksi' => $hasil['tanggal_transaksi'] ?? null,
            'nama_toko' => $hasil['nama_vendor'] ?? null,
            'currency' => 'IDR',
            'total_pembayaran' => $hasil['total_pembayaran'] ?? null,
            'daftar_item' => $hasil['daftar_item'] ?? [],
            'nomor_resi' => $hasil['nomor_dokumen'] ?? null,
            'info_pelanggan' => ['nama' => $hasil['nama_penerima'] ?? null],
            'cara_pembayaran' => $hasil['cara_pembayaran'] ?? null,
            'struk_mentah' => explode("\n", $hasil['raw_text'] ?? ''),
            'terbilang' => $hasil['terbilang'] ?? null,
            'keterangan' => $hasil['keterangan'] ?? null,
            'pajak' => $hasil['pajak'] ?? [],
        ];

        $tipeDokumen = [
            'type' => $hasil['tipe_dokumen'] ?? 'unknown',
            'confidence' => $hasil['confidence'] ?? 0,
        ];

        // Validasi kelengkapan dan perhitungan
        $hasilChecklist = $this->documentChecklistService->validate(
            $dataTerstruktur,
            $tipeDokumen['type']
        );

        $hasilValidasi = $this->amountValidationService->validateAll(
            $dataTerstruktur,
            $tipeDokumen['type']
        );

        return response()->json([
            'success' => true,
            'ocr_text' => $hasil['raw_text'] ?? '',
            'structured' => $dataTerstruktur,
            'verification' => [
                'document_type' => $tipeDokumen,
                'checklist' => $hasilChecklist,
                'amount_validation' => $hasilValidasi,
            ],
            'method' => 'gemini_vision',
        ]);
    }

    // Proses PDF dengan OCR.space API
    private function prosesOcrSpace(UploadedFile $file)
    {
        // Ekstrak text dari PDF
        $teksOcr = $this->ocrService->extractTextFromImage($file);
        $dataTerstruktur = $this->transactionExtractor->extract($teksOcr);
        $tipeDokumen = $this->documentTypeDetector->detect($teksOcr);

        // Validasi kelengkapan dan perhitungan
        $hasilChecklist = $this->documentChecklistService->validate(
            $dataTerstruktur,
            $tipeDokumen['type']
        );

        $hasilValidasi = $this->amountValidationService->validateAll(
            $dataTerstruktur,
            $tipeDokumen['type']
        );

        return response()->json([
            'success' => true,
            'ocr_text' => $teksOcr,
            'structured' => $dataTerstruktur,
            'verification' => [
                'document_type' => $tipeDokumen,
                'checklist' => $hasilChecklist,
                'amount_validation' => $hasilValidasi,
            ],
            'method' => 'ocr_space',
        ]);
    }

    // Generate jurnal menggunakan AI
    public function generate(Request $request)
    {
        $request->validate([
            'structured' => 'required|array',
            'prompt' => 'nullable|string',
            'unit_id' => 'nullable|integer|exists:units,id',
        ]);

        try {
            $dataTerstruktur = $request->structured;
            $promptTambahan = $request->prompt;
            $unitId = $request->unit_id ?? Auth::user()?->unit_id;

            // Bersihkan data dari karakter kontrol
            $dataTerstruktur = $this->sanitizeForJson($dataTerstruktur);

            // Tambahkan info akun unit ke prompt AI
            if ($unitId) {
                $infoAkun = $this->unitAccountService->formatAccountsForAI($unitId);
                $promptTambahan = $infoAkun . "\n\n" . ($promptTambahan ?? '');
            }

            // Generate jurnal dengan AI
            $inputAi = json_encode($dataTerstruktur, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $hasilJurnal = $this->aiJournalService->generateJournalEntries($inputAi, $promptTambahan);

            if (is_array($hasilJurnal)) {
                $hasilJurnal = $this->sanitizeForJson($hasilJurnal);
            }

            $hasilJurnal['unit_id'] = $unitId;

            // Simpan ke session untuk halaman preview
            $request->session()->put('journal_data', $hasilJurnal);

            return response()->json([
                'success' => true,
                'journal' => $hasilJurnal,
                'table_url' => route('journal.table'),
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            Log::error('Generate jurnal gagal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Bersihkan data dari karakter kontrol yang tidak valid untuk JSON
    private function sanitizeForJson($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeForJson'], $data);
        }

        if (is_string($data)) {
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            return $data;
        }

        return $data;
    }

    // Tampilkan halaman tabel jurnal hasil generate
    public function showTable(Request $request)
    {
        $journalData = $request->session()->get('journal_data');

        if (!$journalData) {
            return redirect()->route('journal.index')
                ->with('error', 'Belum ada data jurnal.');
        }

        // Ambil akun untuk dropdown edit
        $unitId = $journalData['unit_id'] ?? Auth::user()?->unit_id;
        $accounts = [];
        if ($unitId) {
            $accounts = $this->unitAccountService->getAccountsByUnit($unitId);
        }

        return view('journal.table', compact('journalData', 'accounts'));
    }

    // API: Ambil daftar unit dengan info akun
    public function getUnits()
    {
        $units = $this->unitAccountService->getUnitsWithAccounts();
        return response()->json(['units' => $units]);
    }

    // API: Ambil akun berdasarkan unit
    public function getAccountsByUnit(int $unitId)
    {
        $accounts = $this->unitAccountService->getAccountsByUnit($unitId);
        return response()->json(['accounts' => $accounts]);
    }
}
