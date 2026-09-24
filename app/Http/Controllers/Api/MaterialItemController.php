<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialItem;
use App\Models\WellMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialItemController extends Controller
{
    public function index()
    {
        //return MaterialItem::orderBy('sort_order')->orderBy('item_name')->get();
        return MaterialItem::orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name'  => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['item_name'] = trim($validated['item_name']);

        if (MaterialItem::where('item_name', $validated['item_name'])->exists()) {
            return response()->json(['message' => 'This item already exists.'], 422);
        }

        $validated['sort_order'] ??= (MaterialItem::max('sort_order') ?? 0) + 1;

        $item = MaterialItem::create($validated);

        return response()->json(['message' => 'Item added', 'item' => $item], 201);
    }

    public function update(Request $request, MaterialItem $materialItem)
    {
        $validated = $request->validate([
            'item_name'  => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $newName = trim($validated['item_name']);
        $oldName = $materialItem->item_name;

        DB::transaction(function () use ($materialItem, $newName, $oldName, $validated) {
            if ($newName !== $oldName) {
                // Cascades across EVERY asset now, since item names are shared.
                WellMaterial::where('item_name', $oldName)->update(['item_name' => $newName]);
            }
            $materialItem->item_name = $newName;
            if (isset($validated['sort_order'])) {
                $materialItem->sort_order = $validated['sort_order'];
            }
            $materialItem->save();
        });

        return response()->json(['message' => 'Item updated', 'item' => $materialItem]);
    }

    public function destroy(MaterialItem $materialItem)
    {
        $usageCount = WellMaterial::where('item_name', $materialItem->item_name)->count();
        $materialItem->delete();

        return response()->json([
            'message' => $usageCount > 0
                ? "Removed from the list. {$usageCount} existing quantity record(s) across all assets remain untouched."
                : 'Item removed.',
        ]);
    }
}
