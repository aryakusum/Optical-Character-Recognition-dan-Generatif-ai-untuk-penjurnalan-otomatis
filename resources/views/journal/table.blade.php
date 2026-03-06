<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Review & Edit Jurnal - Sistem Verifikasi</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- Tambahkan Select2 untuk pencarian akun yang lebih mudah -->
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

		.amount {
			font-family: 'Consolas', monospace;
		}

		.total-row {
			background-color: #e9ecef;
			font-weight: bold;
		}

		.debit {
			color: #27ae60;
		}

		.credit {
			color: #e74c3c;
		}

		.item-valid {
			color: #27ae60;
			font-weight: bold;
		}

		.item-invalid {
			color: #e74c3c;
			font-weight: bold;
		}

		.verification-ok {
			background-color: #d4edda;
		}

		.verification-warning {
			background-color: #fff3cd;
		}

		.verification-error {
			background-color: #f8d7da;
		}

		/* Custom styles for select2 in table */
		.select2-container .select2-selection--single {
			height: 31px;
			padding: 2px;
			font-size: 0.875rem;
		}
	</style>
</head>

<body>
	@include('partials.navbar')

	<div class="container py-4">
		<h2 class="fw-bold mb-4">Review & Edit Jurnal</h2>

		<div class="row g-4">
			<!-- Panel Kiri: Info Dokumen & Items -->
			<div class="col-lg-5">
				<!-- Info Dokumen -->
				<div class="card shadow-sm mb-3">
					<div class="card-header bg-white fw-bold">Informasi Dokumen</div>
					<div class="card-body">
						<div class="row g-2">
							<div class="col-6">
								<label class="form-label small text-muted">Tanggal</label>
								<input type="date" class="form-control form-control-sm" id="journalDate"
									value="{{ $journalData['date'] ?? now()->format('Y-m-d') }}">
							</div>
							<div class="col-6">
								<label class="form-label small text-muted">Vendor</label>
								<input type="text" class="form-control form-control-sm" id="journalVendor"
									value="{{ $journalData['vendor'] ?? '' }}">
							</div>
							<div class="col-12">
								<label class="form-label small text-muted">Keterangan</label>
								<input type="text" class="form-control form-control-sm" id="journalActivity"
									value="{{ $journalData['activity_detail'] ?? $journalData['description'] ?? '' }}">
							</div>
						</div>
					</div>
				</div>

				<!-- Daftar Item (Verifikasi Perhitungan) -->
				<div class="card shadow-sm mb-3">
					<div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
						<span>Daftar Item (Verifikasi)</span>
						<small class="text-muted fw-normal">Dapat diedit</small>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<thead class="table-light">
									<tr>
										<th>Item</th>
										<th class="text-center" width="15%">Qty</th>
										<th class="text-end" width="25%">Harga</th>
										<th class="text-end" width="25%">Subtotal</th>
										<th class="text-center" width="10%">Status</th>
									</tr>
								</thead>
								<tbody id="itemsBody">
									@php
									$items = $journalData['items'] ?? [];
									// Konversi ke array jika string JSON (safety check)
									if (is_string($items)) $items = json_decode($items, true) ?? [];

									$calculatedTotal = 0;
									@endphp

									@forelse($items as $index => $item)
									@php
									$qty = (float) ($item['qty'] ?? $item['quantity'] ?? 1);
									$harga = (float) ($item['harga_satuan'] ?? $item['price'] ?? 0);
									$subtotal = isset($item['subtotal']) ? (float)$item['subtotal'] : ($qty * $harga);
									$calculatedTotal += $subtotal;
									@endphp
									<tr class="item-row">
										<td>
											<input type="text" class="form-control form-control-sm border-0 bg-transparent p-0 item-name"
												value="{{ $item['nama'] ?? $item['name'] ?? '-' }}">
										</td>
										<td>
											<input type="number" class="form-control form-control-sm text-center item-qty p-1"
												value="{{ $qty }}" step="0.01" min="0" onchange="recalcItem(this)">
										</td>
										<td>
											<input type="number" class="form-control form-control-sm text-end item-price p-1"
												value="{{ $harga }}" step="1" min="0" onchange="recalcItem(this)">
										</td>
										<td class="text-end amount small align-middle item-subtotal">{{ number_format($subtotal, 0, ',', '.') }}</td>
										<td class="text-center align-middle item-status"></td> <!-- Status diisi via JS -->
									</tr>
									@empty
									<tr>
										<td colspan="5" class="text-center text-muted py-3">Tidak ada item</td>
									</tr>
									@endforelse
								</tbody>
								<tfoot class="table-light">
									<tr class="fw-bold">
										<td colspan="3" class="text-end">Total Items:</td>
										<td class="text-end amount" id="totalCalculated">{{ number_format($calculatedTotal, 0, ',', '.') }}</td>
										<td></td>
									</tr>

									@php
									$declaredTotal = (float) ($journalData['total'] ?? $journalData['total_pembayaran'] ?? 0);
									@endphp

									<tr id="docTotalRow">
										<td colspan="3" class="text-end">Total Dokumen:</td>
										<td class="text-end p-0">
											<input type="number" class="form-control form-control-sm text-end fw-bold bg-transparent border-0"
												id="declaredTotal" value="{{ $declaredTotal }}"
												onchange="checkTotalMatch()">
										</td>
										<td class="text-center align-middle" id="totalStatus"></td>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
				</div>
			</div>

			<!-- Panel Kanan: Tabel Jurnal -->
			<div class="col-lg-7">
				<div class="card shadow-sm">
					<div class="card-header bg-white d-flex justify-content-between align-items-center">
						<div>
							<strong>Jurnal Entry</strong>
							@if(isset($accounts) && count($accounts) > 0)
							<small class="text-success ms-2"><i class="bi bi-check-circle"></i> Akun Unit Tersedia</small>
							@else
							<small class="text-warning ms-2"><i class="bi bi-exclamation-triangle"></i> Akun Unit Tidak Ditemukan</small>
							@endif
						</div>
						<button class="btn btn-sm btn-outline-secondary" onclick="tambahBaris()">+ Tambah Baris</button>
					</div>
					<div class="card-body p-0">
						<table class="table table-bordered mb-0">
							<thead class="table-light">
								<tr>
									<th width="40%">Akun (Kode - Nama)</th>
									<th width="20%">Debit</th>
									<th width="20%">Kredit</th>
									<th width="10%">Aksi</th>
								</tr>
							</thead>
							<tbody id="journalBody">
								@foreach($journalData['lines'] ?? [] as $line)
								<tr>
									<td>
										<select class="form-select form-select-sm account-select" onchange="updateAccountName(this)" style="width: 100%">
											<option value="">-- Pilih Akun --</option>
											@if(isset($accounts))
											@foreach($accounts as $acc)
											@php
											// Coba match dengan code saja atau code di dalam string
											$isSelected = ($line['account_code'] == $acc['code'] || str_contains($line['account_code'], $acc['code']));
											@endphp
											<option value="{{ $acc['code'] }}" data-name="{{ $acc['name'] }}" {{ $isSelected ? 'selected' : '' }}>
												{{ $acc['code'] }} - {{ $acc['name'] }}
											</option>
											@endforeach
											@endif
										</select>
										<input type="hidden" class="account-code" value="{{ $line['account_code'] ?? '' }}">
										<input type="hidden" class="account-name" value="{{ $line['account_name'] ?? '' }}">

										<!-- Fallback untuk manual input jika akun tidak ada di list -->
										<div class="manual-input mt-1" style="display: none;">
											<input type="text" class="form-control form-control-sm mb-1 account-code-manual" placeholder="Kode" value="{{ $line['account_code'] ?? '' }}">
											<input type="text" class="form-control form-control-sm account-name-manual" placeholder="Nama" value="{{ $line['account_name'] ?? '' }}">
										</div>
									</td>
									<td><input type="number" class="form-control form-control-sm debit-input" value="{{ $line['debit'] ?? 0 }}" step="0.01" onchange="hitungTotal()"></td>
									<td><input type="number" class="form-control form-control-sm credit-input" value="{{ $line['credit'] ?? 0 }}" step="0.01" onchange="hitungTotal()"></td>
									<td><button class="btn btn-danger btn-sm w-100" onclick="hapusBaris(this)">Hapus</button></td>
								</tr>
								@endforeach
							</tbody>
							<tfoot class="total-row">
								<tr>
									<td class="text-end">TOTAL:</td>
									<td class="text-end"><span id="totalDebit" class="amount debit">0</span></td>
									<td class="text-end"><span id="totalCredit" class="amount credit">0</span></td>
									<td id="balanceStatus" class="text-center"></td>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>

				<!-- Tombol Aksi -->
				<div class="d-flex gap-2 mt-3">
					<button class="btn btn-success btn-lg flex-grow-1" onclick="simpanJurnal()">Simpan Jurnal</button>
					<a href="{{ route('journal.index') }}" class="btn btn-outline-secondary">Batal</a>
				</div>
			</div>
		</div>
	</div>

	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	<script>
		const SAVE_URL = "{{ route('journals.store') }}";
		const CSRF_TOKEN = "{{ csrf_token() }}";

		// Data akun dari server untuk baris baru
		const ACCOUNTS_DATA = JSON.parse('{!! json_encode($accounts ?? [], JSON_HEX_APOS | JSON_HEX_QUOT) !!}');

		$(document).ready(function() {
			// Inisialisasi Select2 untuk semua dropdown akun
			initSelect2();

			// Hitung total awal
			hitungItemInitial();
			hitungTotal();
		});

		function initSelect2() {
			$('.account-select').select2({
				placeholder: "-- Pilih Akun --",
				allowClear: true,
				width: '100%'
			});
		}

		/* ================= ITEM FUNCTIONS ================= */

		function hitungItemInitial() {
			// Trigger recalc untuk setiap baris untuk set status awal
			document.querySelectorAll('.item-row').forEach(row => {
				recalcItem(row.querySelector('.item-qty'), false);
			});
			recalcItemsTotal();
		}

		function recalcItem(input, updateGlobal = true) {
			const row = input.closest('tr');
			const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
			const price = parseFloat(row.querySelector('.item-price').value) || 0;
			const subtotal = qty * price;

			// Update tampilan subtotal
			row.querySelector('.item-subtotal').textContent = formatRupiah(subtotal);

			// Validasi baris (cek kalkulasi) - sebenarnya pasti benar karena dikali di JS, 
			// tapi bisa dipakai untuk menandai outlier jika ada expected value dari OCR (simpan di dataset jika perlu)
			const statusCell = row.querySelector('.item-status');

			// Logic: Jika subtotal > 0 dianggap OK
			if (subtotal >= 0) {
				statusCell.innerHTML = '<span class="item-valid">[OK]</span>';
			} else {
				statusCell.innerHTML = '<span class="item-invalid">[Err]</span>';
			}

			if (updateGlobal) recalcItemsTotal();
		}

		function recalcItemsTotal() {
			let total = 0;
			document.querySelectorAll('.item-row').forEach(row => {
				const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
				const price = parseFloat(row.querySelector('.item-price').value) || 0;
				total += (qty * price);
			});

			document.getElementById('totalCalculated').textContent = formatRupiah(total);
			checkTotalMatch(total);
		}

		function checkTotalMatch(calcTotal = null) {
			if (calcTotal === null) {
				// Ambil dari text content, remove dots
				const text = document.getElementById('totalCalculated').textContent;
				calcTotal = parseFloat(text.replace(/\./g, '').replace(',', '.')) || 0;
			}

			const declaredTotal = parseFloat(document.getElementById('declaredTotal').value) || 0;
			const diff = Math.abs(declaredTotal - calcTotal);
			const row = document.getElementById('docTotalRow');
			const status = document.getElementById('totalStatus');

			if (diff < 100) { // Toleransi kecil
				row.className = 'verification-ok';
				status.innerHTML = '<span class="item-valid">[OK]</span>';
			} else {
				row.className = 'verification-warning';
				status.innerHTML = '<span class="item-invalid" title="Selisih: ' + formatRupiah(diff) + '">[Diff]</span>';
			}
		}

		/* ================= JOURNAL FUNCTIONS ================= */

		function updateAccountName(select) {
			const option = select.options[select.selectedIndex];
			const row = select.closest('tr');
			const code = select.value;
			const name = option.getAttribute('data-name') || '';

			// Update hidden inputs
			row.querySelector('.account-code').value = code;
			row.querySelector('.account-name').value = name;
		}

		// Hitung total debit dan kredit
		function hitungTotal() {
			let totalDebit = 0,
				totalCredit = 0;
			document.querySelectorAll('.debit-input').forEach(i => totalDebit += parseFloat(i.value) || 0);
			document.querySelectorAll('.credit-input').forEach(i => totalCredit += parseFloat(i.value) || 0);

			document.getElementById('totalDebit').textContent = formatRupiah(totalDebit);
			document.getElementById('totalCredit').textContent = formatRupiah(totalCredit);

			const balance = Math.abs(totalDebit - totalCredit) < 0.01;
			const statusCell = document.getElementById('balanceStatus');

			if (balance && (totalDebit > 0)) {
				statusCell.innerHTML = '<span class="text-success fw-bold">[OK]</span>';
				statusCell.style.backgroundColor = '#d4edda';
			} else {
				statusCell.innerHTML = '<span class="text-danger fw-bold">[X]</span>';
				statusCell.style.backgroundColor = '#f8d7da';
			}
		}

		function formatRupiah(angka) {
			return new Intl.NumberFormat('id-ID').format(angka);
		}

		// Tambah baris baru jurnal
		function tambahBaris() {
			const tbody = document.getElementById('journalBody');

			// Buat options untuk select
			let optionsHtml = '<option value="">-- Pilih Akun --</option>';
			ACCOUNTS_DATA.forEach(acc => {
				optionsHtml += `<option value="${acc.code}" data-name="${acc.name}">${acc.code} - ${acc.name}</option>`;
			});

			const row = document.createElement('tr');
			row.innerHTML = `
                <td>
                    <select class="form-select form-select-sm account-select" onchange="updateAccountName(this)" style="width: 100%">
                        ${optionsHtml}
                    </select>
                    <input type="hidden" class="account-code">
                    <input type="hidden" class="account-name">
                </td>
                <td><input type="number" class="form-control form-control-sm debit-input" value="0" step="0.01" onchange="hitungTotal()"></td>
                <td><input type="number" class="form-control form-control-sm credit-input" value="0" step="0.01" onchange="hitungTotal()"></td>
                <td><button class="btn btn-danger btn-sm w-100" onclick="hapusBaris(this)">Hapus</button></td>
            `;
			tbody.appendChild(row);

			// Init select2 untuk row baru
			$(row).find('.account-select').select2({
				placeholder: "-- Pilih Akun --",
				allowClear: true,
				width: '100%'
			});
		}

		// Hapus baris
		function hapusBaris(btn) {
			if (document.querySelectorAll('#journalBody tr').length <= 1) {
				alert("Minimal satu baris jurnal harus ada.");
				return;
			}
			btn.closest('tr').remove();
			hitungTotal();
		}

		// Simpan jurnal
		function simpanJurnal() {
			const lines = [];
			let hasError = false;

			document.querySelectorAll('#journalBody tr').forEach(row => {
				const select = row.querySelector('.account-select');
				let kode = select.value;
				let nama = row.querySelector('.account-name').value;

				// Fallback manual input (jika implemented)
				if (!kode) {
					// Check hidden/manual value if using manual input feature
				}

				const debit = parseFloat(row.querySelector('.debit-input').value) || 0;
				const credit = parseFloat(row.querySelector('.credit-input').value) || 0;

				if (!kode) {
					// Skip empty rows usually, but warn if amounts are filled
					if (debit > 0 || credit > 0) hasError = true;
					return;
				}

				lines.push({
					account_code: kode,
					account_name: nama,
					debit,
					credit
				});
			});

			if (hasError) {
				alert('Ada baris dengan nominal tapi tanpa akun!');
				return;
			}

			// Validasi balance
			const totalDebit = lines.reduce((s, l) => s + l.debit, 0);
			const totalCredit = lines.reduce((s, l) => s + l.credit, 0);

			if (Math.abs(totalDebit - totalCredit) > 1) { // Toleransi 1 rupiah
				alert('Jurnal tidak seimbang!\nDebit: ' + formatRupiah(totalDebit) + '\nKredit: ' + formatRupiah(totalCredit));
				return;
			}

			if (lines.length === 0) {
				alert('Minimal harus ada 1 baris jurnal yang valid');
				return;
			}

			// Kirim ke server
			fetch(SAVE_URL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': CSRF_TOKEN
					},
					body: JSON.stringify({
						journal_data: {
							date: document.getElementById('journalDate').value,
							vendor: document.getElementById('journalVendor').value,
							description: document.getElementById('journalActivity').value,
							lines: lines
						},
						// Kirim ulang unit_id jika perlu, tapi controller showTable ambil dari session, store method mungkin butuh
						unit_id: "{{ $journalData['unit_id'] ?? '' }}"
					})
				})
				.then(r => r.json())
				.then(data => {
					if (data.success) {
						alert('Jurnal berhasil disimpan!\nNomor: ' + data.journal_number);
						window.location.href = "{{ route('journals.list') }}";
					} else {
						alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
					}
				})
				.catch(err => alert('Error: ' + err.message));
		}
	</script>
</body>

</html>