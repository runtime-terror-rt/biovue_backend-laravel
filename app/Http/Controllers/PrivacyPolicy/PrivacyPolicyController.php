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
            'title'     => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'content'   => 'nullable',
            'items'     => 'nullable|array',
        ]);

        $content = $request->items ?? $request->content ?? [];

        $policy = PrivacyPolicy::updateOrCreate(
            ['id' => 1], 
            [
                'title'     => $request->title ?? 'Privacy Policy',
                'content'   => $content, 
                'is_active' => $request->has('is_active') ? (bool)$request->is_active : true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Privacy Policy updated successfully.',
            'data'    => $policy
        ]);
    }
}
