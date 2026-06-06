<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $query = Journal::with(['lines', 'unit', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $allowed = ['draft', 'verified_unit', 'verified_finance', 'posted', 'rejected', 'void'];
            if (in_array($request->status, $allowed)) {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', (int) $request->unit_id);
        }

        $journals = $query->paginate(20);
        $user = Auth::user();

        return view('journal.list', compact('journals', 'user'));
    }

    public function show(Journal $journal)
    {
        $this->authorizeView($journal);
        $journal->load(['lines', 'unit', 'user', 'verifiedByUnit', 'verifiedByFinance']);
        $user = Auth::user();
        return view('journal.detail', compact('journal', 'user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'journal_data' => 'required|array',
            'journal_data.date' => 'nullable|date',
            'journal_data.vendor' => 'nullable|string|max:255',
            'journal_data.description' => 'nullable|string|max:1000',
            'journal_data.lines' => 'required|array|min:1',
            'journal_data.lines.*.account_code' => 'required|string|max:50',
            'journal_data.lines.*.account_name' => 'required|string|max:255',
            'journal_data.lines.*.debit' => 'required|numeric|min:0',
            'journal_data.lines.*.credit' => 'required|numeric|min:0',
            'unit_id' => 'nullable|integer|exists:units,id',
        ]);

        $data = $request->journal_data;
        $unitId = $request->unit_id ?? Auth::user()?->unit_id;

        $data['vendor'] = isset($data['vendor']) ? strip_tags(trim($data['vendor'])) : null;
        $data['description'] = isset($data['description']) ? strip_tags(trim($data['description'])) : null;

        try {
            DB::beginTransaction();

            $documentPath = session('last_document_path');
            $documentOriginalName = session('last_document_original_name');

            $journal = Journal::create([
                'journal_number' => Journal::generateNumber(),
                'transaction_date' => $data['date'] ?? now()->toDateString(),
                'document_type' => isset($data['document_type']) ? strip_tags($data['document_type']) : null,
                'document_number' => isset($data['document_number']) ? strip_tags($data['document_number']) : null,
                'vendor' => $data['vendor'],
                'description' => $data['description'],
                'total_amount' => $this->hitungTotal($data['lines'] ?? []),
                'currency' => $data['currency'] ?? 'IDR',
                'unit_id' => $unitId,
                'user_id' => Auth::id(),
                'status' => 'draft',
                'raw_data' => $data,
                'document_path' => $documentPath,
                'document_original_name' => $documentOriginalName,
            ]);

            foreach ($data['lines'] ?? [] as $line) {
                JournalLine::create([
                    'journal_id' => $journal->id,
                    'account_code' => strip_tags(trim($line['account_code'] ?? '')),
                    'account_name' => strip_tags(trim($line['account_name'] ?? '')),
                    'description' => isset($line['description']) ? strip_tags(trim($line['description'])) : null,
                    'debit' => max(0, (float) ($line['debit'] ?? 0)),
                    'credit' => max(0, (float) ($line['credit'] ?? 0)),
                ]);
            }

            DB::commit();
            session()->forget(['last_document_path', 'last_document_original_name']);

            Log::info('AUDIT: Journal created', [
                'journal_id' => $journal->id,
                'journal_number' => $journal->journal_number,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jurnal berhasil disimpan',
                'journal_id' => $journal->id,
                'journal_number' => $journal->journal_number,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Journal store failed', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan jurnal'], 500);
        }
    }

    public function verifyUnit(Request $request, Journal $journal)
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        if ($journal->status !== Journal::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Jurnal hanya bisa diverifikasi unit saat status Draft'], 400);
        }

        $journal->update([
            'status' => Journal::STATUS_VERIFIED_UNIT,
            'verified_unit_at' => now(),
            'verified_unit_by' => Auth::id(),
            'verified_unit_notes' => $request->notes ? strip_tags(trim($request->notes)) : null,
        ]);

        Log::info('AUDIT: Journal verified by unit', [
            'journal_id' => $journal->id,
            'user_id' => Auth::id(),
            'user_role' => Auth::user()->role,
        ]);

        return response()->json(['success' => true, 'message' => 'Jurnal berhasil diverifikasi oleh unit']);
    }

    public function verifyFinance(Request $request, Journal $journal)
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        if ($journal->status !== Journal::STATUS_VERIFIED_UNIT) {
            return response()->json(['success' => false, 'message' => 'Jurnal hanya bisa diverifikasi keuangan pusat setelah diverifikasi unit'], 400);
        }

        $journal->update([
            'status' => Journal::STATUS_POSTED,
            'verified_finance_at' => now(),
            'verified_finance_by' => Auth::id(),
            'verified_finance_notes' => $request->notes ? strip_tags(trim($request->notes)) : null,
        ]);

        Log::info('AUDIT: Journal verified by finance and posted', [
            'journal_id' => $journal->id,
            'user_id' => Auth::id(),
            'user_role' => Auth::user()->role,
        ]);

        return response()->json(['success' => true, 'message' => 'Jurnal berhasil diverifikasi dan diposting']);
    }

    public function reject(Request $request, Journal $journal)
    {
        $request->validate(['notes' => 'required|string|max:1000']);

        $allowedStatuses = [Journal::STATUS_DRAFT, Journal::STATUS_VERIFIED_UNIT];
        if (!in_array($journal->status, $allowedStatuses)) {
            return response()->json(['success' => false, 'message' => 'Jurnal dengan status ini tidak bisa ditolak'], 400);
        }

        $user = Auth::user();
        $sanitizedNotes = 'DITOLAK: ' . strip_tags(trim($request->notes));

        $updateData = ['status' => Journal::STATUS_REJECTED];

        if ($user->isVerifikator() || $user->isAdmin()) {
            $updateData['verified_finance_notes'] = $sanitizedNotes;
        } else {
            $updateData['verified_unit_notes'] = $sanitizedNotes;
        }

        $journal->update($updateData);

        Log::warning('AUDIT: Journal rejected', [
            'journal_id' => $journal->id,
            'user_id' => Auth::id(),
            'user_role' => $user->role,
            'reason' => $request->notes,
        ]);

        return response()->json(['success' => true, 'message' => 'Jurnal berhasil ditolak']);
    }

    public function updateStatus(Request $request, Journal $journal)
    {
        $request->validate([
            'status' => 'required|in:draft,verified_unit,verified_finance,posted,rejected,void',
        ]);

        $user = Auth::user();
        if (!$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Hanya admin yang bisa mengubah status manual'], 403);
        }

        $journal->update(['status' => $request->status]);

        Log::warning('AUDIT: Journal status manually changed', [
            'journal_id' => $journal->id,
            'new_status' => $request->status,
            'user_id' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Status jurnal diupdate']);
    }

    public function viewDocument(Journal $journal)
    {
        $this->authorizeView($journal);

        if (!$journal->hasDocument()) {
            abort(404, 'Dokumen tidak ditemukan');
        }

        if (!Storage::disk('public')->exists($journal->document_path)) {
            abort(404, 'File dokumen tidak ditemukan di server');
        }

        $fullPath = Storage::disk('public')->path($journal->document_path);
        $mimeType = mime_content_type($fullPath);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimes)) {
            abort(403, 'Tipe file tidak diizinkan');
        }

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($journal->document_original_name ?? 'document') . '"',
        ]);
    }

    public function downloadDocument(Journal $journal)
    {
        $this->authorizeView($journal);

        if (!$journal->hasDocument()) {
            abort(404, 'Dokumen tidak ditemukan');
        }

        if (!Storage::disk('public')->exists($journal->document_path)) {
            abort(404, 'File dokumen tidak ditemukan di server');
        }

        return Storage::disk('public')->download(
            $journal->document_path,
            $journal->document_original_name ?? basename($journal->document_path)
        );
    }

    public function destroy(Journal $journal)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $journal->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki izin menghapus jurnal ini'], 403);
        }

        if (in_array($journal->status, ['posted', 'verified_finance'])) {
            return response()->json(['success' => false, 'message' => 'Jurnal yang sudah diposting tidak bisa dihapus'], 400);
        }

        if ($journal->hasDocument() && Storage::disk('public')->exists($journal->document_path)) {
            Storage::disk('public')->delete($journal->document_path);
        }

        Log::warning('AUDIT: Journal deleted', [
            'journal_id' => $journal->id,
            'journal_number' => $journal->journal_number,
            'user_id' => Auth::id(),
        ]);

        $journal->delete();

        return response()->json(['success' => true, 'message' => 'Jurnal berhasil dihapus']);
    }

    private function hitungTotal(array $lines): float
    {
        $total = 0;
        foreach ($lines as $line) {
            $total += max(0, (float) ($line['debit'] ?? 0));
        }
        return $total;
    }

    private function authorizeView(Journal $journal): void
    {
        $user = Auth::user();

        if ($user->isAdmin() || $user->isVerifikator()) {
            return;
        }

        if ($journal->unit_id && $user->unit_id && $journal->unit_id !== $user->unit_id) {
            abort(403, 'Anda tidak memiliki izin melihat jurnal unit lain');
        }
    }
}
