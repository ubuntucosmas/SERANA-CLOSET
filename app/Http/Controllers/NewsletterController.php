<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:leads,email',
        ]);

        Lead::create($validated);

        return back()->with('success', 'The Serana Inner Circle will open soon. You are on the registry.');
    }
}
