<?php

namespace AmjadAH\LaraScope\Http\Controllers;

use AmjadAH\LaraScope\Models\RequestLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $requestLogs = RequestLog::query()
            ->when(
                $request->filled('method'),
                fn ($query) => $query->where('method', strtoupper($request->input('method')))
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status_code', $request->integer('status'))
            )
            ->when(
                $request->filled('path'),
                fn ($query) => $query->where('path', 'like', '%' . $request->input('path') . '%')
            )
            ->latest('created_at')
            ->paginate(config('larascope.dashboard.per_page', 25))
            ->withQueryString();

        return view('larascope::dashboard.index', compact('requestLogs'));
    }

    public function show(RequestLog $requestLog): View
    {
        return view('larascope::dashboard.show', compact('requestLog'));
    }
}
