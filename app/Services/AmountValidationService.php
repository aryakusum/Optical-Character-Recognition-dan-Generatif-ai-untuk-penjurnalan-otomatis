<?php

namespace App\Services;

// Service untuk validasi perhitungan pada dokumen keuangan
class AmountValidationService
{
    // Mapping kata terbilang ke angka
    private array $terbilangMap = [
        'satu' => 1,
        'dua' => 2,
        'tiga' => 3,
        'empat' => 4,
        'lima' => 5,
        'enam' => 6,
        'tujuh' => 7,
        'delapan' => 8,
        'sembilan' => 9,
        'sepuluh' => 10,
        'sebelas' => 11,
        'ratus' => 100,
        'ribu' => 1000,
        'juta' => 1000000,
    ];

    // Konfigurasi validasi per tipe dokumen
    private array $configValidasi = [
        'kuitansi' => ['total' => true, 'terbilang' => true, 'ppn' => false, 'materai' => true],
        'invoice' => ['total' => true, 'terbilang' => false, 'ppn' => true, 'materai' => false],
        'faktur_pajak' => ['total' => true, 'terbilang' => false, 'ppn' => true, 'materai' => false],
        'struk' => ['total' => true, 'terbilang' => false, 'ppn' => false, 'materai' => false],
        'nota' => ['total' => true, 'terbilang' => false, 'ppn' => false, 'materai' => false],
    ];

    // Validasi semua perhitungan berdasarkan tipe dokumen
    public function validateAll(array $data, ?string $tipeDokumen = null): array
    {
        $config = $this->configValidasi[$tipeDokumen] ?? $this->configValidasi['struk'];
        $hasil = [];

        if ($config['total']) {
            $hasil['total_validation'] = $this->validasiTotal($data);
        }

        if ($config['terbilang']) {
            $hasil['terbilang_validation'] = $this->validasiTerbilang($data);
        }

        if ($config['ppn']) {
            $hasil['ppn_validation'] = $this->validasiPPN($data);
        }

        if ($config['materai']) {
            $hasil['materai_validation'] = $this->cekMaterai($data);
        }

        return $hasil;
    }

    // Validasi total vs penjumlahan item
    private function validasiTotal(array $data): array
    {
        $total = $data['total_pembayaran'] ?? null;
        $items = $data['daftar_item'] ?? [];

        if (!$total) {
            return [
                'status' => 'no_data',
                'message' => 'Total pembayaran tidak terdeteksi',
                'required' => true,
            ];
        }

        if (empty($items)) {
            return [
                'status' => 'info',
                'message' => 'Total: Rp ' . number_format($total, 0, ',', '.'),
                'required' => true,
            ];
        }

        // Hitung total dari items
        $totalHitung = 0;
        foreach ($items as $item) {
            $totalHitung += (float) ($item['subtotal'] ?? 0);
        }

        $selisih = abs($total - $totalHitung);
        $toleransi = max(100, $total * 0.01); // 1% atau minimal Rp 100
        $valid = $selisih <= $toleransi;

        return [
            'status' => $valid ? 'valid' : 'warning',
            'message' => $valid
                ? 'Total Rp ' . number_format($total, 0, ',', '.') . ' sesuai dengan items'
                : 'Selisih Rp ' . number_format($selisih, 0, ',', '.'),
            'required' => true,
        ];
    }

