<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Umum - Sistem Verifikasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f0f2f5;
        }

        .card {
            border: none;
            border-radius: 12px;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }

        .badge-draft { background-color: #f39c12; color: white; }
        .badge-verified_unit { background-color: #17a2b8; color: white; }
        .badge-verified_finance { background-color: #3498db; color: white; }
        .badge-posted { background-color: #27ae60; color: white; }
        .badge-rejected { background-color: #e74c3c; color: white; }
        .badge-void { background-color: #95a5a6; color: white; }

        .amount {
            font-family: 'Consolas', monospace;
        }

        .doc-icon {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #3498db;
            font-size: 0.8rem;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .page-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 24px;
        }

        .page-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .page-header p {
            margin: 4px 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .stats-row {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            flex: 1;
            background: white;
            border-radius: 10px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body>
    @include('partials.navbar')

    <div class="container py-4">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>Jurnal Umum</h2>
                    <p>Kelola dan verifikasi jurnal transaksi keuangan</p>
                </div>
                <a href="{{ route('journal.index') }}" class="btn btn-light fw-semibold">
                    + Upload Dokumen Baru
                </a>
            </div>
        </div>

        <!-- Statistik -->
        @php
            $allJournals = \App\Models\Journal::all();
            $countDraft = $allJournals->where('status', 'draft')->count();
            $countVerifiedUnit = $allJournals->where('status', 'verified_unit')->count();
            $countPosted = $allJournals->where('status', 'posted')->count();
            $countRejected = $allJournals->where('status', 'rejected')->count();
        @endphp
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value text-warning">{{ $countDraft }}</div>
                <div class="stat-label">Draft</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-info">{{ $countVerifiedUnit }}</div>
                <div class="stat-label">Verifikasi Unit</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-success">{{ $countPosted }}</div>
                <div class="stat-label">Posted</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-danger">{{ $countRejected }}</div>
                <div class="stat-label">Ditolak</div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="verified_unit" {{ request('status') == 'verified_unit' ? 'selected' : '' }}>Diverifikasi Unit</option>
                            <option value="verified_finance" {{ request('status') == 'verified_finance' ? 'selected' : '' }}>Diverifikasi Keuangan</option>
                            <option value="posted" {{ request('status') == 'posted' ? 'selected' : '' }}>Posted</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Ditolak</option>
                            <option value="void" {{ request('status') == 'void' ? 'selected' : '' }}>Void</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Dari Tanggal</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Sampai Tanggal</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Jurnal -->
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No. Jurnal</th>
                            <th>Tanggal</th>
                            <th>Vendor</th>
                            <th>Keterangan</th>
                            <th class="text-end">Total</th>
                            <th>Dokumen</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($journals as $journal)
                        <tr>
                            <td>
                                <a href="{{ route('journals.show', $journal) }}" class="fw-bold text-decoration-none">
                                    {{ $journal->journal_number }}
                                </a>
                            </td>
                            <td>{{ $journal->transaction_date->format('d/m/Y') }}</td>
                            <td>{{ $journal->vendor ?? '-' }}</td>
                            <td>{{ Str::limit($journal->description, 40) }}</td>
                            <td class="text-end amount">Rp {{ number_format($journal->total_amount, 0, ',', '.') }}</td>
                            <td>
                                @if($journal->hasDocument())
                                    <a href="{{ route('journals.document', $journal) }}" target="_blank" class="doc-icon text-decoration-none" title="Lihat Dokumen">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                                        </svg>
                                        Ada
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge status-badge badge-{{ $journal->status }}">
                                    {{ $journal->status_label }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('journals.show', $journal) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Belum ada jurnal. <a href="{{ route('journal.index') }}">Upload dokumen</a> untuk membuat jurnal.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-3">
            {{ $journals->withQueryString()->links() }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>