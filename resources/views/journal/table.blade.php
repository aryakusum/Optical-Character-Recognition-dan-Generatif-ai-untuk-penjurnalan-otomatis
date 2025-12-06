<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Jurnal Umum - Edit</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		.table th, .table td { vertical-align: middle; }
		.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
		.total-row { background-color: #f8f9fa; font-weight: bold; }
		.debit { color: #dc3545; }
		.credit { color: #198754; }
	</style>
</head>
<body>
	<nav class="navbar navbar-expand-md navbar-dark" style="background:#e34b4b">
		<div class="container">
			<a class="navbar-brand fw-semibold" href="#">Nama web</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div id="navMenu" class="collapse navbar-collapse justify-content-end">
				<ul class="navbar-nav ms-auto text-end text-md-start">
					<li class="nav-item"><a class="nav-link text-white" href="{{ route('journal.index') }}">Upload</a></li>
					<li class="nav-item"><a class="nav-link text-white" href="#">username</a></li>
				</ul>
			</div>
		</div>
	</nav>

	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h2 class="fw-bold mb-1">Jurnal Umum</h2>
				<p class="text-muted mb-0">{{ $journalData['date'] ?? 'N/A' }} </p>
			</div>
			<div>
				<button class="btn btn-success" onclick="saveJournal()">Simpan Jurnal</button>
				<button class="btn btn-outline-secondary" onclick="addRow()">Tambah Baris</button>
			</div>
		</div>

		<div class="card shadow-sm">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-bordered" id="journalTable">
						<thead class="table-light">
							<tr>
								<th width="15%">Kode Akun</th>
								<th width="35%">Nama Akun</th>
								<th width="20%">Debit</th>
								<th width="20%">Kredit</th>
								<th width="10%">Aksi</th>
							</tr>
						</thead>
						<tbody id="journalBody">
							@foreach($journalData['lines'] ?? [] as $index => $line)
							<tr data-index="{{ $index }}">
								<td>
									<input type="text" class="form-control form-control-sm" value="{{ $line['account_code'] ?? '' }}" name="account_code">
								</td>
								<td>
									<input type="text" class="form-control form-control-sm" value="{{ $line['account_name'] ?? '' }}" name="account_name">
								</td>
								<td>
									<input type="number" class="form-control form-control-sm debit-input" value="{{ $line['debit'] ?? 0 }}" name="debit" step="0.01">
								</td>
								<td>
									<input type="number" class="form-control form-control-sm credit-input" value="{{ $line['credit'] ?? 0 }}" name="credit" step="0.01">
								</td>
								<td>
									<button class="btn btn-danger btn-sm" onclick="removeRow(this)">Hapus</button>
								</td>
							</tr>
							@endforeach
						</tbody>
						<tfoot class="total-row">
							<tr>
								<td colspan="2" class="text-end"><strong>Total:</strong></td>
								<td><span id="totalDebit" class="debit">0</span></td>
								<td><span id="totalCredit" class="credit">0</span></td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>

		<div class="mt-3">
			<div class="alert alert-info">
				<strong>Info:</strong> Pastikan total debit sama dengan total kredit untuk jurnal yang seimbang.
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		function updateTotals() {
			let totalDebit = 0;
			let totalCredit = 0;
			
			document.querySelectorAll('.debit-input').forEach(input => {
				totalDebit += parseFloat(input.value) || 0;
			});
			
			document.querySelectorAll('.credit-input').forEach(input => {
				totalCredit += parseFloat(input.value) || 0;
			});
			
			document.getElementById('totalDebit').textContent = totalDebit.toLocaleString('id-ID');
			document.getElementById('totalCredit').textContent = totalCredit.toLocaleString('id-ID');
			
			// Check if balanced
			const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01;
			document.getElementById('totalDebit').parentElement.style.backgroundColor = isBalanced ? '#d1edff' : '#ffebee';
			document.getElementById('totalCredit').parentElement.style.backgroundColor = isBalanced ? '#d1edff' : '#ffebee';
		}

		function addRow() {
			const tbody = document.getElementById('journalBody');
			const index = tbody.children.length;
			const row = document.createElement('tr');
			row.setAttribute('data-index', index);
			row.innerHTML = `
				<td><input type="text" class="form-control form-control-sm" name="account_code"></td>
				<td><input type="text" class="form-control form-control-sm" name="account_name"></td>
				<td><input type="number" class="form-control form-control-sm debit-input" name="debit" step="0.01" onchange="updateTotals()"></td>
				<td><input type="number" class="form-control form-control-sm credit-input" name="credit" step="0.01" onchange="updateTotals()"></td>
				<td><button class="btn btn-danger btn-sm" onclick="removeRow(this)">Hapus</button></td>
			`;
			tbody.appendChild(row);
		}

		function removeRow(button) {
			button.closest('tr').remove();
			updateTotals();
		}

		function saveJournal() {
			const rows = document.querySelectorAll('#journalBody tr');
			const journalData = {
				date: '{{ $journalData["date"] ?? "" }}',
				vendor: '{{ $journalData["vendor"] ?? "" }}',
				description: '{{ $journalData["description"] ?? "" }}',
				currency: '{{ $journalData["currency"] ?? "IDR" }}',
				lines: []
			};

			rows.forEach(row => {
				const accountCode = row.querySelector('input[name="account_code"]').value;
				const accountName = row.querySelector('input[name="account_name"]').value;
				const debit = parseFloat(row.querySelector('input[name="debit"]').value) || 0;
				const credit = parseFloat(row.querySelector('input[name="credit"]').value) || 0;

				if (accountCode && accountName && (debit > 0 || credit > 0)) {
					journalData.lines.push({
						account_code: accountCode,
						account_name: accountName,
						debit: debit,
						credit: credit
					});
				}
			});

			// Check if balanced
			const totalDebit = journalData.lines.reduce((sum, line) => sum + line.debit, 0);
			const totalCredit = journalData.lines.reduce((sum, line) => sum + line.credit, 0);
			
			if (Math.abs(totalDebit - totalCredit) > 0.01) {
				alert('Jurnal tidak seimbang! Total debit: ' + totalDebit + ', Total kredit: ' + totalCredit);
				return;
			}

			// Save to session or send to server
			console.log('Journal data:', journalData);
			alert('Jurnal berhasil disimpan!');
		}

		// Initialize totals on page load
		document.addEventListener('DOMContentLoaded', function() {
			updateTotals();
			
			// Add change listeners to existing inputs
			document.querySelectorAll('.debit-input, .credit-input').forEach(input => {
				input.addEventListener('change', updateTotals);
			});
		});
	</script>
</body>
</html>
