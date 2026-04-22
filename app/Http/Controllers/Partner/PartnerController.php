<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PartnerController extends Controller
{
    public function index()
    {
        $partners = Partner::all()->map(function ($partner) {
            if ($partner->image_url) {
                $partner->image_url = asset('storage/' . $partner->image_url);
            }
            return $partner;
        });

        return response()->json(['success' => true, 'data' => $partners]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:partners,email',
            'company' => 'nullable|string',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('partners', 'public');
            $validated['image_url'] = $path;
        }

        $partner = Partner::create($validated);

        if ($partner->image_url) {
            $partner->image_url = asset('storage/' . $partner->image_url);
        }


        return response()->json([
            'success' => true,
            'message' => 'Partner added successfully',
            'data'    => $partner
        ], 201);
    }

    public function show($id)
    {
        $partner = Partner::find($id);
        if (!$partner) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        if ($partner->image_url) {
            $partner->image_url = asset('storage/' . $partner->image_url);
        }

        return response()->json(['success' => true, 'data' => $partner]);
    }

    public function update(Request $request, $id)
    {
        $partner = Partner::find($id);
        if (!$partner) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'name'    => 'sometimes|string',
            'email'   => 'sometimes|email|unique:partners,email,' . $id,
            'company' => 'nullable|string',
            'image'   => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $oldPath = $partner->getRawOriginal('image_url');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            
            $validated['image_url'] = $request->file('image')->store('partners', 'public');
        }

        $partner->update($validated);

        if ($partner->image_url) {
            $partner->image_url = asset('storage/' . $partner->image_url);
        }

        return response()->json([
            'success' => true,
            'message' => 'Partner updated successfully',
            'data'    => $partner
        ]);
    }

    public function destroy($id)
    {
        $partner = Partner::find($id);
        if (!$partner) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $oldPath = $partner->getRawOriginal('image_url');
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $partner->delete();
        return response()->json(['success' => true, 'message' => 'Partner deleted successfully']);
    }
}