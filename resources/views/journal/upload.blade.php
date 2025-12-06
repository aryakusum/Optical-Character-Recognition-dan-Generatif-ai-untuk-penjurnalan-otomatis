<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Otomatisasi Jurnal - OCR</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		pre{white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;overflow:auto;max-height:50vh}
		#loadingOverlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:1050}
		@media (max-width: 576px){
			.navbar .nav-link{padding:.25rem 0}
			.container{padding-left:1rem;padding-right:1rem}
			h2.fw-bold{font-size:1.35rem}
			.btn.btn-danger.btn-lg{font-size:1.05rem;padding:.9rem 1rem}
			.bg-danger.rounded-3.p-4{padding:1rem !important}
		}
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
					<li class="nav-item"><a class="nav-link text-white" href="#">jurnal</a></li>
					<li class="nav-item"><a class="nav-link text-white" href="#">username</a></li>
				</ul>
			</div>
		</div>
	</nav>

	<div class="container py-5">
		<div class="text-center mb-4">
			<h2 class="fw-bold">Masukkan ke jurnal</h2>
			<p class="text-muted">masukkan foto/pdf transaksi ke jurnal</p>
		</div>

		<div class="row justify-content-center">
			<div class="col-md-8 col-lg-6">
				<div class="card shadow-sm p-4">
					<form id="uploadForm">
						<input type="file" id="image" name="image" accept="image/*,.pdf" class="d-none" required />
					<button type="button" id="pickBtn" class="btn btn-danger btn-lg w-100 py-4 fw-bold">Pilih file</button>
						<button type="submit" class="d-none">Upload</button>
					</form>
				</div>
			</div>
		</div>

		<div class="row justify-content-center mt-4" id="output" hidden>
			<div class="col-md-10">
				<p class="text-secondary mb-2">hasil penjurnalan</p>
				<div class="bg-danger rounded-3 p-4 shadow-sm">
					<h3 class="text-center text-white fw-bold mb-4">hasil OCR</h3>
					<div class="bg-white rounded-3 p-3">
						<pre id="ocr_text" class="mb-0"></pre>
					</div>
				</div>

				<div class="card shadow-sm mt-4">
					<div class="card-body">
						<h6 class="mb-2">Data Terstruktur</h6>
						<pre id="structured" class="mb-3"></pre>
						
						<div class="mt-3">
							<label for="customPrompt" class="form-label">Prompt Tambahan (opsional)</label>
							<textarea id="customPrompt" class="form-control" placeholder="Contoh: gunakan akun COA perusahaan saya dan pembulatan ke ratusan." rows="3"></textarea>
							<button id="generateJournalBtn" class="btn btn-success mt-3" hidden>Generate Jurnal (AI)</button>
						</div>
					</div>
				</div>

				<div class="card shadow-sm mt-4" id="journalResult" hidden>
					<div class="card-body">
						<h6 class="mb-2">Jurnal AI</h6>
						<pre id="journalOutput" class="mb-0"></pre>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div id="loadingOverlay">
		<div class="text-center text-white">
			<div class="spinner-border text-light" role="status" style="width:3rem;height:3rem"></div>
			<div class="mt-3 fw-semibold">Memproses gambar, mohon tunggu…</div>
		</div>
	</div>

	<script>
const uploadForm = document.getElementById('uploadForm');
const resultsSection = document.getElementById('output');
const ocrTextPre = document.getElementById('ocr_text');
const structuredPre = document.getElementById('structured');
const pickFileButton = document.getElementById('pickBtn');
const fileInput = document.getElementById('image');
const loadingOverlay = document.getElementById('loadingOverlay');
const generateJournalBtn = document.getElementById('generateJournalBtn');
const journalResult = document.getElementById('journalResult');
const journalOutput = document.getElementById('journalOutput');
let lastStructuredData = null;

pickFileButton.addEventListener('click', () => {
	fileInput.click();
});

fileInput.addEventListener('change', () => {
	if (fileInput.files.length > 0) {
		uploadForm.requestSubmit();
	}
});

uploadForm.addEventListener('submit', async (e) => {
		e.preventDefault();
	const selectedFile = fileInput.files[0];
	if (!selectedFile) return;

		const fd = new FormData();
	fd.append('image', selectedFile);

	// show loading
	loadingOverlay.style.display = 'flex';
	pickFileButton.setAttribute('disabled', 'true');

	const res = await fetch('{{ route('journal.process') }}', {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': '{{ csrf_token() }}'
			},
			body: fd
		});

		const data = await res.json();
	loadingOverlay.style.display = 'none';
	pickFileButton.removeAttribute('disabled');
	resultsSection.hidden = false;
		if (!data.success) {
		ocrTextPre.textContent = '';
		structuredPre.textContent = '';
			alert('Gagal memproses: ' + (data.message || 'Unknown error'));
			return;
		}
	ocrTextPre.textContent = data.ocr_text || '';
	structuredPre.textContent = JSON.stringify(data.structured, null, 2);
	lastStructuredData = data.structured || null;
	generateJournalBtn.hidden = !lastStructuredData;
	journalResult.hidden = true;
});

generateJournalBtn.addEventListener('click', async () => {
	if (!lastStructuredData) return;
	
	loadingOverlay.style.display = 'flex';
	generateJournalBtn.setAttribute('disabled', 'true');
	
	const res = await fetch('{{ route('journal.generate') }}', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': '{{ csrf_token() }}'
		},
		body: JSON.stringify({ 
			structured: lastStructuredData, 
			prompt: document.getElementById('customPrompt').value 
		})
	});
	
	const data = await res.json();
	loadingOverlay.style.display = 'none';
	generateJournalBtn.removeAttribute('disabled');
	
	if (!data.success) {
		journalOutput.textContent = 'Gagal generate jurnal: ' + (data.message || 'Unknown error');
		journalResult.hidden = false;
		return;
	}
	
	journalOutput.textContent = JSON.stringify(data.journal, null, 2);
	journalResult.hidden = false;
	
	// Add button to view table
	if (data.table_url) {
		const tableBtn = document.createElement('button');
		tableBtn.className = 'btn btn-primary mt-3';
		tableBtn.textContent = 'Lihat Tabel Jurnal';
		tableBtn.onclick = () => window.location.href = data.table_url;
		journalResult.querySelector('.card-body').appendChild(tableBtn);
	}
});
	</script>
</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


