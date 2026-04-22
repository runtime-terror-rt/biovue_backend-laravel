<?php

namespace App\Http\Controllers\PrivacyPolicy;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use Illuminate\Http\Request;

class PrivacyPolicyController extends Controller
{
    public function show()
    {
        $policy = PrivacyPolicy::find(1);

        if (!$policy) {
            return response()->json([
                'success' => false, 
                'message' => 'Privacy Policy not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $policy
        ]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'title'     => 'required|string|max:255',
            'is_active' => 'required|boolean',
            'items'     => 'required|array|min:1', 
            'items.*.id'      => 'required|integer',
            'items.*.heading' => 'required|string',
            'items.*.content' => 'required|string',
        ]);

        $policy = PrivacyPolicy::updateOrCreate(
            ['id' => 1], 
            [
                'title'     => $request->title,
                'content'   => $request->items, 
                'is_active' => $request->is_active,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Privacy Policy updated successfully.',
            'data'    => $policy
        ]);
    }
}
