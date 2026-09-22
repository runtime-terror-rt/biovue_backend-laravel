<?php
namespace App\Http\Controllers\Faq; 

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Cache::remember('faqs_public_active', 86400, function () {
            return Faq::where('is_active', true)->get();
        });
                    
        return response()->json([
            'success' => true,
            'data' => $faqs
        ]);
    }

    public function adminIndex()
    {
        $faqs = Faq::get();
                    
        return response()->json([
            'success' => true,
            'data' => $faqs
        ]);
    }

    public function storeOrUpdate(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only an Admin can manage FAQs.'
                ], 403);
            }

        $request->validate([
            'id'        => 'nullable|exists:faqs,id', 
            'question'  => 'required|string',
            'answer'    => 'required|string',
            'is_active' => 'nullable|boolean', 
        ]);

        $faq = Faq::updateOrCreate(
            ['id' => $request->id], 
            [
                'question'  => $request->question,
                'answer'    => $request->answer,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
            ]
        );

        Cache::forget('faqs_public_active');

        return response()->json([
            'success' => true,
            'message' => $request->id ? 'FAQ updated successfully' : 'FAQ created successfully',
            'data'    => $faq
        ]);
    }

    public function show($id)
    {
        $faq = Faq::find($id);
        if (!$faq) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return response()->json(['data' => $faq]);
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only an Admin can delete FAQs.'
                ], 403);
            }
        $faq = Faq::find($id);
        if ($faq) {
            $faq->delete();
            Cache::forget('faqs_public_active');
            return response()->json(['message' => 'FAQ deleted successfully']);
        }
        return response()->json(['message' => 'FAQ not found'], 404);
    }

    public function toggleActive($id)
    {
        if (!auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only an Admin can manage FAQs.'
                ], 403);
            }
        $faq = Faq::find($id);
        if ($faq) {
            $faq->is_active = !$faq->is_active;
            $faq->save();
            Cache::forget('faqs_public_active');
            return response()->json(['message' => 'FAQ status toggled successfully', 'data' => $faq]);
        }
        return response()->json(['message' => 'FAQ not found'], 404);
    }   
}