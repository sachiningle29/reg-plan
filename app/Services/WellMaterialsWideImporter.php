<?php


namespace App\Services;

use App\Models\MaterialItem;
use App\Models\WellMaterial;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WellMaterialsWideImporter
{
    protected array $wellNameAliases = ['well_name', 'well', 'well name'];
    protected array $hptpAliases     = ['hptp', 'hptp_non_hptp', 'hp_tp', 'hpht', 'hpht_non_hpht', 'hp_ht'];

    // Anything here is recognized context, NEVER treated as a material item —
    // this is exactly the list that was missing start_date/finish_date/status,
    // which is what caused them to get registered as items previously.
    protected array $ignoreAliases = [
        'sl_no', 'sl_no_', 'input_type', 'type', 'well_type', 'wi_well',
        'meters', 'drilling_meterage', 'drilling_meters',
        'start_date', 'finish_date', 'status',
    ];

    public function import(string $filePath, string $assetName): array
    {
        $knownByNormalized = [];
        foreach (MaterialItem::pluck('item_name') as $name) {
            $knownByNormalized[$this->normalizeHeader($name)] = $name;
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $headerRow = $this->findHeaderRow($sheet, $highestColIndex, min($highestRow, 10));

        if (!$headerRow) {
            throw new \RuntimeException('Could not locate a header row containing a "Well Name" / "Well" column within the first 10 rows.');
        }

        $groupLabels = $headerRow > 1 ? $this->readGroupLabels($sheet, $highestColIndex, $headerRow - 1) : [];

        $wellNameCol = null;
        $hptpCol = null;
        $itemCols = [];
        $usedItemNames = [];
        $newItemNames = [];

        for ($col = 1; $col <= $highestColIndex; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $rawHeader = trim((string) $sheet->getCell($letter . $headerRow)->getValue());
            $normalized = $this->normalizeHeader($rawHeader);

            if ($normalized === '') continue;

            if (in_array($normalized, $this->wellNameAliases, true)) { $wellNameCol = $col; continue; }
            if (in_array($normalized, $this->hptpAliases, true)) { $hptpCol = $col; continue; }
            if (in_array($normalized, $this->ignoreAliases, true)) continue;

            $itemName = $knownByNormalized[$normalized] ?? $rawHeader;

            if (isset($usedItemNames[$itemName])) {
                $suffix = $groupLabels[$col] ?? ('Col ' . $letter);
                $itemName = "{$itemName} ({$suffix})";
            }
            $usedItemNames[$itemName] = true;
            $itemCols[$col] = $itemName;

            if (!isset($knownByNormalized[$normalized]) && !in_array($itemName, $newItemNames, true)) {
                $newItemNames[] = $itemName;
            }
        }

        if (!$wellNameCol) {
            throw new \RuntimeException('Found a header row but no "Well Name" / "Well" column in it.');
        }

        if (!empty($newItemNames)) {
            $nextOrder = (MaterialItem::max('sort_order') ?? 0) + 1;
            foreach ($newItemNames as $name) {
                MaterialItem::firstOrCreate(['item_name' => $name], ['sort_order' => $nextOrder++]);
            }
        }

        $batch = [];
        $imported = 0;
        $now = now();

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $wellName = trim((string) $this->cellValue($sheet, $wellNameCol, $row));
            if ($wellName === '') continue;

            $hptp = $hptpCol ? $this->normalizeHptp($this->cellValue($sheet, $hptpCol, $row)) : 'Non-HPHT';

            foreach ($itemCols as $col => $itemName) {
                $value = $this->cellValue($sheet, $col, $row);
                if ($value === null || $value === '' || !is_numeric($value)) continue;

                $batch[] = [
                    'asset_name' => $assetName, 'well_name' => $wellName, 'hptp' => $hptp,
                    'item_name' => $itemName, 'quantity' => (float) $value,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $imported++;

                if (count($batch) >= 200) { WellMaterial::insert($batch); $batch = []; }
            }
        }

        if (!empty($batch)) WellMaterial::insert($batch);

        return ['imported' => $imported, 'new_items' => $newItemNames];
    }

    protected function readGroupLabels($sheet, int $highestColIndex, int $groupRow): array
    {
        $labels = [];
        $current = null;
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $val = trim((string) $sheet->getCell($letter . $groupRow)->getValue());
            if ($val !== '') $current = $val;
            if ($current !== null) $labels[$col] = $current;
        }
        return $labels;
    }

    protected function findHeaderRow($sheet, int $highestColIndex, int $maxRow): ?int
    {
        for ($row = 1; $row <= $maxRow; $row++) {
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $header = $this->normalizeHeader((string) $sheet->getCell($letter . $row)->getValue());
                if (in_array($header, $this->wellNameAliases, true)) return $row;
            }
        }
        return null;
    }

    protected function cellValue($sheet, int $col, int $row)
    {
        $letter = Coordinate::stringFromColumnIndex($col);
        $cell = $sheet->getCell($letter . $row);
        if ($cell->getDataType() !== DataType::TYPE_FORMULA) return $cell->getValue();
        return $cell->getOldCalculatedValue() ?? $cell->getCalculatedValue();
    }

    protected function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }

    protected function normalizeHptp($value): string
    {
        $v = strtoupper(trim((string) $value));
        if ($v === '') return 'Non-HPHT';
        if (str_contains($v, 'NON')) return 'Non-HPHT';
        if (str_contains($v, 'HPHT') || str_contains($v, 'HPTP')) return 'HPHT';
        return 'Non-HPHT';
    }
}
