<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RigDetail;
use App\Services\RigDetailsImporter;
use Illuminate\Http\Request;

class PlanImportController extends Controller
{
    public function import(Request $request, RigDetailsImporter $importer)
    {
        $validated = $request->validate([
            'plan_name'   => 'required|string|max:100',
            'record_type' => 'required|in:planned,actual',
            'file'        => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $count = $importer->import(
            $request->file('file')->getRealPath(),
            $validated['plan_name'],
            $validated['record_type'],
            $request->user()->id
        );

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
