<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Contact;

class ContactController extends Controller
{
    /**
     * Store message and send email to admin
     */
    public function store(Request $request)
    {
        // Validate email and message
        $validated = $request->validate([
            'email'   => 'required|email',
            'message' => 'required|string',
        ]);

        // Save to database
        $contact = Contact::create($validated);

        // Send mail to admin
       Mail::raw(
        "New Contact Message\n\nFrom: {$validated['email']}\n\nMessage:\n{$validated['message']}",
        function ($mail) use ($validated) {
            $mail->to('biovuesupport@gmail.com')
                ->subject('New Contact Message')
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->replyTo($validated['email']);
        }
    );

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent to admin successfully!',
            'data'    => $contact,
        ]);
    }

    /**
     * Display all contact messages (paginated)
     */
    public function index()
    {
        try {
            $perPage = 10;
            $contacts = Contact::orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'All contact messages retrieved successfully',
                'data'    => $contacts->items(),
                'meta' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page'    => $contacts->lastPage(),
                    'per_page'     => $contacts->perPage(),
                    'total'        => $contacts->total(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contacts: ' . $e->getMessage(),
            ], 500);
        }
    }
}