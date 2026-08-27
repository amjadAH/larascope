<?php

namespace Amjad\LaraScope\Http\Controllers;

use Amjad\LaraScope\Http\Filters\RequestLogFilters;
use Amjad\LaraScope\Models\RequestLog;
use Amjad\LaraScope\Services\RequestLogStats;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = new RequestLogFilters($request);

        $filteredLogs = $filters->apply(RequestLog::query());

        // Summarised before sorting — an aggregate has no use for an ORDER BY.
        $stats = RequestLogStats::forQuery($filteredLogs);

        $requestLogs = $filters->applySort($filteredLogs)
            ->paginate(config('larascope.dashboard.per_page', 25))
            ->withQueryString();

        return view('larascope::dashboard.index', compact('requestLogs', 'filters', 'stats'));
    }

    public function show(RequestLog $requestLog): View
    {
        return view('larascope::dashboard.show', compact('requestLog'));
    }
}
