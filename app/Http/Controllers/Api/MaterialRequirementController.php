<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialRequirementController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['asset' => 'required|string']);

        $rows = DB::table('well_materials')
            ->join('rig_details', function ($join) {
                $join->on(DB::raw('TRIM(well_materials.well_name)'), '=', DB::raw('TRIM(rig_details.well_name)'))
                     ->where('rig_details.record_type', '=', 'planned');
            })
            ->where('well_materials.asset_name', $request->asset)
            ->whereNotNull('rig_details.start_date')
            ->selectRaw("
                well_materials.item_name as item_name,
                MAX(CASE WHEN well_materials.hptp = 'HPHT' THEN 1 ELSE 0 END) as is_hpht,
                DATE_FORMAT(rig_details.start_date, '%Y-%m') as month_key,
                DATE_FORMAT(rig_details.start_date, '%b %Y') as month_label,
                SUM(well_materials.quantity) as qty
            ")
            ->groupBy('well_materials.item_name', 'month_key', 'month_label')
            ->orderBy('month_key')
            ->orderBy('well_materials.item_name')
            ->get();

        return response()->json($rows);
    }
}
