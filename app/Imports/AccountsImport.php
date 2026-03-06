<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\Unit;

class AccountsImport
{
    public function import(string $filePath): int
    {
        $rows = [];

        // Baca file sebagai CSV
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Deteksi delimiter
            $firstLine = fgets($handle);
            rewind($handle);

            $delimiter = ',';
            if (strpos($firstLine, ';') !== false) {
                $delimiter = ';';
            } elseif (strpos($firstLine, "\t") !== false) {
                $delimiter = "\t";
            }

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        return $this->processRows($rows);
    }

    private function processRows(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        // Ambil header dari baris pertama
        $rawHeaders = $rows[0] ?? [];
        $headers = [];
        foreach ($rawHeaders as $h) {
            $headers[] = strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h)));
        }
        $headerMap = array_flip($headers);

        $imported = 0;

        // Loop mulai dari baris kedua (skip header)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Ambil data berdasarkan header
            $code = $this->getValue($row, $headerMap, ['kode_akun', 'code', 'kode']);
            $name = $this->getValue($row, $headerMap, ['nama_akun', 'name', 'nama']);

            if (!$code || !$name) {
                continue;
            }

            // Buat atau update akun
            $account = Account::updateOrCreate(
                ['code' => trim($code)],
                [
                    'name' => trim($name),
                    'type' => $this->mapType($this->getValue($row, $headerMap, ['jenis', 'type'])),
                    'description' => $this->getValue($row, $headerMap, ['keterangan', 'description']),
                    'is_active' => true,
                ]
            );

            // Handle kolom unit
            $unitCodes = $this->getValue($row, $headerMap, ['unit', 'kode_unit', 'units']);
            if ($unitCodes) {
                $unitCodesArray = array_map('trim', explode(',', $unitCodes));
                $unitIds = Unit::whereIn('code', $unitCodesArray)->pluck('id')->toArray();

                if (!empty($unitIds)) {
                    $account->units()->syncWithoutDetaching($unitIds);
                }
            }

            $imported++;
        }

        return $imported;
    }

    private function getValue(array $row, array $headerMap, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            if (isset($headerMap[$name]) && isset($row[$headerMap[$name]])) {
                $value = $row[$headerMap[$name]];
                return $value !== null && $value !== '' ? trim((string) $value) : null;
            }
        }
        return null;
    }

    private function mapType(?string $type): string
    {
        if (!$type) {
            return 'expense';
        }

        $type = strtolower(trim($type));

        $mapping = [
            'aset' => 'asset',
            'asset' => 'asset',
            'aktiva' => 'asset',
            'kewajiban' => 'liability',
            'liability' => 'liability',
            'hutang' => 'liability',
            'liabilitas' => 'liability',
            'ekuitas' => 'equity',
            'equity' => 'equity',
            'modal' => 'equity',
            'pendapatan' => 'revenue',
            'revenue' => 'revenue',
            'income' => 'revenue',
            'beban' => 'expense',
            'expense' => 'expense',
            'biaya' => 'expense',
        ];

        return $mapping[$type] ?? 'expense';
    }
}
