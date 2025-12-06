<?php

namespace App\Services;

class TransactionExtractor
{
	// Fungsi utama: ekstrak data terstruktur dari hasil OCR struk
	public function extract(string $ocrText): array
	{
		// Pisahkan baris-baris struk OCR jadi array
		$lines = preg_split('/\r?\n/', (string) $ocrText);
		$lines = array_map('trim', $lines);
		$lines = array_filter($lines, fn($line) => $line !== '');
		$lines = array_values($lines);

		// Parsing tiap data penting
		$date = $this->parseDate($lines);
		$currency = $this->parseCurrency($lines) ?? 'IDR';
		$vendor = $this->parseVendor($lines);
		$items = $this->parseItems($lines, $currency);
		$total = $this->parseTotal($lines, $currency, $items);
		$receiptNumber = $this->parseReceiptNumber($lines);
		$customerInfo = $this->parseCustomerInfo($lines);
		$paymentMethod = $this->parsePaymentMethod($lines);

		// Return struk data terstruktur dengan field yang jelas
		return [
			'tanggal_transaksi' => $date,                         // Format yyyy-mm-dd atau null
			'nama_toko' => $vendor,                               // Nama toko/vendor
			'currency' => $currency,                             // Mata uang
			'total_pembayaran' => $total,                        // Nilai total di struk
			'daftar_item' => $items,                             // List item transaksi
			'nomor_resi' => $receiptNumber,                      // Nomor resi/nota/struk
			'info_pelanggan' => $customerInfo,                   // Array info pelanggan (id/nama/telepon)
			'cara_pembayaran' => $paymentMethod,                 // Metode pembayaran
			'struk_mentah' => $lines,                            // Semua baris hasil OCR
		];
	}

