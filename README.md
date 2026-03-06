# OCR + Generative AI untuk Penjurnalan Otomatis

Sistem penjurnalan akuntansi otomatis yang menggunakan OCR (Optical Character Recognition) dan AI (Gemini) untuk mengekstrak data dari dokumen keuangan dan membuat jurnal umum secara otomatis.

## Fitur Utama

-   **OCR Dokumen**: Upload gambar/PDF struk, nota, invoice, kuitansi
-   **AI Extraction**: Gemini Vision untuk ekstraksi data terstruktur
-   **Validasi Otomatis**: Cek kelengkapan dokumen dan perhitungan
-   **Generate Jurnal**: AI generate jurnal debit/credit yang balance
-   **Multi-Unit**: Setiap unit kerja memiliki Chart of Accounts sendiri

## Teknologi

-   **Backend**: Laravel 11
-   **OCR**: Gemini Vision (gambar), OCR.space (PDF)
-   **AI**: Google Gemini API
-   **Database**: MySQL/MariaDB

## Struktur Folder

```
app/
├── Http/Controllers/
│   ├── AuthController.php      # Login, register, logout
│   ├── JournalController.php   # CRUD jurnal umum
│   └── OcrAiController.php     # Proses OCR dan AI
├── Models/
│   ├── Account.php             # Chart of Accounts
│   ├── Journal.php             # Header jurnal
│   ├── JournalLine.php         # Detail baris jurnal
│   ├── Unit.php                # Unit kerja
│   └── User.php                # Pengguna
└── Services/
    ├── AiJournalService.php          # Generate jurnal dengan Gemini
    ├── AmountValidationService.php   # Validasi perhitungan
    ├── DocumentChecklistService.php  # Validasi kelengkapan
    ├── DocumentTypeDetector.php      # Deteksi tipe dokumen
    ├── GeminiVisionService.php       # Ekstrak data dari gambar
    ├── OcrService.php                # OCR dengan OCR.space
    ├── TransactionExtractor.php      # Parse text OCR ke data
    └── UnitAccountService.php        # Mapping akun per unit
```

## Setup

### 1. Clone & Install

```bash
git clone <repo-url>
cd TA
composer install
npm install
```

### 2. Environment

Copy `.env.example` ke `.env` dan set variabel:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ta_journal
DB_USERNAME=root
DB_PASSWORD=

# API Keys
OCR_SPACE_API_KEY=your_ocr_space_api_key
GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-2.5-flash
```

### 3. Database

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### 4. Run

```bash
php artisan serve
npm run dev
```

Buka `http://localhost:8000`

## Alur Kerja

```
1. User upload dokumen (gambar/PDF)
       ↓
2. Sistem ekstrak data dengan OCR/Gemini Vision
       ↓
3. Validasi kelengkapan dan perhitungan
       ↓
4. AI generate jurnal (debit/credit)
       ↓
5. User review dan simpan
```

## API Endpoints

### Autentikasi

-   `GET /login` - Halaman login
-   `POST /login` - Proses login
-   `GET /register` - Halaman register
-   `POST /register` - Proses register
-   `POST /logout` - Logout

### OCR + AI

-   `GET /journal` - Halaman upload dokumen
-   `POST /journal/process` - Proses OCR dokumen
-   `POST /journal/generate` - Generate jurnal dengan AI
-   `GET /journal/table` - Preview hasil generate

### Jurnal Umum

-   `GET /journals` - Daftar jurnal
-   `GET /journals/{id}` - Detail jurnal
-   `POST /journals` - Simpan jurnal baru
-   `PUT /journals/{id}/status` - Update status
-   `DELETE /journals/{id}` - Hapus jurnal draft

### API Data

-   `GET /api/units` - Daftar unit dengan akun
-   `GET /api/units/{id}/accounts` - Akun per unit

## Format Response

### Proses OCR (`POST /journal/process`)

```json
{
  "success": true,
  "ocr_text": "raw text hasil OCR",
  "structured": {
    "tanggal_transaksi": "2024-01-15",
    "nama_toko": "PT Supplier",
    "total_pembayaran": 150000,
    "daftar_item": [...]
  },
  "verification": {
    "document_type": { "type": "invoice", "confidence": 85 },
    "checklist": { "is_complete": true, ... },
    "amount_validation": { ... }
  }
}
```

### Generate Jurnal (`POST /journal/generate`)

```json
{
    "success": true,
    "journal": {
        "date": "2024-01-15",
        "vendor": "PT Supplier",
        "total": 150000,
        "lines": [
            {
                "account_code": "5-1001",
                "account_name": "Beban ATK",
                "debit": 150000,
                "credit": 0
            },
            {
                "account_code": "1-1001",
                "account_name": "Kas",
                "debit": 0,
                "credit": 150000
            }
        ]
    }
}
```

## Catatan Pengembangan

-   Gunakan Gemini Vision untuk gambar (lebih akurat)
-   OCR.space untuk PDF (gratis, cukup untuk dokumen sederhana)
-   Rate limit Gemini free tier: 15 request/menit
-   Semua jurnal baru berstatus `draft`, harus di-post manual
-   Jurnal yang sudah `posted` tidak bisa diedit/hapus

## License

MIT License
