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


//      //added logic for update actual records on 13-08-26

//    public function update(Request $request, RigDetail $rigDetail)
//     {
//         $validated = $request->validate([
//             'rig_name'    => 'required|string|max:255',
//             'asset'       => 'nullable|string|max:255',
//             'platform'    => 'nullable|string|max:255',
//             'well_name'   => 'nullable|string|max:255',
//             'type'        => 'nullable|string|max:50',
//             'meters'      => 'nullable|numeric',
//             'wi_well'     => 'nullable|string|max:50',
//             'days'        => 'nullable|integer',
//             'status'      => 'nullable|string|max:50',
//             'activity'    => 'nullable|string|max:50',
//             'start_date'  => 'nullable|date',
//             'finish_date' => 'nullable|date',
//             'oil_gain'    => 'nullable|numeric',
//             'gas_gain'    => 'nullable|numeric',
//         ]);

//         $rigDetail->update($validated);

//         return response()->json(['message' => 'Record updated', 'record' => $rigDetail]);
//     }
//     //end

    public function update(Request $request, RigDetail $rigDetail)
    {
        abort_if($rigDetail->record_type !== 'actual', 403, 'Only actual records can be edited.');

        // Only these fields are accepted. Rig name, asset, platform, well name,
        // start/finish dates and days are deliberately NOT here, so they stay
        // fixed even if someone sends them directly to the API.
        $validated = $request->validate([
            'type'     => 'nullable|string|max:50',
            'wi_well'  => 'nullable|string|max:50',
            'activity' => 'nullable|string|max:50',
            'status'   => 'nullable|string|max:50',
            'meters'   => 'nullable|numeric',
            'oil_gain' => 'nullable|numeric',
            'gas_gain' => 'nullable|numeric',
        ]);

        $rigDetail->update($validated);

        return response()->json(['message' => 'Record updated', 'record' => $rigDetail]);
    }

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




//added by sachin on 28-9-26
//start

    public function wellGroups(Request $request)
    {
        $base = RigDetail::query()
            ->where('record_type', $request->get('record_type', 'actual'))
            ->whereNotNull('start_date')
            ->whereNotNull('finish_date');

        if ($request->filled('plan_name')) {
            $base->where('plan_name', $request->plan_name);
        }

        // Find the matching wells first, then load ALL segments of those wells,
        // so a search can never trim a well's date range down to only the
        // segments that happened to match.
        $matching = clone $base;
        if ($request->filled('search')) {
            $term = $request->search;
            $matching->where(function ($q) use ($term) {
                $q->where('rig_name', 'like', "%{$term}%")
                ->orWhere('asset', 'like', "%{$term}%")
                ->orWhere('well_name', 'like', "%{$term}%");
            });
        }
        $wellNames = $matching->distinct()->pluck('well_name')->all();

        $groups = (clone $base)
            ->whereIn('well_name', $wellNames)
            ->orderBy('start_date')->orderBy('id')
            ->get()
            ->groupBy('well_name')
            ->map(function ($segments, $wellName) {
                $latest = $segments->sortByDesc(fn ($s) => $s->finish_date->timestamp)->first();

                return [
                    'well_name'     => $wellName,
                    'rig_name'      => $latest->rig_name,
                    'asset'         => $latest->asset,
                    'platform'      => $latest->platform,
                    'start_date'    => $segments->map(fn ($s) => $s->start_date->format('Y-m-d'))->min(),
                    'finish_date'   => $segments->map(fn ($s) => $s->finish_date->format('Y-m-d'))->max(),
                    'has_completed' => $segments->contains(fn ($s) => strtoupper(trim((string) $s->status)) === 'C'),
                    'segments'      => $segments->map(fn ($s) => [
                        'id'          => $s->id,
                        'rig_name'    => $s->rig_name,
                        'asset'       => $s->asset,
                        'platform'    => $s->platform,
                        'well_name'   => $s->well_name,
                        'type'        => $s->type,
                        'wi_well'     => $s->wi_well,
                        'activity'    => $s->activity,
                        'status'      => $s->status,
                        'days'        => $s->days,
                        'meters'      => $s->meters,
                        'oil_gain'    => $s->oil_gain,
                        'gas_gain'    => $s->gas_gain,
                        'start_date'  => $s->start_date->format('Y-m-d'),
                        'finish_date' => $s->finish_date->format('Y-m-d'),
                    ])->values(),
                ];
            })
            ->values()
            ->sortBy([['rig_name', 'asc'], ['start_date', 'asc'], ['well_name', 'asc']])
            ->values();

        $perPage = 15;
        $page = max((int) $request->get('page', 1), 1);
        $total = $groups->count();

        return response()->json([
            'data'         => $groups->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page'    => max((int) ceil($total / $perPage), 1),
            'total'        => $total,
        ]);
    }

    //end

}
