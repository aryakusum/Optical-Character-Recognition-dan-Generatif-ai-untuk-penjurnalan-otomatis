<?php

namespace App\Services;

class TransactionExtractor
{
	public function extract(string $ocrText): array
	{
		$lines = preg_split('/\r?\n/', (string) $ocrText);
		$lines = array_map('trim', $lines);
		$lines = array_filter($lines, fn($line) => $line !== '');
		$lines = array_values($lines);

		$date = $this->parseDate($lines);
		$currency = $this->parseCurrency($lines) ?? 'IDR';
		$vendor = $this->parseVendor($lines);
		$items = $this->parseItems($lines, $currency);
		$total = $this->parseTotal($lines, $currency, $items);
		$receiptNumber = $this->parseReceiptNumber($lines);
		$customerInfo = $this->parseCustomerInfo($lines);
		$paymentMethod = $this->parsePaymentMethod($lines);

		return [
			'tanggal_transaksi' => $date,
			'nama_toko' => $vendor,
			'currency' => $currency,
			'total_pembayaran' => $total,
			'daftar_item' => $items,
			'nomor_resi' => $receiptNumber,
			'info_pelanggan' => $customerInfo,
			'cara_pembayaran' => $paymentMethod,
			'struk_mentah' => $lines,
		];
	}

	private function parseDate(array $lines): ?string
	{
		$patterns = [
			'/\b(20\d{2})[-\/](\d{1,2})[-\/](\d{1,2})\b/',
			'/\b(\d{1,2})[-\/](\d{1,2})[-\/](20\d{2})\b/',
		];

		foreach ($lines as $line) {
			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $line, $m)) {
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

	private function parseCurrency(array $lines): ?string
	{
		$text = strtoupper(implode(' ', $lines));
		if (str_contains($text, 'IDR') || str_contains($text, 'RP') || str_contains($text, 'RPH')) return 'IDR';
		if (str_contains($text, 'USD') || str_contains($text, '$')) return 'USD';
		if (str_contains($text, 'EUR') || str_contains($text, '€')) return 'EUR';
		return null;
	}

	private function parseVendor(array $lines): ?string
	{
		$keywords = ['INVOICE', 'FAKTUR', 'RECEIPT', 'STRUK', 'TOTAL', 'SUBTOTAL', 'TAX', 'PAJAK', 'PPN', 'NO.', 'NOMOR'];

		foreach ($lines as $line) {
			$cb = strtoupper($line);
			$adaKeyword = false;
			foreach ($keywords as $key) {
				if (str_contains($cb, $key)) $adaKeyword = true;
			}
			if ($adaKeyword) continue;
			if (preg_match('/[\d\$€]|IDR|USD|EUR|RP/i', $line)) continue;
			if (strlen($line) >= 3 && strlen($line) <= 60) return $line;
		}
		return null;
	}

	private function parseItems(array $lines, string $currency): array
	{
		$hasil = [];
		foreach ($lines as $line) {
			if (preg_match('/^(.*?)\s+(?:x\s*)?(\d{1,4})\s+([\d\.,]+)\s+([\d\.,]+)$/i', $line, $m)) {
				$hasil[] = [
					'nama_item' => trim($m[1]),
					'jumlah' => (int)$m[2],
					'harga_satuan' => $this->toNumber($m[3]),
					'subtotal' => $this->toNumber($m[4]),
				];
			}
		}
		return $hasil;
	}

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

		$jumlahItem = 0.0;
		foreach ($items as $item) {
			$jumlahItem += (float)($item['subtotal'] ?? 0);
		}
		return $jumlahItem > 0 ? $jumlahItem : null;
	}

	private function toNumber(string $nominal): float
	{
		$nominal = trim($nominal);
		$nominal = preg_replace('/[^\d,\.]/', '', $nominal);
		if ($nominal === '') return 0.0;

		if (str_contains($nominal, '.') && str_contains($nominal, ',')) {
			if (strrpos($nominal, '.') < strrpos($nominal, ',')) {
				$nominal = str_replace('.', '', $nominal);
				$nominal = str_replace(',', '.', $nominal);
			} else {
				$nominal = str_replace(',', '', $nominal);
			}
		} elseif (substr_count($nominal, '.') > 0) {
			$parts = explode('.', $nominal);
			$lastPart = end($parts);
			if (strlen($lastPart) === 3) {
				$nominal = str_replace('.', '', $nominal);
			}
		} elseif (str_contains($nominal, ',')) {
			$nominal = str_replace(',', '.', $nominal);
		}

		return (float)$nominal;
	}

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
				if (preg_match($pat, $baris, $m)) {
					$hasil[$key] = trim($m[1]);
				}
			}
		}
		return $hasil;
	}

	private function parsePaymentMethod(array $lines): ?string
	{
		$daftar = ['CASH', 'CARD', 'TRANSFER', 'QRIS', 'DIGITAL', 'E-WALLET'];
		$text = strtoupper(implode(' ', $lines));

		foreach ($daftar as $metode) {
			if (str_contains($text, $metode)) return $metode;
		}
		return null;
	}
}
