<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RigDetail;
use App\Models\WellMaterial;
use App\Services\WellMaterialsWideImporter;
use Illuminate\Http\Request;

class WellMaterialController extends Controller
{
    // --- Filters (Asset / Wells) — unchanged, per your request ---

    // public function assets()
    // {
    //     return WellMaterial::query()
    //         ->select('asset_name')->distinct()
    //         ->orderBy('asset_name')
    //         ->pluck('asset_name');
    // }

    // public function wells(Request $request)
    // {
    //     $query = WellMaterial::query()->select('well_name')->distinct();

    //     if ($request->filled('asset')) {
    //         $query->where('asset_name', $request->asset);
    //     }

    //     return $query->orderBy('well_name')->pluck('well_name');
    // }


    public function assets()
    {
        return RigDetail::query()
            ->where('record_type', 'planned')
            ->whereNotNull('asset')
            ->distinct()
            ->orderBy('asset')
            ->pluck('asset');
    }

    public function wells(Request $request)
    {
        $query = RigDetail::query()
            ->where('record_type', 'planned')
            ->whereNotNull('well_name');

        if ($request->filled('asset')) {
            $query->where('asset', $request->asset);
        }

        return $query->select('well_name')->distinct()->orderBy('well_name')->pluck('well_name');
    }

    // --- Main report (joined against rig_details, planned only) ---

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
                'well_materials.quantity',
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

    // --- Template download source (from rig_details, so it works even before any materials exist) ---

    public function rigAssets()
    {
        return RigDetail::query()
            ->whereNotNull('asset')
            ->distinct()
            ->orderBy('asset')
            ->pluck('asset');
    }

    // public function template(Request $request)
    // {
    //     $request->validate(['asset' => 'required|string']);

    //     $rows = RigDetail::query()
    //         ->where('asset', $request->asset)
    //         ->where('record_type', 'planned')
    //         ->whereNotNull('well_name')
    //         ->orderBy('well_name')
    //         ->orderByDesc('start_date')
    //         ->get(['well_name', 'type', 'wi_well', 'meters']);

    //     $unique = $rows->unique('well_name')->values();

    //     return $unique->map(fn ($r) => [
    //         'well_name' => $r->well_name,
    //         'type'      => $r->type,
    //         'wi_well'   => $r->wi_well,
    //         'meters'    => $r->meters,
    //     ]);
    // }

    public function template(Request $request)
    {
        $request->validate(['asset' => 'required|string']);

        $rows = RigDetail::query()
            ->where('asset', $request->asset)
            ->where('record_type', 'planned')
            ->whereNotNull('well_name')
            ->orderBy('well_name')
            ->orderByDesc('start_date')
            ->get(['well_name', 'type', 'wi_well', 'meters', 'start_date']);

        $unique = $rows->unique('well_name')->values();

        return $unique->map(fn ($r) => [
            'well_name'  => $r->well_name,
            'type'       => $r->type,
            'wi_well'    => $r->wi_well,
            'meters'     => $r->meters,
            'start_date' => $r->start_date,
        ]);
    }

    // --- Wide-format import ---

    // public function import(Request $request, WellMaterialsWideImporter $importer)
    // {
    //     $validated = $request->validate([
    //         'asset'   => 'required|string|max:255',
    //         'file'    => 'required|file|mimes:xlsx,xls|max:10240',
    //         'replace' => 'nullable|boolean',
    //     ]);

    //     if ($request->boolean('replace')) {
    //         WellMaterial::where('asset_name', $validated['asset'])->delete();
    //     }

    //     $count = $importer->import($request->file('file')->getRealPath(), $validated['asset']);

    //     return response()->json([
    //         'message' => 'Import successful',
    //         'rows_imported' => $count,
    //     ]);
    // }

    public function import(Request $request, WellMaterialsWideImporter $importer)
    {
        $validated = $request->validate([
            'asset'   => 'required|string|max:255',
            'file'    => 'required|file|mimes:xlsx,xls|max:10240',
            'replace' => 'nullable|boolean',
        ]);

        if ($request->boolean('replace')) {
            WellMaterial::where('asset_name', $validated['asset'])->delete();
        }

        $result = $importer->import($request->file('file')->getRealPath(), $validated['asset']);

        return response()->json([
            'message' => 'Import successful',
            'rows_imported' => $result['imported'],
            'new_items' => $result['new_items'],
        ]);
    }
}
