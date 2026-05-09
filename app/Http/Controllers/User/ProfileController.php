<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\DeleteAccountRequest;
use App\Http\Requests\User\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Store a new photo for the user's profile.
     */
    public function storePhotoProfile(Request $request): JsonResponse
    {
        $request->validate([
            'photo_profile' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('photo_profile')) {

            $user = $request->user();

            if ($user->photo_profile) {
                $oldPath = str_replace('storage/', '', $user->photo_profile);
                Storage::disk('public')->delete($oldPath);
            }

            $photoProfile = $request->file('photo_profile');

            $imageName = time().'.'.$photoProfile->getClientOriginalExtension();

            Storage::disk('public')->put('photo_profile/'.$imageName, file_get_contents($photoProfile));

            $request->user()->update([
                'photo_profile' => "storage/photo_profile/{$imageName}",
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Photo profile updated successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update photo profile',
        ], 400);
    }

    /**
     * Delete the user's photo profile.
     */
    public function destroyPhotoProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->photo_profile) {

            $oldPath = str_replace('storage/', '', $user->photo_profile);
            Storage::disk('public')->delete($oldPath);

            $user->update([
                'photo_profile' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Photo profile deleted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete photo profile',
        ], 400);
    }
}
