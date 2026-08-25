<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RigDetail;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class RigDetailController extends Controller
{
    public function index(Request $request)
    {
        $query = RigDetail::query();   // <-- no longer wrapped in excludeOffHire()

        if ($request->filled('plan_name')) $query->where('plan_name', $request->plan_name);
        if ($request->filled('record_type')) $query->where('record_type', $request->record_type);
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
        $query = RigDetail::query()   // <-- no longer wrapped in excludeOffHire()
            ->whereNotNull('start_date')
            ->whereNotNull('finish_date');

        if ($request->filled('plan_name')) $query->where('plan_name', $request->plan_name);
        if ($request->filled('record_type')) $query->where('record_type', $request->record_type);

        // return $query->orderBy('rig_name')->orderBy('start_date')->get([
        //     'id', 'rig_name', 'asset', 'platform', 'well_name', 'record_type',
        //     'status', 'activity', 'wi_well', 'days', 'start_date', 'finish_date',
        // ]);

        return $query->orderBy('rig_name')->orderBy('finish_date')->get([
            'id', 'rig_name', 'asset', 'platform', 'well_name', 'record_type',
            'status', 'activity', 'wi_well', 'days', 'start_date', 'finish_date',
        ]);
    }


     //added logic for update actual records on 13-08-26

   public function update(Request $request, RigDetail $rigDetail)
    {
        $validated = $request->validate([
            'rig_name'    => 'required|string|max:255',
            'asset'       => 'nullable|string|max:255',
            'platform'    => 'nullable|string|max:255',
            'well_name'   => 'nullable|string|max:255',
            'type'        => 'nullable|string|max:50',
            'meters'      => 'nullable|numeric',
            'wi_well'     => 'nullable|string|max:50',
            'days'        => 'nullable|integer',
            'status'      => 'nullable|string|max:50',
            'activity'    => 'nullable|string|max:50',
            'start_date'  => 'nullable|date',
            'finish_date' => 'nullable|date',
            'oil_gain'    => 'nullable|numeric',
            'gas_gain'    => 'nullable|numeric',
        ]);

        $rigDetail->update($validated);

        return response()->json(['message' => 'Record updated', 'record' => $rigDetail]);
    }
    //end

// //add logic for unique rig and proper finsh date
//     public function summary(Request $request)
//     {
//         $query = RigDetail::query()
//             ->whereNotNull('start_date')
//             ->whereNotNull('finish_date')

//             ->where('platform', '!=', 'DEHIRE'); // Exclude DEHIRE records


//         if ($request->filled('plan_name')) {
//             $query->where('plan_name', $request->plan_name);
//         }
//         if ($request->filled('record_type')) {
//             $query->where('record_type', $request->record_type);
//         }
//         if ($request->filled('search')) {
//             $term = $request->search;
//             $query->where(function ($q) use ($term) {
//                 $q->where('rig_name', 'like', "%{$term}%")
//                 ->orWhere('asset', 'like', "%{$term}%")
//                 ->orWhere('well_name', 'like', "%{$term}%");
//             });
//         }

//         return $query
//             ->selectRaw('rig_name, MIN(start_date) as start_date, MAX(finish_date) as finish_date, COUNT(*) as segments')
//             ->groupBy('rig_name')
//             ->orderBy('rig_name')
//             ->get();
//     }
//     //end logic

    public function summary(Request $request)
    {
        $query = RigDetail::query()
            ->whereNotNull('start_date')
            ->whereNotNull('finish_date')
            //->where('platform', '!=', 'DEHIRE'); // Exclude DEHIRE records
            ->whereNotIn('platform', ['DEHIRE', 'HIRE']); // Exclude DEHIRE, HIRE records

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

        $summary = $query
            ->selectRaw('rig_name, record_type, MIN(start_date) as start_date, MAX(finish_date) as finish_date, COUNT(*) as segments')
            ->groupBy('rig_name', 'record_type')
            ->orderBy('rig_name')
            ->orderBy('record_type')
            ->get();

        $page = max((int) $request->get('page', 1), 1);
        $perPage = 15;
        $total = $summary->count();

        return response()->json([
            'data' => $summary->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page' => max((int) ceil($total / $perPage), 1),
            'total' => $total,
        ]);
    }



}