    // Validasi terbilang dengan angka
    private function validasiTerbilang(array $data): array
    {
        $total = $data['total_pembayaran'] ?? null;
        $terbilang = $data['terbilang'] ?? null;

        // Cari terbilang di raw text jika tidak ada
        if (!$terbilang && isset($data['struk_mentah'])) {
            foreach ($data['struk_mentah'] as $baris) {
                if (preg_match('/terbilang\s*[:\-]?\s*(.+)/i', $baris, $match)) {
                    $terbilang = trim($match[1]);
                    break;
                }
            }
        }

        if (!$terbilang) {
            return [
                'status' => 'no_data',
                'message' => 'Terbilang tidak ditemukan',
                'required' => true,
            ];
        }

        if (!$total) {
            return [
                'status' => 'no_data',
                'message' => 'Total tidak tersedia',
                'required' => true,
            ];
        }

        // Parse terbilang ke angka
        $nilaiTerbilang = $this->parseTerbilang($terbilang);

        if (!$nilaiTerbilang) {
            return [
                'status' => 'warning',
                'message' => 'Gagal membaca terbilang',
                'required' => true,
            ];
        }

        $selisih = abs($total - $nilaiTerbilang);
        $valid = $selisih <= 100;

        return [
            'status' => $valid ? 'valid' : 'warning',
            'message' => $valid ? 'Terbilang sesuai' : 'Terbilang tidak cocok',
            'required' => true,
        ];
    }

    // Validasi perhitungan PPN 11%
    private function validasiPPN(array $data): array
    {
        $total = $data['total_pembayaran'] ?? null;
        $ppn = $data['pajak']['ppn'] ?? null;

        if (!$total || !$ppn) {
            return [
                'status' => 'no_data',
                'message' => 'Data PPN tidak lengkap',
                'required' => true,
            ];
        }

        // Hitung DPP dan PPN yang seharusnya
        $dpp = $total - $ppn;
        $ppnSeharusnya = $dpp * 0.11;
        $selisih = abs($ppn - $ppnSeharusnya);
        $valid = $selisih <= max(100, $ppn * 0.02);

        return [
            'status' => $valid ? 'valid' : 'warning',
            'message' => $valid
                ? 'PPN 11% sesuai: Rp ' . number_format($ppn, 0, ',', '.')
                : 'PPN tidak sesuai perhitungan',
            'required' => true,
        ];
    }

    // Cek kebutuhan materai untuk transaksi > 5 juta
    private function cekMaterai(array $data): array
    {
        $total = $data['total_pembayaran'] ?? 0;

        if ($total < 5000000) {
            return [
                'status' => 'info',
                'message' => 'Tidak perlu materai (< Rp 5 juta)',
                'required' => false,
            ];
        }

        // Cari indikasi materai di teks
        $adaMaterai = false;
        if (isset($data['struk_mentah'])) {
            foreach ($data['struk_mentah'] as $baris) {
                if (preg_match('/materai|meterai|10\.?000/i', $baris)) {
                    $adaMaterai = true;
                    break;
                }
            }
        }

        return [
            'status' => $adaMaterai ? 'valid' : 'warning',
            'message' => $adaMaterai
                ? 'Materai terdeteksi'
                : 'Pastikan ada materai (transaksi > Rp 5 juta)',
            'required' => true,
        ];
    }

    // Parse teks terbilang menjadi angka
    private function parseTerbilang(string $terbilang): ?float
    {
        $teks = strtolower(trim($terbilang));
        $teks = preg_replace('/[^a-z\s]/', '', $teks);
        $teks = preg_replace('/\s+/', ' ', $teks);
        $teks = preg_replace('/\s*rupiah\s*$/', '', $teks);

        $kataKata = explode(' ', $teks);
        $hasil = 0;
        $current = 0;

        foreach ($kataKata as $kata) {
            if (isset($this->terbilangMap[$kata])) {
                $nilai = $this->terbilangMap[$kata];

                if ($kata === 'ratus') {
                    $current = ($current ?: 1) * 100;
                } elseif ($kata === 'ribu') {
                    $current = ($current ?: 1) * 1000;
                    $hasil += $current;
                    $current = 0;
                } elseif ($kata === 'juta') {
                    $current = ($current ?: 1) * 1000000;
                    $hasil += $current;
                    $current = 0;
                } else {
                    $current += $nilai;
                }
            } elseif ($kata === 'belas') {
                $current += 10;
            } elseif ($kata === 'puluh') {
                $current *= 10;
            }
        }

        $hasil += $current;
        return $hasil > 0 ? $hasil : null;
    }
}
