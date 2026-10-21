<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

// use App\Imports\RigDetailsImporter;

use App\Services\ActualRigDetailsImporter;
use App\Models\RigDetail;
use Illuminate\Http\Request;

 use App\Services\RigDetailsImporter;

class PlanImportController extends Controller
{
    public function import(Request $request, RigDetailsImporter $plannedImporter, ActualRigDetailsImporter $actualImporter)
    {
        $validated = $request->validate([
            'plan_name'   => 'required|string|max:100',
            'record_type' => 'required|in:planned,actual',
            'file'        => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $path = $request->file('file')->getRealPath();

        // if ($validated['record_type'] === 'actual') {
        //     $result = $actualImporter->import($path, $validated['plan_name'], $request->user()->id);

        //     return response()->json([
        //         'message' => 'Import successful',
        //         'rows_imported' => $result['wells_imported'],
        //         'detail' => sprintf(
        //             '%d wells from %d daily rows (%d duplicate day-entries merged). %s%s',
        //             $result['wells_imported'],
        //             $result['raw_rows_read'],
        //             $result['duplicate_day_rows'],
        //             $result['assets_left_blank'] ? "{$result['assets_left_blank']} well(s) left with no Asset. " : '',
        //             $result['types_left_blank'] ? "{$result['types_left_blank']} well(s) left with no Type." : ''
        //         ),
        //     ]);
        // }

        if ($validated['record_type'] === 'actual') {
            $result = $actualImporter->import($path, $validated['plan_name'], $request->user()->id);

            // $notes = [];
            // if ($result['assets_left_blank']) $notes[] = "{$result['assets_left_blank']} well(s) left with no Asset";
            // if (!empty($result['unmapped_modes'])) $notes[] = 'Unmapped Rig Modes: ' . implode(', ', $result['unmapped_modes']);
            $notes = [];
            if ($result['assets_left_blank']) $notes[] = "{$result['assets_left_blank']} well(s) left with no Asset";
            if (!empty($result['unmapped_modes'])) $notes[] = 'Unmapped Rig Modes: ' . implode(', ', $result['unmapped_modes']);
            if ($result['borrowed_days']) $notes[] = "{$result['borrowed_days']} A/B/Z/U day(s) took their type from a neighbouring day";
            if ($result['fallback_days']) $notes[] = "{$result['fallback_days']} day(s) had no usable neighbour and used the category default";


            if ($result['wellid_fallback_rows']) $notes[] = "{$result['wellid_fallback_rows']} row(s) had no Well No. and used the Wellid as the well name";
            if ($result['skipped_rows']) $notes[] = "{$result['skipped_rows']} row(s) skipped (no well name and no date)";


            return response()->json([
                'message' => 'Import successful',
                'rows_imported' => $result['segments_imported'],
                'detail' => sprintf(
                    '%d segments across %d wells, from %d daily rows.%s',
                    $result['segments_imported'],
                    $result['unique_wells'],
                    $result['raw_rows_read'],
                    $notes ? ' ' . implode('. ', $notes) . '.' : ''
                ),
            ]);
        }

        $count = $plannedImporter->import($path, $validated['plan_name'], $validated['record_type'], $request->user()->id);

        return response()->json([
            'message' => 'Import successful',
            'rows_imported' => $count,
        ]);
    }

    public function plans()
    {
        $plans = RigDetail::selectRaw('plan_name, record_type, COUNT(*) as total')
            ->groupBy('plan_name', 'record_type')
            ->orderBy('plan_name')
            ->get();

        return response()->json($plans);
    }
}
