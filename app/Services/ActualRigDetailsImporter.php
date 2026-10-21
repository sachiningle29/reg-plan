<?php

namespace App\Services;

use App\Models\RigDetail;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ActualRigDetailsImporter
{
    protected array $aliases = [
        'date'            => ['date'],
        'well_no'         => ['well_no', 'well_no_', 'well_number'],
        'wellid'          => ['wellid', 'well_id'],
        'rig_name'        => ['rig_name'],
        'rig_mode'        => ['rig_mode'],
        'well_category'   => ['well_category'],
        'daily_meterage'  => ['daily_mete', 'daily_meterage', 'daily_meters'],
        'asset'           => ['asset'],
        'completion_date' => ['well_complition_date', 'well_completion_date'],
    ];

    // // Modes that mean the same thing in every category.
    // // (Capital Repair: C -> CAP REP and O -> OOC come from this same table.)
    // protected array $modeToType = [
    //     'L' => 'ST', 'Y' => 'ST', 'S' => 'ST', 'V' => 'ST',
    //     'W' => 'WO', 'X' => 'WO',
    //     'R' => 'Drilling', 'D' => 'Drilling', 'P' => 'Drilling',
    //     'C' => 'CAP REP',
    //     'O' => 'OOC',
    //     'T' => 'OOC',
    // ];


        // Rig Moved: these modes become type "RM" for actual records, so rig moves
    // show on the Gantt chart just like they do for planned data.
    protected array $rigMoveModes = ['Y', 'X', 'R'];

    // true  = a Y/X/R day is RM even on an Exploratory well
    // false = Exploratory wells keep EXP for every mode (the earlier rule)
    protected bool $rigMoveOverridesExploratory = true;

    protected array $modeToType = [
        'L' => 'ST', 'S' => 'ST', 'V' => 'ST',
        'W' => 'WO',
        'D' => 'Drilling', 'P' => 'Drilling',
        'C' => 'CAP REP',
        'O' => 'OOC',
        'T' => 'OOC',
    ];



    // These modes carry no type of their own — they borrow from a
    // neighbouring day of the same well.
    protected array $ambiguousModes = ['A', 'B', 'Z', 'U'];




    // Start a new phase when more than this many days pass between logged days.
    // 1 = any missing day splits the phase.
    protected int $maxGapDays = 1;





    // Last-resort type for a well whose every day is ambiguous.
    protected array $categoryDefaultType = [
        'Development'                    => 'DEV',
        'Capital Repair & Refurbishment' => 'CAP REP',
    ];

    protected array $unmappedModes = [];
    protected array $skippedAssetWells = [];
    protected int $borrowedDays = 0;
    protected int $fallbackDays = 0;

    public function import(string $filePath, string $planName, int $userId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $colMap = $this->mapColumns($sheet, $highestColIndex);
        foreach (['date', 'well_no'] as $required) {
            if (!isset($colMap[$required])) {
                throw new \RuntimeException("Could not find a \"{$required}\" column in the uploaded file.");
            }
        }

        // Pass 1: read every daily row.
        $rawRows = [];
        $skippedRows = 0;
        $wellidFallbackRows = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $wellNo = trim((string) $this->cell($sheet, $colMap, 'well_no', $row));
            $wellid = trim((string) $this->cell($sheet, $colMap, 'wellid', $row));

            // Well No. is the well name. When it is blank, the Wellid is the
            // next best identifier — dropping these rows silently would lose
            // whole wells (about a quarter of the rows in your file).
            if ($wellNo === '') {
                if ($wellid === '') { $skippedRows++; continue; }
                $wellNo = $wellid;
                $wellidFallbackRows++;
            }

            $date = $this->parseDate($this->cell($sheet, $colMap, 'date', $row));
            if (!$date) { $skippedRows++; continue; }

            $rawRows[] = [
                'well_no'         => $wellNo,
                'date'            => $date,
                'wellid'          => $wellid,
                'rig_name'        => trim((string) $this->cell($sheet, $colMap, 'rig_name', $row)),
                'rig_mode'        => trim((string) $this->cell($sheet, $colMap, 'rig_mode', $row)),
                'well_category'   => trim((string) $this->cell($sheet, $colMap, 'well_category', $row)),
                'daily_meterage'  => (float) ($this->cell($sheet, $colMap, 'daily_meterage', $row) ?: 0),
                'asset'           => trim((string) $this->cell($sheet, $colMap, 'asset', $row)),
                'completion_date' => $this->parseDate($this->cell($sheet, $colMap, 'completion_date', $row)),
            ];
        }

        // Pass 2: collapse exact (well, date) duplicates. Prefer whichever
        // duplicate carries a completion date, for every field.
        $byWellDate = [];
        foreach ($rawRows as $r) {
            $dateKey = $r['date']->format('Y-m-d');
            $existing = $byWellDate[$r['well_no']][$dateKey] ?? null;

            if (!$existing) {
                $byWellDate[$r['well_no']][$dateKey] = $r;
                continue;
            }
            $existing['daily_meterage'] = max($existing['daily_meterage'], $r['daily_meterage']);
            if (!$existing['completion_date'] && $r['completion_date']) {
                $existing = array_merge($existing, [
                    'wellid' => $r['wellid'], 'rig_name' => $r['rig_name'], 'rig_mode' => $r['rig_mode'],
                    'well_category' => $r['well_category'], 'asset' => $r['asset'],
                    'completion_date' => $r['completion_date'],
                ]);
            }
            $byWellDate[$r['well_no']][$dateKey] = $existing;
        }

        // Pass 3: per well — sort days, classify each day, split into segments
        // whenever the type changes.
        $batch = [];
        $now = now();
        $segmentCount = 0;

        foreach ($byWellDate as $wellNo => $days) {
            $sorted = array_values($days);
            usort($sorted, fn ($a, $b) => $a['date'] <=> $b['date']);

            $wellHasCompletion = collect($sorted)->contains(fn ($d) => $d['completion_date'] !== null);

            $dayTypes = $this->classifyDays($sorted);

            // $segments = [];
            // $current = null;

            // foreach ($sorted as $i => $day) {
            //     $type = $dayTypes[$i];

            //     if ($current === null || $current['type'] !== $type) {
            //         if ($current !== null) $segments[] = $current;
            //         $current = ['type' => $type, 'days' => [$day]];
            //     } else {
            //         $current['days'][] = $day;
            //     }
            // }
            // if ($current !== null) $segments[] = $current;


            $segments = [];
            $current = null;

            foreach ($sorted as $i => $day) {
                $type = $dayTypes[$i];
                $gap = $current === null ? 0 : $current['last']->diffInDays($day['date'], true);

                if ($current === null || $current['type'] !== $type || $gap > $this->maxGapDays) {
                    if ($current !== null) $segments[] = $current;
                    $current = ['type' => $type, 'days' => [$day], 'last' => $day['date']];
                } else {
                    $current['days'][] = $day;
                    $current['last'] = $day['date'];
                }
            }
            if ($current !== null) $segments[] = $current;










            $lastIndex = count($segments) - 1;

            foreach ($segments as $idx => $segment) {
                $segDays = $segment['days'];
                $minDate = min(array_column($segDays, 'date'));
                $maxDate = max(array_column($segDays, 'date'));
                $meters = array_sum(array_column($segDays, 'daily_meterage'));
                $representative = end($segDays);

                $asset = $this->resolveAsset($representative['well_category'], $representative['asset']);
                if ($asset === null) $this->skippedAssetWells[] = $wellNo;

                $batch[] = [
                    'plan_name'   => $planName,
                    'record_type' => 'actual',
                    'rig_name'    => $representative['rig_name'] ?: null,
                    'asset'       => $asset,
                    'platform'    => $this->extractPlatform($representative['wellid'] ?: $wellNo),
                    'well_name'   => $wellNo,
                    'type'        => $segment['type'],
                    'meters'      => $meters ?: null,
                    'wi_well'     => null,
                    'days'        => $minDate->diffInDays($maxDate),
                    'status'      => ($idx === $lastIndex && $wellHasCompletion) ? 'C' : null,
                    'activity'    => $segment['type'],
                    'start_date'  => $minDate->format('Y-m-d'),
                    'finish_date' => $maxDate->format('Y-m-d'),
                    'oil_gain'    => null,
                    'gas_gain'    => null,
                    'imported_by' => $userId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                $segmentCount++;
            }
        }

        RigDetail::insert($batch);

        return [
            'segments_imported'    => $segmentCount,
            'unique_wells'         => count($byWellDate),
            'raw_rows_read'        => count($rawRows),
            'skipped_rows'         => $skippedRows,
            'wellid_fallback_rows' => $wellidFallbackRows,
            'assets_left_blank'    => count(array_unique($this->skippedAssetWells)),
            'unmapped_modes'       => array_keys($this->unmappedModes),
            'borrowed_days'        => $this->borrowedDays,
            'fallback_days'        => $this->fallbackDays,
        ];
    }

    // Returns one type per day (same order as $days, which must already be
    // sorted by date and belong to a single well).
    protected function classifyDays(array $days): array
    {
        $n = count($days);
        $modes = array_map(fn ($d) => strtoupper(trim($d['rig_mode'])), $days);

        // A day is "usable" when its rig mode is specific (not blank, not A/B/Z/U).
        $usable = array_map(
            fn ($m) => $m !== '' && !in_array($m, $this->ambiguousModes, true),
            $modes
        );

        // Nearest usable day strictly before and strictly after each position.
        $prevUsable = array_fill(0, $n, null);
        $last = null;
        for ($i = 0; $i < $n; $i++) {
            $prevUsable[$i] = $last;
            if ($usable[$i]) $last = $i;
        }

        $nextUsable = array_fill(0, $n, null);
        $next = null;
        for ($i = $n - 1; $i >= 0; $i--) {
            $nextUsable[$i] = $next;
            if ($usable[$i]) $next = $i;
        }

        $types = [];
        foreach ($days as $i => $day) {
            $category = trim((string) $day['well_category']);

            // // Exploratory: every day is EXP, whatever the mode.
            // if ($category === 'Exploratory') {
            //     $types[$i] = 'EXP';
            //     continue;
            // }

                        // Rig move: Y / X / R.
            if ($this->isRigMove($modes[$i], $category)) {
                $types[$i] = 'RM';
                continue;
            }

            // Exploratory: every other day is EXP, whatever the mode.
            if ($category === 'Exploratory') {
                $types[$i] = 'EXP';
                continue;
            }

            if ($usable[$i]) {
                $type = $this->resolveType($category, $modes[$i]);
                if ($type === null) $this->unmappedModes[$modes[$i]] = true;
                $types[$i] = $type;
                continue;
            }

            // A/B/Z/U or blank: borrow the mode of the nearest usable day
            // (backward first, then forward) and apply this day's category rules.
            $source = $prevUsable[$i] ?? $nextUsable[$i];

            if ($source !== null) {
                $types[$i] = $this->resolveType($category, $modes[$source]);
                $this->borrowedDays++;
            } else {
                // Whole well is A/B/Z/U — nothing to borrow. Use the category default.
                $types[$i] = $this->categoryDefaultType[$category] ?? null;
                $this->fallbackDays++;
            }
        }

        return $types;
    }

    protected function resolveType(?string $category, ?string $mode): ?string
    {
        $category = trim((string) $category);
        $mode = strtoupper(trim((string) $mode));

        if ($this->isRigMove($mode, $category)) return 'RM';

        if ($category === 'Exploratory') return 'EXP';

        // Development: only D, P, O become DEV.
        if ($category === 'Development' && in_array($mode, ['D', 'P', 'O'], true)) {
            return 'DEV';
        }

        return $this->modeToType[$mode] ?? null;
    }



    protected function isRigMove(string $mode, string $category): bool
    {
        return in_array($mode, $this->rigMoveModes, true)
            && ($category !== 'Exploratory' || $this->rigMoveOverridesExploratory);
    }

    protected function extractPlatform(string $wellid): ?string
    {
        if ($wellid === '') return null;
        return str_contains($wellid, '#') ? strstr($wellid, '#', true) : $wellid;
    }

    protected function resolveAsset(?string $category, ?string $assetValue): ?string
    {
        if (trim((string) $category) === 'Exploratory') return 'EXP';
        $assetValue = trim((string) $assetValue);
        return $assetValue !== '' ? $assetValue : null;
    }

    protected function mapColumns($sheet, int $highestColIndex): array
    {
        $map = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $header = $this->normalizeHeader((string) $sheet->getCell($letter . '1')->getValue());
            foreach ($this->aliases as $canonical => $variants) {
                if (in_array($header, $variants, true)) $map[$canonical] = $col;
            }
        }
        return $map;
    }

    protected function cell($sheet, array $colMap, string $key, int $row)
    {
        if (!isset($colMap[$key])) return null;
        $letter = Coordinate::stringFromColumnIndex($colMap[$key]);
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

    protected function parseDate($value): ?Carbon
    {
        if (empty($value)) return null;
        if ($value instanceof \DateTimeInterface) return Carbon::instance($value);
        if (is_numeric($value)) {
            try { return Carbon::instance(ExcelDate::excelToDateTimeObject($value)); }
            catch (\Throwable) { return null; }
        }
        try { return Carbon::parse((string) $value); }
        catch (\Throwable) { return null; }
    }
}
