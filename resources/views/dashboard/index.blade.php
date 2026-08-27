@extends('larascope::layout')

@section('title', 'Request logs')

@section('content')

<h1 class="mb-5 text-lg font-semibold tracking-tight text-ink">Request logs</h1>

@include('larascope::dashboard.partials.summary')
@include('larascope::dashboard.partials.filters')

<div class="rounded-md border border-rule bg-surface overflow-hidden">
    @if ($requestLogs->isEmpty())
        <div class="px-6 py-20 text-center">
            <svg class="mx-auto mb-4 h-8 w-16 text-rule" viewBox="0 0 28 24" fill="none" stroke="currentColor"
                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M0 12h28" />
            </svg>

            @if ($filters->isActive())
                <p class="text-sm font-medium text-ink">No requests match these filters.</p>
                <p class="mt-1 text-sm text-muted">Widen the time range or drop a filter to see more.</p>
                <a href="{{ route('larascope.index') }}"
                   class="mt-4 inline-block rounded border border-rule px-3 py-1.5 font-mono text-xs text-muted hover:text-ink hover:border-muted transition-colors">
                    Reset filters
                </a>
            @else
                <p class="text-sm font-medium text-ink">Nothing logged yet.</p>
                <p class="mt-1 text-sm text-muted">Make a request to your app and it will show up here.</p>
            @endif
        </div>
    @else
        @php
            // The bars are scaled against the slowest request on this page, so
            // each page reads as its own trace rather than a global ratio.
            $slowestOnPage = max(1.0, (float) $requestLogs->max('duration_ms'));
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-rule bg-raised font-mono text-[10px] uppercase tracking-[0.14em] text-muted">
                        <th scope="col" class="px-4 py-2.5 font-medium">Method</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Path</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Status</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Duration</th>
                        <th scope="col" class="hidden px-4 py-2.5 font-medium lg:table-cell">Memory</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Queries</th>
                        <th scope="col" class="hidden px-4 py-2.5 font-medium lg:table-cell">User</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Age</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-rule">
                    @foreach ($requestLogs as $requestLog)
                        @php
                            $methodTone = [
                                'GET'    => 'border-nominal/40 bg-nominal/5 text-nominal',
                                'POST'   => 'border-info/40 bg-info/5 text-info',
                                'PUT'    => 'border-attention/40 bg-attention/5 text-attention',
                                'PATCH'  => 'border-attention/40 bg-attention/5 text-attention',
                                'DELETE' => 'border-fault/40 bg-fault/5 text-fault',
                            ][$requestLog->method] ?? 'border-rule bg-raised text-muted';

                            $statusCode = $requestLog->status_code;
                            $statusTone = match (true) {
                                $statusCode >= 500 => 'text-fault',
                                $statusCode >= 400 => 'text-attention',
                                $statusCode >= 300 => 'text-info',
                                default            => 'text-nominal',
                            };

                            $isSlow = $requestLog->hasSlowQueries();

                            // Square-root scale: latency spans orders of magnitude, and a
                            // linear bar would flatten every normal request into nothing
                            // next to one multi-second outlier. Ordering is preserved.
                            $durationShare = max(4.0, sqrt((float) $requestLog->duration_ms / $slowestOnPage) * 100);
                        @endphp

                        <tr class="cursor-pointer transition-colors hover:bg-raised focus-within:bg-raised"
                            onclick="window.location='{{ route('larascope.show', $requestLog) }}'">

                            <td class="px-4 py-2.5">
                                <span class="inline-block rounded border px-1.5 py-0.5 font-mono text-[11px] leading-none {{ $methodTone }}">
                                    {{ $requestLog->method }}
                                </span>
                            </td>

                            <td class="max-w-xs px-4 py-2.5">
                                <a href="{{ route('larascope.show', $requestLog) }}"
                                   class="block truncate font-mono text-xs text-ink hover:text-signal transition-colors">
                                    /{{ $requestLog->path }}
                                </a>
                                @if ($requestLog->route_name)
                                    <span class="block truncate font-mono text-[10px] text-muted">{{ $requestLog->route_name }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-2.5 font-mono text-xs font-semibold {{ $statusTone }}">
                                {{ $statusCode }}
                            </td>

                            {{-- The bar is the point: the outlier is visible before you read a number. --}}
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs text-ink">{{ number_format($requestLog->duration_ms, 1) }}<span class="text-muted"> ms</span></span>
                                <span class="mt-1 block h-[3px] w-24 overflow-hidden bg-rule" aria-hidden="true">
                                    <span class="block h-full {{ $isSlow ? 'bg-attention' : 'bg-signal' }}"
                                          style="width: {{ $durationShare }}%"></span>
                                </span>
                            </td>

                            <td class="hidden px-4 py-2.5 font-mono text-xs text-muted lg:table-cell">
                                {{ number_format($requestLog->memory_peak_mb, 1) }} MB
                            </td>

                            <td class="px-4 py-2.5">
                                @if ($requestLog->query_count > 0)
                                    <span class="font-mono text-xs text-ink">{{ $requestLog->query_count }}</span>
                                    @if ($isSlow)
                                        <span class="ml-1 rounded border border-attention/40 bg-attention/10 px-1 py-0.5 font-mono text-[10px] leading-none text-attention">slow</span>
                                    @endif
                                @else
                                    <span class="font-mono text-xs text-muted">0</span>
                                @endif
                            </td>

                            <td class="hidden px-4 py-2.5 font-mono text-xs text-muted lg:table-cell">
                                {{ $requestLog->user_id ? '#' . $requestLog->user_id : '—' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-muted"
                                title="{{ $requestLog->created_at->format('Y-m-d H:i:s') }}">
                                {{ $requestLog->created_at->diffForHumans(null, true) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($requestLogs->hasPages())
            <div class="flex items-center justify-between border-t border-rule px-4 py-3">
                <p class="font-mono text-[11px] text-muted">
                    {{ number_format($requestLogs->firstItem()) }}–{{ number_format($requestLogs->lastItem()) }}
                    of {{ number_format($requestLogs->total()) }}
                </p>

                <div class="flex gap-2 font-mono text-[11px]">
                    @if ($requestLogs->onFirstPage())
                        <span class="rounded border border-rule px-2.5 py-1 text-muted opacity-40">Previous</span>
                    @else
                        <a href="{{ $requestLogs->previousPageUrl() }}"
                           class="rounded border border-rule px-2.5 py-1 text-muted hover:text-ink hover:border-muted transition-colors">Previous</a>
                    @endif

                    @if ($requestLogs->hasMorePages())
                        <a href="{{ $requestLogs->nextPageUrl() }}"
                           class="rounded border border-rule px-2.5 py-1 text-muted hover:text-ink hover:border-muted transition-colors">Next</a>
                    @else
                        <span class="rounded border border-rule px-2.5 py-1 text-muted opacity-40">Next</span>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>

@endsection
