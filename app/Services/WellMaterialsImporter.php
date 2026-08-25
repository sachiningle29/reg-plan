<?php

namespace App\Services;

use App\Models\WellMaterial;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WellMaterialsImporter
{
    protected array $aliases = [
        'asset_name' => ['asset_name', 'asset', 'asset name'],
        'well_name'  => ['well_name', 'well', 'well name'],
        'hptp'       => ['hptp'],
        'item_name'  => ['item_name', 'item', 'items_name', 'material', 'materials', 'material_item', 'item name'],
    ];

    public function import(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $headingMap = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $header = $this->normalizeHeader((string) $sheet->getCell($letter . '1')->getValue());
            if ($canonical = $this->resolveAlias($header)) {
                $headingMap[$col] = $canonical;
            }
        }

        $batch = [];
        $imported = 0;
        $now = now();

        for ($row = 2; $row <= $highestRow; $row++) {
            $data = [];
            foreach ($headingMap as $col => $key) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $data[$key] = $this->extractCellValue($sheet->getCell($letter . $row));
            }

            if (empty($data['asset_name']) || empty($data['well_name'])) {
                continue;
            }

            $batch[] = [
                'asset_name' => trim($data['asset_name']),
                'well_name'  => trim($data['well_name']),
                'hptp'       => $this->normalizeHptp($data['hptp'] ?? null),
                'item_name'  => trim($data['item_name'] ?? 'Unspecified'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $imported++;

            if (count($batch) >= 200) {
                WellMaterial::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            WellMaterial::insert($batch);
        }

        return $imported;
    }

    protected function extractCellValue($cell)
    {
        if ($cell->getDataType() !== DataType::TYPE_FORMULA) {
            return $cell->getValue();
        }
        return $cell->getOldCalculatedValue() ?? $cell->getCalculatedValue();
    }

    protected function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }

    protected function resolveAlias(string $header): ?string
    {
        foreach ($this->aliases as $canonical => $variants) {
            if (in_array($header, $variants, true)) {
                return $canonical;
            }
        }
        return null;
    }

    protected function normalizeHptp($value): string
    {
        $v = strtoupper(trim((string) $value));
        return str_contains($v, 'NON') ? 'Non-HPTP' : ($v === 'HPTP' ? 'HPTP' : 'Non-HPTP');
    }
}
