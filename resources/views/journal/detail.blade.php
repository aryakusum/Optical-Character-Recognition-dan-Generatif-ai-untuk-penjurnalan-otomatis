<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Jurnal - {{ $journal->journal_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
        }

        .card {
            border: none;
            border-radius: 8px;
        }

        .journal-header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
        }

        .amount {
            font-family: 'Consolas', monospace;
        }

        .debit {
            color: #27ae60;
        }

        .credit {
            color: #e74c3c;
        }
    </style>
</head>

<body>
    @include('partials.navbar')

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('journals.list') }}">Jurnal Umum</a></li>
                <li class="breadcrumb-item active">{{ $journal->journal_number }}</li>
            </ol>
        </nav>

        <!-- Header Jurnal -->
        <div class="card shadow-sm mb-4">
            <div class="card-header journal-header text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">{{ $journal->journal_number }}</h4>
                        <small>{{ $journal->transaction_date->format('d F Y') }}</small>
                    </div>
                    <div>
                        @if($journal->status == 'posted')
                        <span class="badge bg-success fs-6">Posted</span>
                        @elseif($journal->status == 'void')
                        <span class="badge bg-danger fs-6">Void</span>
                        @else
                        <span class="badge bg-warning text-dark fs-6">Draft</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" width="150">Vendor</td>
                                <td><strong>{{ $journal->vendor ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tipe Dokumen</td>
                                <td>{{ ucfirst($journal->document_type ?? '-') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">No. Dokumen</td>
                                <td>{{ $journal->document_number ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" width="150">Unit</td>
                                <td>{{ $journal->unit?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Dibuat Oleh</td>
                                <td>{{ $journal->user?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Keterangan</td>
                                <td>{{ $journal->description ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Lines -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Detail Jurnal</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="150">Kode Akun</th>
                            <th>Nama Akun</th>
                            <th>Keterangan</th>
                            <th class="text-end" width="150">Debit</th>
                            <th class="text-end" width="150">Credit</th>
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
                                <span class="text-success">Balance (Debit = Credit)</span>
                                @else
                                <span class="text-danger">Tidak Balance!</span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2">
            <a href="{{ route('journals.list') }}" class="btn btn-secondary">Kembali</a>
            @if($journal->status == 'draft')
            <button class="btn btn-success" onclick="updateStatus('posted')">Post Jurnal</button>
            <button class="btn btn-danger" onclick="updateStatus('void')">Void</button>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const STATUS_URL = "{{ route('journals.status', $journal) }}";
        const CSRF_TOKEN = "{{ csrf_token() }}";

        function updateStatus(status) {
            if (!confirm('Yakin ubah status ke ' + status + '?')) return;

            fetch(STATUS_URL, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        status: status
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message);
                })
                .catch(e => alert('Error: ' + e.message));
        }
    </script>
</body>

</html>