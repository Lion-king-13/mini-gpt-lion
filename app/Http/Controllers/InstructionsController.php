<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class InstructionsController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        return Inertia::render('Instructions/Edit', [
            'aboutYou' => $user->about_you,
            'behavior' => $user->behavior,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'about_you' => 'nullable|string|max:2000',
            'behavior' => 'nullable|string|max:2000',
        ]);

        auth()->user()->update($validated);

        return back();
    }
}
