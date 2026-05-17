<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\StorePermissionRequest;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $permissions = Permission::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Permission/Index', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePermissionRequest $request)
    {
        Permission::create($request->validated());

        return redirect()->back()->with('success', 'Permission berhasil ditambahkan');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $permission->update($request->validated());

        return redirect()->back()->with('success', 'Permission berhasil diubah');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission)
    {
        try {
            $permission->delete();
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', 'Permission gagal dihapus');
        }

        return redirect()->back()->with('success', 'Permission berhasil dihapus');
    }
}
