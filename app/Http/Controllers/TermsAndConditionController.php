<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TermsAndCondition;

class TermsAndConditionController extends Controller
{
    /**
     * Get Terms & Conditions (public)
     */
    public function get()
    {
        $terms = TermsAndCondition::first();

        if (!$terms) {
            return response()->json([
                'success' => false,
                'message' => 'Terms & Conditions not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $terms
        ]);
    }

    /**
     * Create or Update Terms & Conditions (Admin)
     */
    public function save(Request $request)
    {
        $request->validate([
            'content' => 'required|array', // JSON array for multilingual support
            'content.en' => 'required|string',
            'content.bn' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $terms = TermsAndCondition::updateOrCreate(
            ['id' => 1], // Always keep a single row
            [
                'content' => $request->content,
                'is_active' => $request->is_active,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Terms & Conditions saved successfully.',
            'data' => $terms
        ]);
    }
}
