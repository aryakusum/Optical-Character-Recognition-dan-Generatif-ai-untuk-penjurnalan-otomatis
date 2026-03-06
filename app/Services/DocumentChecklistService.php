<?php

namespace App\Services;

// Service untuk validasi kelengkapan dokumen berdasarkan tipe
class DocumentChecklistService
{
    // Definisi checklist field per tipe dokumen
    protected array $checklists = [
        'kuitansi' => [
            ['field' => 'tanggal_transaksi', 'label' => 'Tanggal Transaksi', 'required' => true],
            ['field' => 'total_pembayaran', 'label' => 'Nominal/Jumlah', 'required' => true],
            ['field' => 'terbilang', 'label' => 'Terbilang', 'required' => true],
            ['field' => 'nama_toko', 'label' => 'Nama Penerima/Vendor', 'required' => true],
            ['field' => 'tanda_tangan', 'label' => 'Tanda Tangan', 'required' => true],
            ['field' => 'materai', 'label' => 'Materai (jika > 5jt)', 'required' => false],
        ],
        'invoice' => [
            ['field' => 'tanggal_transaksi', 'label' => 'Tanggal Invoice', 'required' => true],
            ['field' => 'nomor_resi', 'label' => 'Nomor Invoice', 'required' => true],
            ['field' => 'nama_toko', 'label' => 'Nama Vendor', 'required' => true],
            ['field' => 'daftar_item', 'label' => 'Rincian Item', 'required' => true],
            ['field' => 'total_pembayaran', 'label' => 'Total Tagihan', 'required' => true],
            ['field' => 'rekening_tujuan', 'label' => 'Rekening Pembayaran', 'required' => false],
        ],
        'struk' => [
            ['field' => 'tanggal_transaksi', 'label' => 'Tanggal Transaksi', 'required' => true],
            ['field' => 'nama_toko', 'label' => 'Nama Toko', 'required' => true],
            ['field' => 'daftar_item', 'label' => 'Daftar Item', 'required' => true],
            ['field' => 'total_pembayaran', 'label' => 'Total Pembayaran', 'required' => true],
        ],
        'nota' => [
            ['field' => 'tanggal_transaksi', 'label' => 'Tanggal', 'required' => true],
            ['field' => 'total_pembayaran', 'label' => 'Total', 'required' => true],
            ['field' => 'daftar_item', 'label' => 'Rincian Barang', 'required' => false],
        ],
        'faktur_pajak' => [
            ['field' => 'tanggal_transaksi', 'label' => 'Tanggal Faktur', 'required' => true],
            ['field' => 'nomor_faktur', 'label' => 'Nomor Seri Faktur', 'required' => true],
            ['field' => 'npwp_penjual', 'label' => 'NPWP Penjual', 'required' => true],
            ['field' => 'npwp_pembeli', 'label' => 'NPWP Pembeli', 'required' => true],
            ['field' => 'dpp', 'label' => 'Dasar Pengenaan Pajak', 'required' => true],
            ['field' => 'ppn', 'label' => 'PPN', 'required' => true],
        ],
    ];

    // Get checklist untuk tipe dokumen tertentu
    public function getChecklist(string $documentType): array
    {
        return $this->checklists[$documentType] ?? [];
    }

    // Validasi data terhadap checklist
    public function validate(array $extractedData, string $documentType): array
    {
        $checklist = $this->getChecklist($documentType);
        $results = [];
        $completeCount = 0;
        $requiredCount = 0;
        $requiredCompleteCount = 0;

        foreach ($checklist as $item) {
            $field = $item['field'];
            $hasValue = $this->hasValue($extractedData, $field);
            $isRequired = $item['required'];

            if ($isRequired) {
                $requiredCount++;
                if ($hasValue) {
                    $requiredCompleteCount++;
                }
            }

            if ($hasValue) {
                $completeCount++;
            }

            $results[] = [
                'field' => $field,
                'label' => $item['label'],
                'required' => $isRequired,
                'completed' => $hasValue,
                'value' => $this->getValue($extractedData, $field),
            ];
        }

        $isComplete = $requiredCompleteCount === $requiredCount;
        $completionPercentage = $requiredCount > 0
            ? round(($requiredCompleteCount / $requiredCount) * 100, 1)
            : 100;

        return [
            'document_type' => $documentType,
            'is_complete' => $isComplete,
            'completion_percentage' => $completionPercentage,
            'total_items' => count($checklist),
            'completed_items' => $completeCount,
            'required_items' => $requiredCount,
            'required_completed' => $requiredCompleteCount,
            'missing_required' => $requiredCount - $requiredCompleteCount,
            'items' => $results,
        ];
    }

    // Cek apakah field memiliki nilai
    private function hasValue(array $data, string $field): bool
    {
        if (!isset($data[$field])) {
            return false;
        }
        $value = $data[$field];
        if (is_null($value)) return false;
        if (is_string($value) && trim($value) === '') return false;
        if (is_array($value) && empty($value)) return false;
        return true;
    }

    // Ambil nilai field
    private function getValue(array $data, string $field)
    {
        return $data[$field] ?? null;
    }
}
