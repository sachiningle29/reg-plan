<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WellMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


use App\Services\WellMaterialsImporter;

class WellMaterialController extends Controller
{
    // // The shared starting point for every endpoint below — one definition
    // // of "how materials match to planned rig work" that can't drift out
    // // of sync between the dropdown filters and the main table.
    // protected function baseQuery(Request $request)
    // {
    //     $query = DB::table('well_materials as wm')
    //         ->join('rig_details as rd', 'rd.well_name', '=', 'wm.well_name')
    //         ->where('rd.record_type', 'planned');

    //     if ($request->filled('asset')) {
    //         $query->where('wm.asset_name', $request->asset);
    //     }
    //     if ($request->filled('well')) {
    //         $query->where('wm.well_name', $request->well);
    //     }
    //     if ($request->filled('quarters')) {
    //         $quarters = $request->input('quarters');
    //         $query->where(function ($q) use ($quarters) {
    //             foreach ($quarters as $range) {
    //                 if (!empty($range['from']) && !empty($range['to'])) {
    //                     $q->orWhereBetween('rd.start_date', [$range['from'], $range['to']]);
    //                 }
    //             }
    //         });
    //     }

    //     return $query;
    // }

    // public function assets()
    // {
    //     return WellMaterial::query()->select('asset_name')->distinct()->orderBy('asset_name')->pluck('asset_name');
    // }

    // public function wells(Request $request)
    // {
    //     $query = WellMaterial::query()->select('well_name')->distinct();
    //     if ($request->filled('asset')) {
    //         $query->where('asset_name', $request->asset);
    //     }
    //     return $query->orderBy('well_name')->pluck('well_name');
    // }

    // public function dateRange(Request $request)
    // {
    //     // Ignores the quarters filter deliberately — this tells the frontend
    //     // the full available date span, so it can offer every valid quarter
    //     // as an option, not just the ones already selected.
    //     $request2 = $request->except('quarters');
    //     return response()->json(
    //         $this->baseQuery(new Request($request2))
    //             ->selectRaw('MIN(rd.start_date) as min_date, MAX(rd.start_date) as max_date')
    //             ->first()
    //     );
    // }

    // public function index(Request $request)
    // {
    //     return $this->baseQuery($request)
    //         ->select([
    //             'wm.asset_name as asset',
    //             'wm.well_name',
    //             'wm.hptp',
    //             'rd.type',
    //             'wm.item_name',
    //             'rd.meters',
    //             'rd.start_date',
    //         ])
    //         ->orderBy('rd.start_date')
    //         ->paginate(15)
    //         ->withQueryString();
    // }

    //  public function assets()
    // {
    //     return response()->json(
    //         WellMaterial::query()->select('asset_name')->distinct()->orderBy('asset_name')->pluck('asset_name')
    //     );
    // }

    // public function wells(Request $request)
    // {
    //     $query = WellMaterial::query()->select('well_name')->distinct();

    //     if ($request->filled('asset')) {
    //         $query->where('asset_name', $request->asset);
    //     }

    //     return response()->json($query->orderBy('well_name')->pluck('well_name'));
    // }

    // public function dateRange(Request $request)
    // {
    //     $range = $this->baseJoin($request)
    //         ->selectRaw('MIN(rig_details.start_date) as min_date, MAX(rig_details.start_date) as max_date')
    //         ->first();

    //     return response()->json($range);
    // }

    // public function consumables(Request $request)
    // {
    //     $query = $this->baseJoin($request)->select([
    //         'well_materials.asset_name as asset',
    //         'well_materials.well_name',
    //         'well_materials.hptp',
    //         'rig_details.type as types',
    //         'well_materials.item_name',
    //         'rig_details.meters',
    //         'rig_details.start_date',
    //     ]);

    //     if ($request->filled('periods')) {
    //         $periods = json_decode($request->input('periods'), true) ?: [];
    //         if (!empty($periods)) {
    //             $query->where(function ($q) use ($periods) {
    //                 foreach ($periods as $p) {
    //                     if (!empty($p['from']) && !empty($p['to'])) {
    //                         $q->orWhereBetween('rig_details.start_date', [$p['from'], $p['to']]);
    //                     }
    //                 }
    //             });
    //         }
    //     }

    //     return $query->orderBy('rig_details.start_date')->paginate(15)->withQueryString();
    // }

    // // The single shared query: well_materials joined to rig_details,
    // // restricted to Planned records only, with optional asset/well filters.
    // protected function baseJoin(Request $request)
    // {
    //     $query = DB::table('well_materials')
    //         ->join('rig_details', 'rig_details.well_name', '=', 'well_materials.well_name')
    //         ->where('rig_details.record_type', 'planned')
    //         ->whereNotNull('rig_details.start_date');

    //     if ($request->filled('asset')) {
    //         $query->where('well_materials.asset_name', $request->asset);
    //     }
    //     if ($request->filled('well')) {
    //         $query->where('well_materials.well_name', $request->well);
    //     }

    //     return $query;
    // }

    public function assets()
    {
        return WellMaterial::query()
            ->select('asset_name')->distinct()
            ->orderBy('asset_name')
            ->pluck('asset_name');
    }

    public function wells(Request $request)
    {
        $query = WellMaterial::query()->select('well_name')->distinct();

        if ($request->filled('asset')) {
            $query->where('asset_name', $request->asset);
        }

        return $query->orderBy('well_name')->pluck('well_name');
    }

    public function report(Request $request)
    {
        $query = WellMaterial::query()
            ->join('rig_details', function ($join) {
                $join->on('well_materials.well_name', '=', 'rig_details.well_name')
                     ->where('rig_details.record_type', '=', 'planned');
            })
            ->select([
                'well_materials.id as material_id',
                'well_materials.asset_name',
                'well_materials.well_name',
                'well_materials.hptp',
                'well_materials.item_name',
                'rig_details.type',
                'rig_details.meters',

                'rig_details.wi_well',
                'rig_details.status',

                'rig_details.start_date',


                'rig_details.finish_date',
            ]);

        if ($request->filled('asset')) {
            $query->where('well_materials.asset_name', $request->asset);
        }

        if ($request->filled('well_names')) {
            $query->whereIn('well_materials.well_name', (array) $request->well_names);
        }

        return $query
            ->orderBy('well_materials.well_name')
            ->orderBy('rig_details.start_date')
            ->get();
    }





    public function import(Request $request, WellMaterialsImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $count = $importer->import($request->file('file')->getRealPath());

        return response()->json([
            'message' => 'Import successful',
            'rows_imported' => $count,
        ]);
    }


}
