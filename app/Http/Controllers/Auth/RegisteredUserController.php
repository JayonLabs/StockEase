<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected SubscriptionService $subscriptionService,
    ) {}

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * Creates Company + User (super_admin) + Free Subscription in one transaction.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => Str::slug($data['company_name']).'-'.Str::random(6),
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'company_id' => $company->id,
            ]);

            $company->update(['owner_id' => $user->id]);

            $user->syncRoles('super_admin');

            $pemula = Plan::where('slug', 'pemula')->firstOrFail();
            $this->subscriptionService->createTrial($company, $pemula);

            return $user;
        });

        Auth::login($result);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
