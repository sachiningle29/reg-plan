<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RigDetail;
use Illuminate\Http\Request;

class RigDetailController extends Controller
{
    public function index(Request $request)
    {
        $query = RigDetail::query()
            ->whereNotIn('platform', ['DEHIRE', 'HIRE']);

        if ($request->filled('plan_name')) {
            $query->where('plan_name', $request->plan_name);
        }

        if ($request->filled('record_type')) {
            $query->where('record_type', $request->record_type);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('rig_name', 'like', "%{$term}%")
                  ->orWhere('asset', 'like', "%{$term}%")
                  ->orWhere('well_name', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('start_date')->paginate(15)->withQueryString();
    }

    public function forGantt(Request $request)
    {
        $query = RigDetail::query()
            // ->whereNotIn('platform', ['DEHIRE', 'HIRE'])
            ->whereNotNull('start_date')
            ->whereNotNull('finish_date');

        if ($request->filled('plan_name')) {
            $query->where('plan_name', $request->plan_name);
        }

        if ($request->filled('record_type')) {
            $query->where('record_type', $request->record_type);
        }

        return $query->orderBy('rig_name')->orderBy('start_date')->get([
            'id', 'rig_name', 'asset', 'platform', 'well_name',
            'status', 'activity', 'wi_well', 'days', 'start_date', 'finish_date',
        ]);
    }
}
