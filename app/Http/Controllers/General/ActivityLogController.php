<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the activity log.
     */
    public function index(Request $request)
    {
        $query = Activity::with('causer:id,name,email')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%");
            });
        }

        if ($request->filled('event')) {
            $query->where('event', $request->query('event'));
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->query('log_name'));
        }

        $activities = $query->paginate(50)->withQueryString();

        $events = Activity::distinct()->pluck('event')->filter()->values();
        $logNames = Activity::distinct()->pluck('log_name')->filter()->values();

        return Inertia::render('ActivityLog/Index', [
            'activities' => $activities,
            'events' => $events,
            'logNames' => $logNames,
            'filters' => [
                'search' => $request->query('search'),
                'event' => $request->query('event'),
                'log_name' => $request->query('log_name'),
            ],
        ]);
    }

    /**
     * Display the specified activity log.
     */
    public function show(Activity $activity)
    {
        $activity->load('causer:id,name,email');

        return Inertia::render('ActivityLog/Show', [
            'activity' => $activity,
        ]);
    }
}
