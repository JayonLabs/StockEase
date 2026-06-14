<?php

namespace App\Http\Controllers\Platform\Owner;

use App\Http\Controllers\Controller;
use App\Services\Platform\Owner\CompanyService;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly CompanyService $companyService
    ) {}

    /**
     * Display a paginated list of all companies.
     */
    public function index(): Response
    {
        return Inertia::render('Platform/Owner/Company/Index', [
            'companies' => $this->companyService->getAll(),
        ]);
    }

    /**
     * Display a single company with details and relations.
     *
     * @param  int  $id  The company ID.
     */
    public function show(int $id): Response
    {
        $company = $this->companyService->findById($id);

        abort_if(is_null($company), 404);

        return Inertia::render('Platform/Owner/Company/Show', [
            'company' => $company,
        ]);
    }
}
