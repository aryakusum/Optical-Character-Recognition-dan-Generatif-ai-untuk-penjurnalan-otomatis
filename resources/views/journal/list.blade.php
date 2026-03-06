<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Umum - Sistem Verifikasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
        }

        .card {
            border: none;
            border-radius: 8px;
        }

        .table th {
            background-color: #f8f9fa;
        }

        .badge-draft {
            background-color: #f39c12;
            color: white;
        }

        .badge-posted {
            background-color: #27ae60;
            color: white;
        }

        .badge-void {
            background-color: #e74c3c;
            color: white;
        }

        .amount {
            font-family: 'Consolas', monospace;
        }
    </style>
</head>

<body>
    @include('partials.navbar')

    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Jurnal Umum</h2>
            <a href="{{ route('journal.index') }}" class="btn btn-primary">Upload Dokumen Baru</a>
        </div>

        <!-- Filter -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="posted" {{ request('status') == 'posted' ? 'selected' : '' }}>Posted</option>
                            <option value="void" {{ request('status') == 'void' ? 'selected' : '' }}>Void</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-secondary w-100">Filter</button>
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
                            <td>{{ Str::limit($journal->description, 50) }}</td>
                            <td class="text-end amount">Rp {{ number_format($journal->total_amount, 0, ',', '.') }}</td>
                            <td>
                                @if($journal->status == 'posted')
                                <span class="badge badge-posted">Posted</span>
                                @elseif($journal->status == 'void')
                                <span class="badge badge-void">Void</span>
                                @else
                                <span class="badge badge-draft">Draft</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('journals.show', $journal) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
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