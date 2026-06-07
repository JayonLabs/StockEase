<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $users = $this->userService->getPaginatedUsers(
            $request->only('search'),
            $perPage
        );

        return Inertia::render('User/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $this->userService->storeUser($request->validated());

        return redirect()->back()->with('success', 'User berhasil ditambahkan');
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorizeCompanyAccess($user);
        $this->userService->updateUser($user, $request->validated());

        return redirect()->back()->with('success', 'User berhasil diubah');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        $this->authorizeCompanyAccess($user);

        try {
            $this->userService->deleteUser($user);
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', 'User gagal dihapus');
        }

        return redirect()->back()->with('success', 'User berhasil dihapus');
    }

    /**
     * Reset the password for the specified user.
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user)
    {
        $this->authorizeCompanyAccess($user);
        $this->userService->resetPassword($user, $request->password);

        return redirect()->back()->with('success', 'Password berhasil diubah');
    }

    /**
     * Ensure the user belongs to the same company as the authenticated user.
     */
    private function authorizeCompanyAccess(User $user): void
    {
        $currentUser = Auth::user();

        if ($currentUser->company_id && $user->company_id !== $currentUser->company_id) {
            abort(403, 'Unauthorized access.');
        }
    }
}
