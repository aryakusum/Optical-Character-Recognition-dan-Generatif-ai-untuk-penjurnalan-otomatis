<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Upload Dokumen - Sistem Verifikasi</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body {
			background-color: #f5f5f5;
		}

		.card {
			border: none;
			border-radius: 8px;
		}

		.btn-primary {
			background-color: #3498db;
			border-color: #3498db;
		}

		.btn-primary:hover {
			background-color: #2980b9;
			border-color: #2980b9;
		}

		.btn-success {
			background-color: #27ae60;
			border-color: #27ae60;
		}

		.verification-card {
			border-left: 4px solid #3498db;
		}

		.verification-success {
			border-left-color: #27ae60;
		}

		.verification-warning {
			border-left-color: #f39c12;
		}

		.verification-error {
			border-left-color: #e74c3c;
		}

		.status-valid {
			color: #27ae60;
		}

		.status-warning {
			color: #f39c12;
		}

		.status-error {
			color: #e74c3c;
		}

		#loadingOverlay {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.7);
			display: none;
			justify-content: center;
			align-items: center;
			z-index: 9999;
		}

		/* Editable items table */
		.items-table input {
			border: 1px solid #dee2e6;
			border-radius: 4px;
			padding: 4px 8px;
			width: 100%;
		}
		.items-table input:focus {
			border-color: #3498db;
			outline: none;
			box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
		}
		.items-table .btn-remove {
			padding: 2px 8px;
			font-size: 12px;
		}

		/* Checkbox styling for checklist */
		.checklist-item {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 4px;
		}
		.checklist-item input[type="checkbox"] {
			width: 16px;
			height: 16px;
			cursor: pointer;
		}
		.checklist-item label {
			margin: 0;
			cursor: pointer;
		}
		.checklist-item.completed label {
			color: #27ae60;
		}
		.checklist-item.incomplete label {
			color: #e74c3c;
		}
	</style>
</head>

