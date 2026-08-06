<?php

namespace App\Services;

use App\Models\RigDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class RigDetailsImporter
{
    protected array $aliases = [
        'rig_name'    => ['rig_name', 'rig'],
        'asset'       => ['asset', 'field', 'field_asset', 'asset_name'],
        'platform'    => ['platform', 'platform_name'],
        'well_name'   => ['well_name', 'well', 'well_no'],
        'type'        => ['type', 'well_type'],
        'meters'      => ['meters', 'metres', 'depth_meters'],
        'wi_well'     => ['wi_well', 'wiwell', 'wi_status'],
        'days'        => ['days', 'no_of_days', 'duration_days'],
        'status'      => ['status'],
        'activity'    => ['activity'],
        'start_date'  => ['start', 'start_date', 'planned_start'],
        'finish_date' => ['finish', 'finish_date', 'planned_finish', 'end_date'],
        'oil_gain'    => ['oil_gain', 'oilgain'],
        'gas_gain'    => ['gas_gain', 'gasgain'],
    ];

    public function import(string $filePath, string $planName, string $recordType, int $userId): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        // Map each column to a canonical field name, based on row 1 headers
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

            if (empty($data['rig_name'])) {
                continue;
            }

            $batch[] = [
                'plan_name'   => $planName,
                'record_type' => $recordType,
                'rig_name'    => $data['rig_name'] ?? null,
                'asset'       => $data['asset'] ?? null,
                'platform'    => $data['platform'] ?? null,
                'well_name'   => $data['well_name'] ?? null,
                'type'        => $data['type'] ?? null,
                'meters'      => $this->parseNumber($data['meters'] ?? null),
                'wi_well'     => $data['wi_well'] ?? null,
                'days'        => $this->parseNumber($data['days'] ?? null),
                'status'      => $data['status'] ?? null,
                'activity'    => $data['activity'] ?? null,
                'start_date'  => $this->parseDate($data['start_date'] ?? null),
                'finish_date' => $this->parseDate($data['finish_date'] ?? null),
                'oil_gain'    => $this->parseNumber($data['oil_gain'] ?? null),
                'gas_gain'    => $this->parseNumber($data['gas_gain'] ?? null),
                'imported_by' => $userId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
            $imported++;

            if (count($batch) >= 200) {
                RigDetail::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            RigDetail::insert($batch);
        }

        return $imported;
    }

    protected function extractCellValue($cell)
    {
        if ($cell->getDataType() !== DataType::TYPE_FORMULA) {
            return $cell->getValue();
        }

        // Read the value Excel already calculated and saved — never ask
        // PhpSpreadsheet to recalculate, since it can't evaluate Excel
        // Table structured references (Table33[[#This Row],[...]]).
        $cached = $cell->getOldCalculatedValue();
        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable $e) {
            Log::warning('Formula could not be evaluated', [
                'cell' => $cell->getCoordinate(),
                'formula' => $cell->getValue(),
            ]);
            return null;
        }
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

    protected function parseDate($value)
    {
        if (empty($value)) return null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseNumber($value)
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? $value : null;
    }
}
