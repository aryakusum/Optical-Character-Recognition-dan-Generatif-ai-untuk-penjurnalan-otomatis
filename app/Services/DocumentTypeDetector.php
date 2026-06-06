<?php

namespace App\Services;

class DocumentTypeDetector
{
    protected array $patterns = [
        'kuitansi' => [
            'keywords' => ['kuitansi', 'kwitansi', 'sudah terima dari', 'telah menerima', 'terbilang', 'rupiah'],
            'weight' => 1.0,
        ],
        'invoice' => [
            'keywords' => ['invoice', 'faktur', 'inv no', 'invoice number', 'tagihan', 'jatuh tempo', 'due date'],
            'weight' => 1.0,
        ],
        'struk' => [
            'keywords' => ['struk', 'receipt', 'kasir', 'cashier', 'subtotal', 'total', 'change', 'kembalian'],
            'weight' => 0.8,
        ],
        'nota' => [
            'keywords' => ['nota', 'bon', 'nota tunai', 'nota pembelian'],
            'weight' => 0.7,
        ],
        'faktur_pajak' => [
            'keywords' => ['faktur pajak', 'nomor seri faktur', 'npwp', 'ppn', 'dpp'],
            'weight' => 1.0,
        ],
    ];

    protected array $requiredFields = [
        'kuitansi' => ['tanggal', 'nominal', 'terbilang', 'penerima', 'keterangan'],
        'invoice' => ['tanggal', 'nomor_invoice', 'nominal', 'vendor', 'item'],
        'struk' => ['tanggal', 'total', 'item'],
        'nota' => ['tanggal', 'total', 'item'],
        'faktur_pajak' => ['tanggal', 'nomor_faktur', 'npwp', 'dpp', 'ppn'],
    ];

    public function detect(string $ocrText): array
    {
        $textLower = strtolower($ocrText);
        $scores = [];

        foreach ($this->patterns as $type => $config) {
            $matchCount = 0;
            $matchedKeywords = [];

            foreach ($config['keywords'] as $keyword) {
                if (str_contains($textLower, strtolower($keyword))) {
                    $matchCount++;
                    $matchedKeywords[] = $keyword;
                }
            }

            if ($matchCount > 0) {
                $scores[$type] = [
                    'score' => ($matchCount / count($config['keywords'])) * $config['weight'],
                    'matched' => $matchedKeywords,
                    'total_keywords' => count($config['keywords']),
                ];
            }
        }

        if (empty($scores)) {
            return [
                'type' => 'unknown',
                'confidence' => 0,
                'required_fields' => [],
                'detection_details' => [],
            ];
        }

        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
        $bestType = array_key_first($scores);
        $bestScore = $scores[$bestType];

        return [
            'type' => $bestType,
            'confidence' => round($bestScore['score'] * 100, 1),
            'required_fields' => $this->requiredFields[$bestType] ?? [],
            'matched_keywords' => $bestScore['matched'],
            'all_scores' => $scores,
        ];
    }

    public function isValidDocument(string $type): bool
    {
        return isset($this->patterns[$type]);
    }

    public function getSupportedTypes(): array
    {
        return array_keys($this->patterns);
    }
}