<body>
	@include('partials.navbar')

	<div class="container py-4">
		<!-- Header -->
		<div class="text-center mb-4">
			<h2 class="fw-bold">Upload Dokumen Transaksi</h2>
			<p class="text-muted">Upload foto kuitansi, invoice, struk, atau nota untuk diverifikasi</p>
		</div>

		<!-- Form Upload -->
		<div class="row justify-content-center">
			<div class="col-md-6">
				<div class="card shadow-sm p-4">
					<form id="uploadForm">
						<input type="file" id="image" name="image" accept="image/*,.pdf" class="d-none" required>
						<button type="button" id="pickBtn" class="btn btn-primary btn-lg w-100 py-3">
							Pilih File Dokumen
						</button>
						<small class="text-muted d-block text-center mt-2">Format: JPG, PNG, PDF (maks 5MB)</small>
					</form>
				</div>
			</div>
		</div>

		<!-- Hasil Verifikasi -->
		<div id="output" hidden class="mt-4">
			<div class="row g-4">
				<!-- Panel Verifikasi -->
				<div class="col-lg-5">
					<div class="card verification-card shadow-sm" id="verificationCard">
						<div class="card-header bg-white d-flex justify-content-between align-items-center">
							<strong>Hasil Verifikasi</strong>
							<span class="badge bg-secondary" id="editModeIndicator">Mode Edit Aktif</span>
						</div>
						<div class="card-body">
							<!-- Tipe Dokumen -->
							<div class="mb-3">
								<label class="text-muted small">Tipe Dokumen</label>
								<h5 id="docType" class="mb-0 fw-bold">-</h5>
								<small id="docConfidence" class="text-muted">-</small>
							</div>

							<!-- Kelengkapan -->
							<div class="mb-3">
								<label class="text-muted small">Kelengkapan Dokumen</label>
								<small class="text-muted d-block mb-1">(Klik checkbox untuk menandai manual)</small>
								<div class="progress mb-2" style="height: 6px;">
									<div id="checklistProgress" class="progress-bar" style="width: 0%"></div>
								</div>
								<div id="checklistItems"></div>
							</div>

							<!-- Validasi Perhitungan -->
							<div class="mb-0">
								<label class="text-muted small">Validasi Perhitungan</label>
								<div id="amountValidation"></div>
							</div>
						</div>
					</div>

					<!-- Pilih Unit -->
					@if(isset($units) && count($units) > 0)
					<div class="card shadow-sm mt-3">
						<div class="card-body">
							<label class="form-label fw-bold">Unit</label>
							<select id="unitSelect" class="form-select">
								<option value="">-- Pilih Unit --</option>
								@foreach($units as $unit)
								<option value="{{ $unit->id }}" {{ (Auth::user()?->unit_id == $unit->id) ? 'selected' : '' }}>
									{{ $unit->name }}
								</option>
								@endforeach
							</select>
						</div>
					</div>
					@endif

					<button id="generateJournalBtn" class="btn btn-success w-100 mt-3 py-2" hidden>
						Generate Jurnal
					</button>
				</div>

				<!-- Panel Data OCR -->
				<div class="col-lg-7">
					<div class="card shadow-sm">
						<div class="card-header bg-white">
							<ul class="nav nav-tabs card-header-tabs" role="tablist">
								<li class="nav-item">
									<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOcr">Teks OCR</button>
								</li>
								<li class="nav-item">
									<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabData">Data Terstruktur</button>
								</li>
							</ul>
						</div>
						<div class="card-body">
							<div class="tab-content">
								<div class="tab-pane fade show active" id="tabOcr">
									<pre id="ocr_text" class="bg-light p-3 rounded" style="max-height: 400px; overflow: auto; font-size: 12px;"></pre>
								</div>
								<div class="tab-pane fade" id="tabData">
									<div id="structured_data" class="bg-light p-3 rounded" style="max-height: 500px; overflow: auto;">
										<!-- Header Info -->
										<div class="row g-2 mb-3">
											<div class="col-md-6">
												<label class="form-label small text-muted mb-1">Tanggal Transaksi</label>
												<input type="date" id="edit_tanggal" class="form-control form-control-sm">
											</div>
											<div class="col-md-6">
												<label class="form-label small text-muted mb-1">Nama Toko/Vendor</label>
												<input type="text" id="edit_nama_toko" class="form-control form-control-sm">
											</div>
										</div>
										<div class="row g-2 mb-3">
											<div class="col-md-6">
												<label class="form-label small text-muted mb-1">Nomor Resi/Dokumen</label>
												<input type="text" id="edit_nomor_resi" class="form-control form-control-sm">
											</div>
											<div class="col-md-6">
												<label class="form-label small text-muted mb-1">Cara Pembayaran</label>
												<input type="text" id="edit_cara_bayar" class="form-control form-control-sm">
											</div>
										</div>

										<!-- Items Table -->
										<label class="form-label small text-muted mb-1">Daftar Item <span class="text-primary">(dapat diedit)</span></label>
										<div class="table-responsive">
											<table class="table table-sm table-bordered items-table" id="itemsTable">
												<thead class="table-light">
													<tr>
														<th style="width: 40%">Nama Item</th>
														<th style="width: 15%">Qty</th>
														<th style="width: 20%">Harga Satuan</th>
														<th style="width: 20%">Subtotal</th>
														<th style="width: 5%"></th>
													</tr>
												</thead>
												<tbody id="itemsTableBody">
													<!-- Items will be rendered here -->
												</tbody>
												<tfoot>
													<tr>
														<td colspan="5">
															<button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
																+ Tambah Item
															</button>
														</td>
													</tr>
												</tfoot>
											</table>
										</div>

										<!-- Total -->
										<div class="row g-2 mt-2">
											<div class="col-md-6">
												<label class="form-label small text-muted mb-1">Total Pembayaran</label>
												<input type="number" id="edit_total" class="form-control form-control-sm">
											</div>
											<div class="col-md-6">
												<label class="form-label small text-muted mb-1">Keterangan</label>
												<input type="text" id="edit_keterangan" class="form-control form-control-sm">
											</div>
										</div>

										<!-- Recalculate Button -->
										<div class="mt-3">
											<button type="button" class="btn btn-sm btn-outline-secondary" id="recalculateTotalBtn">
												Hitung Ulang Total dari Item
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="mt-3">
						<label class="form-label">Prompt Tambahan (opsional)</label>
						<textarea id="customPrompt" class="form-control" rows="2" placeholder="Contoh: gunakan akun tertentu..."></textarea>
					</div>
				</div>
			</div>
		</div>

		<!-- Hasil Jurnal -->
		<div class="card shadow-sm mt-4" id="journalResult" hidden>
			<div class="card-header bg-success text-white">
				<strong>Hasil Jurnal</strong>
			</div>
			<div class="card-body">
				<pre id="journalOutput" class="bg-light p-3 rounded mb-3"></pre>
				<div class="d-flex gap-2">
					<button id="saveJournalBtn" class="btn btn-primary">Simpan ke Jurnal Umum</button>
					<a href="{{ route('journals.list') }}" class="btn btn-outline-secondary">Lihat Jurnal Umum</a>
				</div>
			</div>
		</div>
	</div>

	<!-- Loading Overlay -->
	<div id="loadingOverlay">
		<div class="text-center text-white">
			<div class="spinner-border" style="width: 3rem; height: 3rem;"></div>
			<div class="mt-3" id="loadingText">Memproses...</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		// Konfigurasi URL dan Token
		const PROCESS_URL = "{{ route('journal.process') }}";
		const GENERATE_URL = "{{ route('journal.generate') }}";
		const SAVE_URL = "{{ route('journals.store') }}";
		const CSRF_TOKEN = "{{ csrf_token() }}";

		// Elemen DOM
		const pickBtn = document.getElementById('pickBtn');
		const imageInput = document.getElementById('image');
		const output = document.getElementById('output');
		const ocrTextPre = document.getElementById('ocr_text');
		const generateBtn = document.getElementById('generateJournalBtn');
		const journalResult = document.getElementById('journalResult');
		const journalOutput = document.getElementById('journalOutput');
		const loadingOverlay = document.getElementById('loadingOverlay');
		const loadingText = document.getElementById('loadingText');

		// Variabel penyimpanan data
		let lastStructuredData = null;
		let lastJournalData = null;
		let lastVerificationData = null;

		// Event: Pilih file
		pickBtn.addEventListener('click', () => imageInput.click());

		// Event: File dipilih
		imageInput.addEventListener('change', async () => {
			if (!imageInput.files.length) return;

			showLoading('Memproses dokumen...');
			output.hidden = true;
			journalResult.hidden = true;

			const formData = new FormData();
			formData.append('image', imageInput.files[0]);

			try {
				const response = await fetch(PROCESS_URL, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': CSRF_TOKEN
					},
					body: formData
				});

				const data = await response.json();
				hideLoading();

				if (!data.success) {
					alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
					return;
				}

				ocrTextPre.textContent = data.ocr_text || '';
				lastStructuredData = data.structured;
				lastVerificationData = data.verification;

				// Render editable structured data
				renderEditableStructuredData(data.structured);
				tampilkanVerifikasi(data.verification);

				output.hidden = false;
				generateBtn.hidden = false;
			} catch (error) {
				hideLoading();
				alert('Error: ' + error.message);
			}
		});

		// Render data terstruktur yang bisa diedit
		function renderEditableStructuredData(data) {
			// Header fields
			document.getElementById('edit_tanggal').value = data.tanggal_transaksi || '';
			document.getElementById('edit_nama_toko').value = data.nama_toko || '';
			document.getElementById('edit_nomor_resi').value = data.nomor_resi || '';
			document.getElementById('edit_cara_bayar').value = data.cara_pembayaran || '';
			document.getElementById('edit_total').value = data.total_pembayaran || '';
			document.getElementById('edit_keterangan').value = data.keterangan || '';

			// Items table
			renderItemsTable(data.daftar_item || []);
		}

		// Render tabel items
		function renderItemsTable(items) {
			const tbody = document.getElementById('itemsTableBody');
			tbody.innerHTML = '';

			items.forEach((item, index) => {
				const row = createItemRow(item, index);
				tbody.appendChild(row);
			});
		}

		// Buat row item
		function createItemRow(item, index) {
			const row = document.createElement('tr');
			row.dataset.index = index;

			const qty = parseFloat(item.qty || item.quantity || 1);
			const price = parseFloat(item.harga_satuan || item.unit_price || item.harga || 0);
			const subtotal = qty * price;

			row.innerHTML = `
				<td><input type="text" class="item-name" value="${escapeHtml(item.nama || item.name || '')}" placeholder="Nama item"></td>
				<td><input type="number" class="item-qty" value="${qty}" min="0" step="0.01" onchange="updateSubtotal(this)"></td>
				<td><input type="number" class="item-price" value="${price}" min="0" step="1" onchange="updateSubtotal(this)"></td>
				<td class="item-subtotal text-end">${formatNumber(subtotal)}</td>
				<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove" onclick="removeItemRow(this)">×</button></td>
			`;

			return row;
		}

		// Update subtotal saat qty atau harga berubah
		function updateSubtotal(input) {
			const row = input.closest('tr');
			const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
			const price = parseFloat(row.querySelector('.item-price').value) || 0;
			const subtotal = qty * price;
			row.querySelector('.item-subtotal').textContent = formatNumber(subtotal);
		}

		// Hapus row item
		function removeItemRow(btn) {
			btn.closest('tr').remove();
		}

		// Tambah item baru
		document.getElementById('addItemBtn').addEventListener('click', () => {
			const tbody = document.getElementById('itemsTableBody');
			const newItem = { nama: '', qty: 1, harga_satuan: 0 };
			const row = createItemRow(newItem, tbody.children.length);
			tbody.appendChild(row);
		});

		// Hitung ulang total dari items
		document.getElementById('recalculateTotalBtn').addEventListener('click', () => {
			const rows = document.querySelectorAll('#itemsTableBody tr');
			let total = 0;
			rows.forEach(row => {
				const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
				const price = parseFloat(row.querySelector('.item-price').value) || 0;
				total += qty * price;
			});
			document.getElementById('edit_total').value = total;
		});

		// Ambil data dari form yang sudah diedit
		function getEditedStructuredData() {
			const items = [];
			const rows = document.querySelectorAll('#itemsTableBody tr');

			rows.forEach(row => {
				items.push({
					nama: row.querySelector('.item-name').value,
					qty: parseFloat(row.querySelector('.item-qty').value) || 0,
					harga_satuan: parseFloat(row.querySelector('.item-price').value) || 0
				});
			});

			return {
				tanggal_transaksi: document.getElementById('edit_tanggal').value,
				nama_toko: document.getElementById('edit_nama_toko').value,
				nomor_resi: document.getElementById('edit_nomor_resi').value,
				cara_pembayaran: document.getElementById('edit_cara_bayar').value,
				total_pembayaran: parseFloat(document.getElementById('edit_total').value) || 0,
				keterangan: document.getElementById('edit_keterangan').value,
				daftar_item: items,
				currency: lastStructuredData?.currency || 'IDR',
				struk_mentah: lastStructuredData?.struk_mentah || [],
				info_pelanggan: lastStructuredData?.info_pelanggan || {},
				terbilang: lastStructuredData?.terbilang || null,
				pajak: lastStructuredData?.pajak || []
			};
		}

		// Tampilkan hasil verifikasi dengan checkbox yang bisa diedit
		function tampilkanVerifikasi(v) {
			const card = document.getElementById('verificationCard');
			const tipe = v.document_type.type;
			const confidence = v.document_type.confidence || 0;

			// Nama tipe dokumen
			const namaTipe = {
				'kuitansi': 'KUITANSI',
				'invoice': 'INVOICE',
				'struk': 'STRUK',
				'nota': 'NOTA',
				'faktur_pajak': 'FAKTUR PAJAK',
				'unknown': 'TIDAK DIKENALI'
			};

			document.getElementById('docType').textContent = namaTipe[tipe] || tipe.toUpperCase();
			document.getElementById('docConfidence').textContent = 'Confidence: ' + confidence + '%';

			// Progress bar
			updateChecklistProgress(v.checklist);

			// Checklist items dengan checkbox
			let html = '<div class="checklist-container">';
			v.checklist.items.forEach((item, index) => {
				const completedClass = item.completed ? 'completed' : 'incomplete';
				const checked = item.completed ? 'checked' : '';
				html += `
					<div class="checklist-item ${completedClass}" data-index="${index}">
						<input type="checkbox" id="checklist_${index}" ${checked} onchange="updateChecklistItem(${index}, this.checked)">
						<label for="checklist_${index}">${item.label}${item.required ? ' <span class="text-danger">*</span>' : ''}</label>
					</div>
				`;
			});
			html += '</div>';
			document.getElementById('checklistItems').innerHTML = html;

			// Validasi amount
			let valHtml = '<div class="small">';
			const totalV = v.amount_validation.total_validation;
			if (totalV) {
				const cls = totalV.status === 'valid' ? 'status-valid' : totalV.status === 'warning' ? 'status-warning' : 'text-muted';
				valHtml += `<p class="${cls} mb-1"><strong>Total:</strong> ${totalV.message}</p>`;
			}

			const terbilangV = v.amount_validation.terbilang_validation;
			if (tipe === 'kuitansi' && terbilangV) {
				const cls = terbilangV.status === 'valid' ? 'status-valid' : 'status-warning';
				valHtml += `<p class="${cls} mb-1"><strong>Terbilang:</strong> ${terbilangV.message}</p>`;
			}

			const ppnV = v.amount_validation.ppn_validation;
			if ((tipe === 'invoice' || tipe === 'faktur_pajak') && ppnV) {
				const cls = ppnV.status === 'valid' ? 'status-valid' : 'status-warning';
				valHtml += `<p class="${cls} mb-0"><strong>PPN:</strong> ${ppnV.message}</p>`;
			}
			valHtml += '</div>';
			document.getElementById('amountValidation').innerHTML = valHtml;

			// Card status
			updateVerificationCardStatus(v.checklist, confidence);
		}

		// Update checklist item saat di-toggle
		function updateChecklistItem(index, isChecked) {
			if (lastVerificationData && lastVerificationData.checklist.items[index]) {
				lastVerificationData.checklist.items[index].completed = isChecked;

				// Update visual
				const item = document.querySelector(`.checklist-item[data-index="${index}"]`);
				if (isChecked) {
					item.classList.remove('incomplete');
					item.classList.add('completed');
				} else {
					item.classList.remove('completed');
					item.classList.add('incomplete');
				}

				// Update progress
				updateChecklistProgress(lastVerificationData.checklist);
				updateVerificationCardStatus(lastVerificationData.checklist, lastVerificationData.document_type.confidence);
			}
		}

		// Update progress bar checklist
		function updateChecklistProgress(checklist) {
			const completed = checklist.items.filter(i => i.completed).length;
			const total = checklist.items.length;
			const persen = total > 0 ? Math.round((completed / total) * 100) : 0;

			const progress = document.getElementById('checklistProgress');
			progress.style.width = persen + '%';
			progress.className = 'progress-bar ' + (persen >= 100 ? 'bg-success' : persen >= 70 ? 'bg-warning' : 'bg-danger');
		}

		// Update status card verifikasi
		function updateVerificationCardStatus(checklist, confidence) {
			const card = document.getElementById('verificationCard');
			const missingRequired = checklist.items.filter(i => i.required && !i.completed).length;
			const isComplete = checklist.items.every(i => !i.required || i.completed);

			card.classList.remove('verification-success', 'verification-warning', 'verification-error');
			if (isComplete && confidence >= 70) {
				card.classList.add('verification-success');
			} else if (missingRequired > 0 || confidence < 50) {
				card.classList.add('verification-error');
			} else {
				card.classList.add('verification-warning');
			}
		}

		// Event: Generate Jurnal
		generateBtn.addEventListener('click', async () => {
			// Gunakan data yang sudah diedit
			const editedData = getEditedStructuredData();
			showLoading('Membuat jurnal dengan AI...');

			const unitSelect = document.getElementById('unitSelect');

			try {
				const response = await fetch(GENERATE_URL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': CSRF_TOKEN
					},
					body: JSON.stringify({
						structured: editedData,
						prompt: document.getElementById('customPrompt').value,
						unit_id: unitSelect ? unitSelect.value : null
					})
				});

				const data = await response.json();
				hideLoading();

				if (!data.success) {
					alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
					return;
				}

				if (data.table_url) {
					window.location.href = data.table_url;
				} else {
					lastJournalData = data.journal;
					journalOutput.textContent = JSON.stringify(data.journal, null, 2);
					journalResult.hidden = false;
				}
			} catch (error) {
				hideLoading();
				alert('Error: ' + error.message);
			}
		});

		// Event: Simpan Jurnal
		document.getElementById('saveJournalBtn').addEventListener('click', async () => {
			if (!lastJournalData) return alert('Tidak ada data jurnal');
			showLoading('Menyimpan jurnal...');

			const unitSelect = document.getElementById('unitSelect');
			try {
				const response = await fetch(SAVE_URL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': CSRF_TOKEN
					},
					body: JSON.stringify({
						journal_data: lastJournalData,
						unit_id: unitSelect ? unitSelect.value : null
					})
				});

				const data = await response.json();
				hideLoading();

				if (data.success) {
					alert('Jurnal berhasil disimpan!\nNomor: ' + data.journal_number);
					window.location.href = "{{ route('journals.list') }}";
				} else {
					alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
				}
			} catch (error) {
				hideLoading();
				alert('Error: ' + error.message);
			}
		});

		function showLoading(text) {
			loadingText.textContent = text;
			loadingOverlay.style.display = 'flex';
		}

		function hideLoading() {
			loadingOverlay.style.display = 'none';
		}

		function formatNumber(num) {
			return new Intl.NumberFormat('id-ID').format(num);
		}

		function escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	</script>
</body>

</html>