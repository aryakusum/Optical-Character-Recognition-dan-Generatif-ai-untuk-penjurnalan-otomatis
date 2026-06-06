<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Jurnal - {{ $journal->journal_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{font-family:'Inter',sans-serif}
        body{background:#f0f2f5}
        .card{border:none;border-radius:12px}
        .journal-header{background:linear-gradient(135deg,#2c3e50,#3498db);border-radius:12px 12px 0 0}
        .amount{font-family:'Consolas',monospace}
        .debit{color:#27ae60} .credit{color:#e74c3c}
        .step{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:8px;margin-bottom:8px;background:#f8f9fa;transition:all .2s}
        .step.active{background:#e3f2fd;border-left:4px solid #2196f3}
        .step.completed{background:#e8f5e9;border-left:4px solid #4caf50}
        .step.rejected{background:#ffebee;border-left:4px solid #f44336}
        .step-number{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0}
        .step.completed .step-number{background:#4caf50;color:#fff}
        .step.active .step-number{background:#2196f3;color:#fff}
        .step.rejected .step-number{background:#f44336;color:#fff}
        .step:not(.completed):not(.active):not(.rejected) .step-number{background:#dee2e6;color:#6c757d}
        .doc-preview{border:2px dashed #dee2e6;border-radius:12px;padding:24px;text-align:center;background:#fafbfc}
        .doc-preview img{max-width:100%;max-height:500px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15)}
        .badge-status{font-size:.8rem;padding:6px 14px;border-radius:20px;font-weight:500}
    </style>
</head>
<body>
    @include('partials.navbar')
    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('journals.list') }}">Jurnal Umum</a></li>
                <li class="breadcrumb-item active">{{ $journal->journal_number }}</li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- Kolom Kiri: Info + Dokumen -->
            <div class="col-lg-5">
                <!-- Header Jurnal -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header journal-header text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 fw-bold">{{ $journal->journal_number }}</h5>
                                <small class="opacity-75">{{ $journal->transaction_date->format('d F Y') }}</small>
                            </div>
                            <span class="badge bg-{{ $journal->status_color }} badge-status">{{ $journal->status_label }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" width="140">Vendor</td><td><strong>{{ $journal->vendor ?? '-' }}</strong></td></tr>
                            <tr><td class="text-muted">Tipe Dokumen</td><td>{{ ucfirst($journal->document_type ?? '-') }}</td></tr>
                            <tr><td class="text-muted">No. Dokumen</td><td>{{ $journal->document_number ?? '-' }}</td></tr>
                            <tr><td class="text-muted">Unit</td><td>{{ $journal->unit?->name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">Dibuat Oleh</td><td>{{ $journal->user?->name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">Keterangan</td><td>{{ $journal->description ?? '-' }}</td></tr>
                        </table>
                    </div>
                </div>

                <!-- Dokumen Upload -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span>Dokumen Pendukung</span>
                        @if($journal->hasDocument())
                        <a href="{{ route('journals.document.download', $journal) }}" class="btn btn-sm btn-outline-primary">Download</a>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($journal->hasDocument())
                            @php
                                $ext = pathinfo($journal->document_original_name ?? $journal->document_path, PATHINFO_EXTENSION);
                                $isImage = in_array(strtolower($ext), ['jpg','jpeg','png','webp','gif']);
                            @endphp
                            <div class="doc-preview">
                                @if($isImage)
                                    <img src="{{ route('journals.document', $journal) }}" alt="Dokumen {{ $journal->journal_number }}">
                                @else
                                    <div class="py-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#e74c3c" viewBox="0 0 16 16">
                                            <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                                        </svg>
                                        <p class="mt-2 mb-1 fw-semibold">{{ $journal->document_original_name ?? 'Dokumen PDF' }}</p>
                                        <a href="{{ route('journals.document', $journal) }}" target="_blank" class="btn btn-sm btn-primary mt-1">Buka PDF</a>
                                    </div>
                                @endif
                            </div>
                            <small class="text-muted d-block mt-2">{{ $journal->document_original_name }}</small>
                        @else
                            <div class="doc-preview">
                                <p class="text-muted mb-0">Tidak ada dokumen yang diupload</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Alur Verifikasi -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold">Alur Verifikasi</div>
                    <div class="card-body">
                        @php
                            $s = $journal->status;
                            $isRejected = $s === 'rejected';
                            $step1Done = in_array($s, ['verified_unit','verified_finance','posted']);
                            $step2Done = in_array($s, ['verified_finance','posted']);
                            $step3Done = $s === 'posted';
                        @endphp
                        <div class="step {{ $step1Done ? 'completed' : ($s === 'draft' && !$isRejected ? 'active' : ($isRejected ? 'rejected' : '')) }}">
                            <div class="step-number">1</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Upload & Draft</div>
                                <div class="text-muted" style="font-size:.75rem">Dokumen diupload oleh staff unit</div>
                                @if($journal->user)
                                <div class="text-muted" style="font-size:.7rem">{{ $journal->user->name }} · {{ $journal->created_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="step {{ $step1Done ? 'completed' : ($isRejected && !$journal->verified_unit_at ? 'rejected' : '') }}">
                            <div class="step-number">2</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Verifikasi Unit</div>
                                <div class="text-muted" style="font-size:.75rem">Diverifikasi oleh verifikator unit kerja</div>
                                @if($journal->verified_unit_at)
                                <div class="text-muted" style="font-size:.7rem">{{ $journal->verifiedByUnit?->name }} · {{ $journal->verified_unit_at->format('d/m/Y H:i') }}</div>
                                @endif
                                @if($journal->verified_unit_notes)
                                <div class="small mt-1 fst-italic">{{ $journal->verified_unit_notes }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="step {{ $step2Done ? 'completed' : ($s === 'verified_unit' ? 'active' : ($isRejected && $journal->verified_unit_at ? 'rejected' : '')) }}">
                            <div class="step-number">3</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Verifikasi Keuangan Pusat</div>
                                <div class="text-muted" style="font-size:.75rem">Diverifikasi oleh keuangan pusat</div>
                                @if($journal->verified_finance_at)
                                <div class="text-muted" style="font-size:.7rem">{{ $journal->verifiedByFinance?->name }} · {{ $journal->verified_finance_at->format('d/m/Y H:i') }}</div>
                                @endif
                                @if($journal->verified_finance_notes)
                                <div class="small mt-1 fst-italic">{{ $journal->verified_finance_notes }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="step {{ $step3Done ? 'completed' : '' }}">
                            <div class="step-number">4</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Posted</div>
                                <div class="text-muted" style="font-size:.75rem">Jurnal resmi tercatat di sistem</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Detail Lines + Aksi -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold">Detail Jurnal</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Kode Akun</th>
                                    <th>Nama Akun</th>
                                    <th>Keterangan</th>
                                    <th class="text-end" width="140">Debit</th>
                                    <th class="text-end" width="140">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($journal->lines as $line)
                                <tr>
                                    <td class="fw-bold">{{ $line->account_code }}</td>
                                    <td>{{ $line->account_name }}</td>
                                    <td class="text-muted">{{ $line->description ?? '-' }}</td>
                                    <td class="text-end amount {{ $line->debit > 0 ? 'debit fw-bold' : '' }}">
                                        {{ $line->debit > 0 ? 'Rp ' . number_format($line->debit, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="text-end amount {{ $line->credit > 0 ? 'credit fw-bold' : '' }}">
                                        {{ $line->credit > 0 ? 'Rp ' . number_format($line->credit, 0, ',', '.') : '-' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="3" class="text-end">TOTAL</td>
                                    <td class="text-end amount debit">Rp {{ number_format($journal->total_debit, 0, ',', '.') }}</td>
                                    <td class="text-end amount credit">Rp {{ number_format($journal->total_credit, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        @if($journal->is_balanced)
                                        <span class="text-success fw-semibold">✓ Balance (Debit = Credit)</span>
                                        @else
                                        <span class="text-danger fw-semibold">✗ Tidak Balance!</span>
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">Aksi</div>
                    <div class="card-body">
                        @php $user = Auth::user(); @endphp

                        {{-- Draft -> Verifikasi Unit (staff unit / admin) --}}
                        @if($journal->status === 'draft')
                            <div class="d-flex gap-2 mb-3">
                                <button class="btn btn-info text-white flex-grow-1" onclick="showVerifyModal('unit')">
                                    ✓ Verifikasi Unit
                                </button>
                                <button class="btn btn-danger" onclick="showRejectModal()">Tolak</button>
                            </div>
                            <small class="text-muted">Periksa dokumen dan data jurnal, lalu verifikasi atau tolak.</small>
                        @endif

                        {{-- Verified Unit --}}
                        @if($journal->status === 'verified_unit')
                            <div class="alert alert-info mb-0">
                                <strong>Menunggu Verifikasi Keuangan Pusat.</strong> Jurnal ini sedang menunggu persetujuan dari Keuangan Pusat.
                            </div>
                        @endif

                        {{-- Posted / Rejected --}}
                        @if($journal->status === 'posted')
                            <div class="alert alert-success mb-0">
                                <strong>✓ Jurnal sudah diposting.</strong> Jurnal ini sudah melewati semua tahap verifikasi.
                            </div>
                        @elseif($journal->status === 'rejected')
                            <div class="alert alert-danger mb-0">
                                <strong>✗ Jurnal ditolak.</strong> Silakan periksa catatan penolakan pada alur verifikasi.
                            </div>
                        @endif

                        <div class="mt-3">
                            <a href="{{ route('journals.list') }}" class="btn btn-secondary">← Kembali</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Verifikasi -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verifyModalTitle">Verifikasi Jurnal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea id="verifyNotes" class="form-control" rows="3" placeholder="Tambahkan catatan verifikasi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="verifyConfirmBtn" onclick="submitVerify()">Verifikasi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tolak -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Jurnal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea id="rejectNotes" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" onclick="submitReject()">Tolak Jurnal</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF = "{{ csrf_token() }}";
        const VERIFY_UNIT_URL = "{{ route('journals.verify-unit', $journal) }}";
        const VERIFY_FINANCE_URL = "{{ route('journals.verify-finance', $journal) }}";
        const REJECT_URL = "{{ route('journals.reject', $journal) }}";
        let currentVerifyType = '';

        function showVerifyModal(type) {
            currentVerifyType = type;
            const title = type === 'unit' ? 'Verifikasi Unit' : 'Verifikasi Keuangan Pusat';
            document.getElementById('verifyModalTitle').textContent = title;
            new bootstrap.Modal(document.getElementById('verifyModal')).show();
        }

        function showRejectModal() {
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        async function submitVerify() {
            const url = currentVerifyType === 'unit' ? VERIFY_UNIT_URL : VERIFY_FINANCE_URL;
            const notes = document.getElementById('verifyNotes').value;
            try {
                const r = await fetch(url, {
                    method:'PUT',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
                    body: JSON.stringify({notes})
                });
                const d = await r.json();
                if(d.success) location.reload();
                else alert(d.message);
            } catch(e) { alert('Error: '+e.message); }
        }

        async function submitReject() {
            const notes = document.getElementById('rejectNotes').value;
            if(!notes.trim()) { alert('Alasan penolakan wajib diisi'); return; }
            try {
                const r = await fetch(REJECT_URL, {
                    method:'PUT',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
                    body: JSON.stringify({notes})
                });
                const d = await r.json();
                if(d.success) location.reload();
                else alert(d.message);
            } catch(e) { alert('Error: '+e.message); }
        }
    </script>
</body>
</html>