	// Cari tanggal di antara baris
	private function parseDate(array $lines): ?string
	{
		// Bisa dd-mm-yyyy, yyyy-mm-dd, dll
		$patterns = [
			'/\b(20\d{2})[-\/](\d{1,2})[-\/](\d{1,2})\b/', // yyyy-mm-dd
			'/\b(\d{1,2})[-\/](\d{1,2})[-\/](20\d{2})\b/', // dd-mm-yyyy
		];
		foreach ($lines as $line) {
			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $line, $m)) {
					// Penyesuaian urutan year-month-day
					if (strlen($m[1]) === 4) {
						$year = $m[1]; $month = $m[2]; $day = $m[3];
					} else {
						$day = $m[1]; $month = $m[2]; $year = $m[3];
					}
					if (checkdate((int)$month, (int)$day, (int)$year)) {
						return sprintf('%04d-%02d-%02d', $year, $month, $day);
					}
				}
			}
		}
		return null;
	}

	// Deteksi currency/IDR
	private function parseCurrency(array $lines): ?string
	{
		$text = strtoupper(implode(' ', $lines));
		if (str_contains($text, 'IDR') || str_contains($text, 'RP') || str_contains($text, 'RPH')) return 'IDR';
		if (str_contains($text, 'USD') || str_contains($text, '$')) return 'USD';
		if (str_contains($text, 'EUR') || str_contains($text, '€')) return 'EUR';
		return null;
	}

	// Nama vendor/toko
	private function parseVendor(array $lines): ?string
	{
		$keywords = ['INVOICE','FAKTUR','RECEIPT','STRUK','TOTAL','SUBTOTAL','TAX','PAJAK','PPN','NO.','NOMOR'];
		foreach ($lines as $line) {
			$cb = strtoupper($line);
			$adaKeyword = false;
			foreach ($keywords as $key) if (str_contains($cb, $key)) $adaKeyword = true;
			if ($adaKeyword) continue;
			if (preg_match('/[\d\$€]|IDR|USD|EUR|RP/i', $line)) continue;
			if (strlen($line) >= 3 && strlen($line) <= 60) return $line;
		}
		return null;
	}

	// Deteksi dan normalisasi item belanja
	private function parseItems(array $lines, string $currency): array
	{
		$hasil = [];
		// Format yang didukung: NamaBarang x2 5000 10000
		foreach ($lines as $line) {
			if (preg_match('/^(.*?)\s+(?:x\s*)?(\d{1,4})\s+([\d\.,]+)\s+([\d\.,]+)$/i', $line, $m)) {
				$namaItem = trim($m[1]);
				$qty = (int)$m[2];
				$hargaSatuan = $this->toNumber($m[3]);
				$total = $this->toNumber($m[4]);
				$hasil[] = [
					'nama_item' => $namaItem,
					'jumlah' => $qty,
					'harga_satuan' => $hargaSatuan,
					'subtotal' => $total,
				];
			}
		}
		return $hasil;
	}

	// Ambil pola variasi penulisan total pembayaran
	private function parseTotal(array $lines, string $currency, array $items): ?float
	{
		$patterns = [
			'/TOTAL\s*[:\-]?\s*([\d\.,]+)/i',
			'/TOTAL\s*BAYAR\s*[:\-]?\s*([\d\.,]+)/i',
			'/TOTAL\s*PEMBAYARAN\s*[:\-]?\s*([\d\.,]+)/i',
			'/TOTAL\s*HARUS\s*BAYAR\s*[:\-]?\s*([\d\.,]+)/i',
			'/TAGIHAN\s*[:\-]?\s*([\d\.,]+)/i',
			'/BAYAR\s*[:\-]?\s*([\d\.,]+)/i',
			'/RP\s*([\d\.,]+)/i',
			'/IDR\s*([\d\.,]+)/i',
			'/RUPIAH\s*([\d\.,]+)/i',
		];
		foreach ($lines as $line) {
			foreach ($patterns as $pat) {
				if (preg_match($pat, $line, $m)) {
					$jumlah = $this->toNumber($m[1]);
					if ($jumlah > 0) return $jumlah;
				}
			}
		}
		// Jika tidak dapat pola total, fallback ke sum item
		$jumlahItem = 0.0;
		foreach ($items as $item) $jumlahItem += (float)($item['subtotal'] ?? 0);
		return $jumlahItem > 0 ? $jumlahItem : null;
	}

	// Pola konversi string ke angka float
	private function toNumber(string $nominal): float
	{
		$nominal = trim($nominal);
		if (preg_match('/,\d{2}$/', $nominal) && str_contains($nominal, '.')) {
			$nominal = str_replace('.', '', $nominal);
			$nominal = str_replace(',', '.', $nominal);
		} else {
			$nominal = str_replace(',', '', $nominal);
		}
		return (float)($nominal === '' ? 0 : $nominal);
	}

	// Nomor resi
	private function parseReceiptNumber(array $lines): ?string
	{
		$pola = [
			'/NO\.?\s*RESI\s*[:\-]?\s*(\w+)/i',
			'/NO\.?\s*STRUK\s*[:\-]?\s*(\w+)/i',
			'/NO\.?\s*TRANSAKSI\s*[:\-]?\s*(\w+)/i',
			'/RESI\s*[:\-]?\s*(\w+)/i',
			'/STRUK\s*[:\-]?\s*(\w+)/i',
		];
		foreach ($lines as $baris) {
			foreach ($pola as $pat) {
				if (preg_match($pat, $baris, $m)) return trim($m[1]);
			}
		}
		return null;
	}

	// Info pelanggan (Nama, ID, telepon)
	private function parseCustomerInfo(array $lines): array
	{
		$hasil = [];
		$pola = [
			'customer_id' => '/ID\s*PELANGGAN\s*[:\-]?\s*(\w+)/i',
			'phone' => '/\b(08\d{8,11})\b/',
			'name' => '/NAMA\s*[:\-]?\s*([A-Za-z\s]+)/i',
		];
		foreach ($lines as $baris) {
			foreach ($pola as $key => $pat) {
				if (preg_match($pat, $baris, $m)) $hasil[$key] = trim($m[1]);
			}
		}
		return $hasil;
	}

	// Metode pembayaran (cash/card/digital)
	private function parsePaymentMethod(array $lines): ?string
	{
		$daftar = ['CASH', 'CARD', 'TRANSFER', 'QRIS', 'DIGITAL', 'E-WALLET'];
		$text = strtoupper(implode(' ', $lines));
		foreach ($daftar as $metode) if (str_contains($text, $metode)) return $metode;
		return null;
	}
}


