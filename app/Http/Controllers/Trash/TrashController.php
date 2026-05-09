<?php

namespace App\Http\Controllers\Trash;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Trash\TrashService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    /**
     * Valid model classes that can be restored or force-deleted.
     */
    protected const RESTORABLE_CLASSES = [
        'Category' => Category::class,
        'Product' => Product::class,
        'Supplier' => Supplier::class,
        'Unit' => Unit::class,
        'User' => User::class,
        'Promotion' => Promotion::class,
        'Purchase' => Purchase::class,
        'Sale' => Sale::class,
    ];

    public function __construct(protected TrashService $trashService) {}

    /**
     * Display all trashed items.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $perPage = $request->integer('per_page', 15);

        $trashed = $search
            ? $this->trashService->searchTrashedItems($search, $perPage)
            : $this->trashService->getPaginatedTrashedItems($perPage);

        return Inertia::render('Trash/Index', [
            'trashedItems' => $trashed,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Display the details of a trashed item.
     */
    public function show(string $type, int $id): Response
    {
        $class = self::RESTORABLE_CLASSES[$type] ?? null;

        if (! $class) {
            abort(404);
        }

        $item = $this->trashService->getTrashedItem($class, $id);

        return Inertia::render('Trash/Show', [
            'trashedItem' => $item,
        ]);
    }

    /**
     * Restore a soft-deleted model.
     */
    public function restore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer'],
        ]);

        $class = self::RESTORABLE_CLASSES[$validated['type']] ?? null;

        if (! $class) {
            abort(404);
        }

        /** @var Model&SoftDeletes $model */
        $model = $this->trashService->restore($class, $validated['id']);

        return redirect()->back()->with('success', sprintf(
            '%s berhasil dipulihkan.',
            $validated['type']
        ));
    }

    /**
     * Permanently delete a soft-deleted model.
     */
    public function forceDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer'],
        ]);

        $class = self::RESTORABLE_CLASSES[$validated['type']] ?? null;

        if (! $class) {
            abort(404);
        }

        $this->trashService->forceDelete($class, $validated['id']);

        return redirect()->back()->with('success', sprintf(
            '%s berhasil dihapus secara permanen.',
            $validated['type']
        ));
    }
}
