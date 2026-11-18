<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Activity;
use App\Models\Buyer;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleDispatch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $statusCounts = SampleDispatch::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $loadingCounts = SampleDispatch::query()->where('status', DispatchStatus::Confirmed)
            ->selectRaw('loading_status, count(*) as total')->groupBy('loading_status')->pluck('total', 'loading_status');

        $stats = [
            'mills' => Mill::count(),
            'buyers' => Buyer::count(),
            'samples' => Sample::count(),
            'samples_month' => Sample::whereBetween('collection_date', [now()->startOfMonth(), now()])->count(),
            'dispatches' => $statusCounts->sum(),
            'pending' => $statusCounts[DispatchStatus::Pending->value] ?? 0,
            'confirmed' => $statusCounts[DispatchStatus::Confirmed->value] ?? 0,
            'rejected' => $statusCounts[DispatchStatus::Rejected->value] ?? 0,
            'loading_pending' => ($loadingCounts[LoadingStatus::Pending->value] ?? 0) + ($loadingCounts[LoadingStatus::InProgress->value] ?? 0),
            'loading_in_progress' => $loadingCounts[LoadingStatus::InProgress->value] ?? 0,
            'loading_completed' => $loadingCounts[LoadingStatus::Completed->value] ?? 0,
        ];

        $awaitingResponse = SampleDispatch::query()
            ->where('status', DispatchStatus::Pending)
            ->with(['buyer:id,name', 'sample:id,sample_number,rice_variety_id', 'sample.variety:id,name'])
            ->oldest('dispatch_date')->oldest('id')->limit(5)->get();

        $awaitingLoading = SampleDispatch::query()
            ->where('status', DispatchStatus::Confirmed)
            ->whereIn('loading_status', [LoadingStatus::Pending, LoadingStatus::InProgress])
            ->with(['buyer:id,name', 'sample:id,sample_number,rice_variety_id', 'sample.variety:id,name', 'loadings:id,sample_dispatch_id,quantity,quantity_unit'])
            ->oldest('confirmation_date')->limit(5)->get();

        // Some events are logged on two records (e.g. on the sample and on the dispatch);
        // show each business event once in the feed.
        $activities = Activity::query()
            ->with(['user:id,name', 'subject'])
            ->whereNot(fn ($q) => $q->where('subject_type', 'sample')->where('event', 'dispatched'))
            ->whereNot(fn ($q) => $q->where('subject_type', 'dispatch')->where('event', 'loading_recorded'))
            ->latest('id')->limit(10)->get();

        return view('dashboard', [
            'user' => $request->user(),
            'stats' => $stats,
            'awaitingResponse' => $awaitingResponse,
            'awaitingLoading' => $awaitingLoading,
            'activities' => $activities,
        ]);
    }
}
