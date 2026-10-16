<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        // Subscribing twice is a success, not an error, and must not reveal
        // whether an address is already on the list.
        Lead::firstOrCreate(['email' => strtolower(trim($validated['email']))]);

        // The footer confirms inline beside the field; no extra flash toast.
        return back();
    }
}
