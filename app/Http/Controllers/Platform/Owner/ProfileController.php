<?php

namespace App\Http\Controllers\Platform\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProfileUpdateRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the platform owner's profile edit page.
     */
    public function edit(): Response
    {
        return Inertia::render('Platform/Owner/Profile/Index');
    }

    /**
     * Update the platform owner's name and email.
     * Resets email_verified_at when email changes to require re-verification.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return redirect()->route('platform.owner.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the platform owner's password.
     * Current password verification is handled by UpdatePasswordRequest.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('platform.owner.profile.edit')
            ->with('success', 'Password updated successfully.');
    }
}
