<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TermsAndCondition;
use Illuminate\Support\Facades\Log;

class TermsAndConditionController extends Controller
{
    /**
     * Get Terms & Conditions (public)
     */
    public function get()
    {
        try {

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

        } catch (\Exception $e) {

            Log::error('Terms fetch error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }

    /**
     * Create or Update Terms & Conditions (Admin)
     */
    public function save(Request $request)
    {
        try {

            $request->validate([
                'content' => 'required|array',
                'content.title' => 'required|string',
                'content.last_updated' => 'nullable|string',
                'content.intro' => 'nullable|string',
                'content.agreement' => 'nullable|string',
                'content.sections' => 'nullable|array',
                'is_active' => 'required|boolean',
            ]);

            $terms = TermsAndCondition::updateOrCreate(

                ['id' => 1],

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

        } catch (\Exception $e) {

            Log::error('Terms save error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save Terms & Conditions.'
            ], 500);
        }
    }
}