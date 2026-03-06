<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Controller untuk mengelola Jurnal Umum
class JournalController extends Controller
{
    // Tampilkan daftar jurnal dengan filter
    public function index(Request $request)
    {
        $query = Journal::with(['lines', 'unit', 'user'])
            ->orderBy('created_at', 'desc');

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan tanggal
        if ($request->filled('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        }

        // Filter berdasarkan unit
        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        $journals = $query->paginate(20);
        $user = Auth::user();

        return view('journal.list', compact('journals', 'user'));
    }

    // Tampilkan detail satu jurnal
    public function show(Journal $journal)
    {
        $journal->load(['lines', 'unit', 'user']);
        return view('journal.detail', compact('journal'));
    }

    // Simpan jurnal baru dari hasil AI
    public function store(Request $request)
    {
        $request->validate([
            'journal_data' => 'required|array',
            'unit_id' => 'nullable|integer|exists:units,id',
        ]);

        $data = $request->journal_data;
        $unitId = $request->unit_id ?? Auth::user()?->unit_id;

        try {
            DB::beginTransaction();

            // Buat header jurnal
            $journal = Journal::create([
                'journal_number' => Journal::generateNumber(),
                'transaction_date' => $data['date'] ?? now()->toDateString(),
                'document_type' => $data['document_type'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'description' => $data['description'] ?? null,
                'total_amount' => $this->hitungTotal($data['lines'] ?? []),
                'currency' => $data['currency'] ?? 'IDR',
                'unit_id' => $unitId,
                'user_id' => Auth::id(),
                'status' => 'draft',
                'raw_data' => $data,
            ]);

            // Buat detail jurnal (lines)
            foreach ($data['lines'] ?? [] as $line) {
                JournalLine::create([
                    'journal_id' => $journal->id,
                    'account_code' => $line['account_code'] ?? '',
                    'account_name' => $line['account_name'] ?? '',
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Jurnal berhasil disimpan',
                'journal_id' => $journal->id,
                'journal_number' => $journal->journal_number,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Update status jurnal (draft -> posted -> void)
    public function updateStatus(Request $request, Journal $journal)
    {
        $request->validate([
            'status' => 'required|in:draft,posted,void',
        ]);

        $journal->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status jurnal diupdate',
        ]);
    }

    // Hapus jurnal (hanya draft yang bisa dihapus)
    public function destroy(Journal $journal)
    {
        if ($journal->status === 'posted') {
            return response()->json([
                'success' => false,
                'message' => 'Jurnal yang sudah diposting tidak bisa dihapus',
            ], 400);
        }

        $journal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jurnal berhasil dihapus',
        ]);
    }

    // Hitung total dari journal lines
    private function hitungTotal(array $lines): float
    {
        $total = 0;
        foreach ($lines as $line) {
            $total += (float) ($line['debit'] ?? 0);
        }
        return $total;
    }
}
