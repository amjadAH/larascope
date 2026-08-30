@extends('larascope::layout')

@section('title', $requestLog->method . ' /' . $requestLog->path)

@section('content')

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
@endphp

<a href="{{ route('larascope.index') }}"
   class="mb-4 inline-flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-muted hover:text-ink transition-colors">
    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 2L4 6l4 4" />
    </svg>
    All requests
</a>

<div class="mb-5 flex flex-wrap items-center gap-3">
    <span class="rounded border px-2 py-1 font-mono text-xs leading-none {{ $methodTone }}">
        {{ $requestLog->method }}
    </span>
    <h1 class="font-mono text-lg font-semibold tracking-tight text-ink break-all">/{{ $requestLog->path }}</h1>
</div>

<p class="mb-5 font-mono text-[11px] text-muted">
    {{ $requestLog->created_at->format('Y-m-d H:i:s') }}
    <span class="mx-1.5 text-rule">/</span>{{ $requestLog->ip_address }}
    @if ($requestLog->user_id)
        <span class="mx-1.5 text-rule">/</span>user #{{ $requestLog->user_id }}
    @endif
</p>

{{-- Same readout treatment as the list, so the two pages read alike. --}}
<section aria-label="Measurements"
         class="mb-5 grid grid-cols-2 lg:grid-cols-4 gap-px bg-rule border border-rule rounded-md overflow-hidden">
    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none {{ $statusTone }}">{{ $statusCode }}</p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Status</p>
    </div>

    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none text-ink">
            {{ number_format($requestLog->duration_ms, 1) }}<span class="ml-1 text-sm font-normal text-muted">ms</span>
        </p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Duration</p>
    </div>

    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none text-ink">
            {{ number_format($requestLog->memory_peak_mb, 2) }}<span class="ml-1 text-sm font-normal text-muted">MB</span>
        </p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Peak memory</p>
    </div>

    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none {{ $requestLog->hasSlowQueries() ? 'text-attention' : 'text-ink' }}">
            {{ $requestLog->query_count }}
        </p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">
            Queries
            @if ($requestLog->hasSlowQueries())
                <span class="text-attention">&middot; slow</span>
            @endif
        </p>
    </div>
</section>

<div class="mb-6 grid grid-cols-1 gap-x-10 gap-y-4 rounded-md border border-rule bg-surface px-5 py-4 md:grid-cols-2">
    <div>
        <p class="mb-1 font-mono text-[10px] uppercase tracking-[0.14em] text-muted">Full URL</p>
        <p class="break-all font-mono text-xs text-ink">{{ $requestLog->url }}</p>
    </div>

    @if ($requestLog->route_name)
        <div>
            <p class="mb-1 font-mono text-[10px] uppercase tracking-[0.14em] text-muted">Route name</p>
            <p class="font-mono text-xs text-ink">{{ $requestLog->route_name }}</p>
        </div>
    @endif
</div>

@if ($requestLog->query_count > 0 && !empty($requestLog->queries))
    @php
        $slowestQueryMs = max(0.001, (float) collect($requestLog->queries)->max('time_ms'));
    @endphp

    <section class="mb-6">
        <h2 class="mb-3 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">
            SQL queries <span class="text-ink">{{ count($requestLog->queries) }}</span>
        </h2>

        <div class="space-y-2">
            @foreach ($requestLog->queries as $queryIndex => $query)
                @php
                    $isSlowQuery = $query['slow'] ?? false;
                    $queryShare  = min(100, (float) $query['time_ms'] / $slowestQueryMs * 100);

                    // Short binding lists read better on one line than pretty-printed
                    // over three, and a query list is something you scan.
                    $bindingsJson = json_encode($query['bindings'] ?? []);
                    if (strlen($bindingsJson) > 80) {
                        $bindingsJson = json_encode($query['bindings'], JSON_PRETTY_PRINT);
                    }
                @endphp

                <article class="rounded-md border {{ $isSlowQuery ? 'border-attention/50' : 'border-rule' }} bg-surface">
                    <div class="flex items-center gap-3 border-b border-rule px-4 py-2">
                        <span class="font-mono text-[10px] text-muted">{{ $queryIndex + 1 }}</span>

                        {{-- Each query's share of the slowest one in this request. --}}
                        <span class="h-[3px] w-24 overflow-hidden bg-rule" aria-hidden="true">
                            <span class="block h-full {{ $isSlowQuery ? 'bg-attention' : 'bg-signal/60' }}"
                                  style="width: {{ $queryShare }}%"></span>
                        </span>

                        <span class="ml-auto font-mono text-[11px] {{ $isSlowQuery ? 'text-attention' : 'text-muted' }}">
                            {{ number_format($query['time_ms'], 3) }} ms
                        </span>

                        @if ($isSlowQuery)
                            <span class="rounded border border-attention/40 bg-attention/10 px-1.5 py-0.5 font-mono text-[10px] leading-none text-attention">slow</span>
                        @endif
                    </div>

                    <pre class="overflow-x-auto px-4 py-3 font-mono text-xs leading-relaxed text-ink whitespace-pre-wrap break-all">{{ $query['sql'] }}</pre>

                    @if (!empty($query['bindings']))
                        <div class="border-t border-rule px-4 py-2.5">
                            <p class="mb-1 font-mono text-[10px] uppercase tracking-[0.14em] text-muted">Bindings</p>
                            <pre class="overflow-x-auto font-mono text-xs text-signal whitespace-pre-wrap break-all">{{ $bindingsJson }}</pre>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif

@if (!empty($requestLog->request_headers))
    <section class="mb-6">
        <h2 class="mb-3 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Request headers</h2>
        <div class="overflow-hidden rounded-md border border-rule bg-surface">
            <table class="w-full text-left">
                <tbody class="divide-y divide-rule">
                    @foreach ($requestLog->request_headers as $headerName => $headerValue)
                        <tr>
                            <td class="w-1/3 px-4 py-2 align-top font-mono text-[11px] text-muted">{{ $headerName }}</td>
                            <td class="break-all px-4 py-2 font-mono text-[11px] text-ink">
                                {{ is_array($headerValue) ? implode(', ', $headerValue) : $headerValue }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@if (!empty($requestLog->request_body))
    <section class="mb-6">
        <h2 class="mb-3 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Request body</h2>
        <pre class="overflow-x-auto rounded-md border border-rule bg-surface px-4 py-3 font-mono text-xs text-ink whitespace-pre-wrap break-all">{{ json_encode($requestLog->request_body, JSON_PRETTY_PRINT) }}</pre>
    </section>
@endif

@if (!empty($requestLog->response_body['content']))
    <section class="mb-6">
        <h2 class="mb-3 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">
            Response body
            @if (!empty($requestLog->response_body['content-type']))
                <span class="mx-1.5 text-rule">/</span>{{ $requestLog->response_body['content-type'] }}
            @endif
        </h2>
        <pre class="overflow-x-auto rounded-md border border-rule bg-surface px-4 py-3 font-mono text-xs text-ink whitespace-pre-wrap break-all">{{ $requestLog->response_body['content'] }}</pre>
    </section>
@endif

@endsection
