@extends('larascope::layout')

@section('title', $requestLog->method . ' /' . $requestLog->path)

@section('content')

{{-- Header --}}
<div class="mb-6 flex items-start gap-4">
    <a href="{{ route('larascope.index') }}"
       class="mt-1 text-gray-400 hover:text-gray-600 transition-colors text-sm flex items-center gap-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back
    </a>
    <div>
        <h1 class="text-xl font-bold text-gray-800 font-mono">/{{ $requestLog->path }}</h1>
        <p class="text-sm text-gray-400 mt-0.5">
            {{ $requestLog->created_at->format('Y-m-d H:i:s') }}
            &middot; {{ $requestLog->ip_address }}
            @if ($requestLog->user_id)
                &middot; User #{{ $requestLog->user_id }}
            @endif
        </p>
    </div>
</div>

{{-- Stat Cards --}}
@php
    $methodColors = [
        'GET'    => 'bg-green-100 text-green-800',
        'POST'   => 'bg-blue-100 text-blue-800',
        'PUT'    => 'bg-yellow-100 text-yellow-800',
        'PATCH'  => 'bg-yellow-100 text-yellow-800',
        'DELETE' => 'bg-red-100 text-red-800',
    ];
    $statusCode  = $requestLog->status_code;
    $statusColor = match(true) {
        $statusCode >= 500 => 'text-red-600',
        $statusCode >= 400 => 'text-yellow-600',
        $statusCode >= 300 => 'text-sky-600',
        default            => 'text-green-600',
    };
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 mb-2">Method</p>
        <span class="inline-flex items-center px-2.5 py-1 rounded text-sm font-bold {{ $methodColors[$requestLog->method] ?? 'bg-gray-100 text-gray-800' }}">
            {{ $requestLog->method }}
        </span>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 mb-2">Status</p>
        <p class="text-3xl font-bold {{ $statusColor }}">{{ $statusCode }}</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 mb-2">Duration</p>
        <p class="text-3xl font-bold text-gray-800">
            {{ number_format($requestLog->duration_ms, 2) }}
            <span class="text-sm font-normal text-gray-400">ms</span>
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-xs text-gray-500 mb-2">Peak Memory</p>
        <p class="text-3xl font-bold text-gray-800">
            {{ number_format($requestLog->memory_peak_mb, 2) }}
            <span class="text-sm font-normal text-gray-400">MB</span>
        </p>
    </div>
</div>

{{-- Details --}}
<div class="bg-white border border-gray-200 rounded-lg p-5 mb-6 grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-4 text-sm">
    <div>
        <p class="text-xs text-gray-400 mb-0.5">Full URL</p>
        <p class="font-mono text-gray-800 break-all text-xs">{{ $requestLog->url }}</p>
    </div>

    @if ($requestLog->route_name)
        <div>
            <p class="text-xs text-gray-400 mb-0.5">Route Name</p>
            <p class="font-mono text-gray-800 text-xs">{{ $requestLog->route_name }}</p>
        </div>
    @endif

    <div>
        <p class="text-xs text-gray-400 mb-0.5">SQL Queries</p>
        <p class="font-mono text-gray-800 text-xs">
            {{ $requestLog->query_count }}
            @if ($requestLog->hasSlowQueries())
                <span class="ml-1 bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded text-xs font-semibold">contains slow</span>
            @endif
        </p>
    </div>
</div>

{{-- SQL Queries --}}
@if ($requestLog->query_count > 0 && !empty($requestLog->queries))
    <section class="mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-3">
            SQL Queries
            <span class="ml-1 text-sm font-normal text-gray-400">({{ $requestLog->query_count }})</span>
        </h2>

        <div class="space-y-3">
            @foreach ($requestLog->queries as $queryIndex => $query)
                @php $isSlow = $query['slow'] ?? false; @endphp
                <div class="bg-white border {{ $isSlow ? 'border-orange-300' : 'border-gray-200' }} rounded-lg p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-xs text-gray-400 font-mono">#{{ $queryIndex + 1 }}</span>
                        <span class="text-xs font-mono text-gray-500 ml-auto">{{ number_format($query['time_ms'], 3) }} ms</span>
                        @if ($isSlow)
                            <span class="bg-orange-100 text-orange-700 text-xs px-2 py-0.5 rounded font-bold">SLOW</span>
                        @endif
                    </div>

                    <pre class="text-xs font-mono text-gray-800 whitespace-pre-wrap break-all bg-gray-50 rounded-md p-3 leading-relaxed">{{ $query['sql'] }}</pre>

                    @if (!empty($query['bindings']))
                        <div class="mt-3">
                            <p class="text-xs text-gray-400 mb-1">Bindings</p>
                            <pre class="text-xs font-mono text-indigo-700 bg-indigo-50 rounded-md p-2 whitespace-pre-wrap break-all">{{ json_encode($query['bindings'], JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- Request Headers --}}
@if (!empty($requestLog->request_headers))
    <section class="mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Request Headers</h2>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-xs">
                <tbody class="divide-y divide-gray-100">
                    @foreach ($requestLog->request_headers as $headerName => $headerValue)
                        <tr>
                            <td class="px-4 py-2 font-mono text-gray-500 w-1/3 align-top">{{ $headerName }}</td>
                            <td class="px-4 py-2 font-mono text-gray-800 break-all">
                                {{ is_array($headerValue) ? implode(', ', $headerValue) : $headerValue }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

{{-- Request Body --}}
@if (!empty($requestLog->request_body))
    <section class="mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Request Body</h2>
        <pre class="bg-white border border-gray-200 rounded-lg p-4 text-xs font-mono text-gray-800 whitespace-pre-wrap break-all">{{ json_encode($requestLog->request_body, JSON_PRETTY_PRINT) }}</pre>
    </section>
@endif

{{-- Response Body --}}
@if (!empty($requestLog->response_body))
    <section class="mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Response Body</h2>
        <pre class="bg-white border border-gray-200 rounded-lg p-4 text-xs font-mono text-gray-800 whitespace-pre-wrap break-all">{{ $requestLog->response_body }}</pre>
    </section>
@endif

@endsection
