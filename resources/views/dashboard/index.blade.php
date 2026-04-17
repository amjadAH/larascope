@extends('larascope::layout')

@section('title', 'Request Logs')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Request Logs</h1>
    <p class="text-gray-500 text-sm mt-1">{{ number_format($requestLogs->total()) }} total entries</p>
</div>

{{-- Filters --}}
<form method="GET"
      class="bg-white border border-gray-200 rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Method</label>
        <select name="method"
                class="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All</option>
            @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $httpMethod)
                <option value="{{ $httpMethod }}" @selected(request('method') === $httpMethod)>
                    {{ $httpMethod }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
        <input type="number" name="status" value="{{ request('status') }}" placeholder="e.g. 200"
               class="border border-gray-300 rounded-md px-3 py-1.5 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Path</label>
        <input type="text" name="path" value="{{ request('path') }}" placeholder="e.g. api/users"
               class="border border-gray-300 rounded-md px-3 py-1.5 text-sm w-52 focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>

    <div class="flex gap-2">
        <button type="submit"
                class="bg-indigo-600 text-white text-sm px-4 py-1.5 rounded-md hover:bg-indigo-700 transition-colors">
            Filter
        </button>
        <a href="{{ route('larascope.index') }}"
           class="text-sm px-4 py-1.5 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
            Clear
        </a>
    </div>
</form>

{{-- Table --}}
<div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
    @if ($requestLogs->isEmpty())
        <div class="py-20 text-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-3 text-gray-300" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-base font-medium">No request logs found.</p>
            <p class="text-sm mt-1">Make some HTTP requests and they will appear here.</p>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Method</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Path</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Status</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Duration</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Memory</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Queries</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($requestLogs as $requestLog)
                    @php
                        $methodColors = [
                            'GET'    => 'bg-green-100 text-green-800',
                            'POST'   => 'bg-blue-100 text-blue-800',
                            'PUT'    => 'bg-yellow-100 text-yellow-800',
                            'PATCH'  => 'bg-yellow-100 text-yellow-800',
                            'DELETE' => 'bg-red-100 text-red-800',
                        ];
                        $methodColor = $methodColors[$requestLog->method] ?? 'bg-gray-100 text-gray-800';
                        $statusCode  = $requestLog->status_code;
                        $statusColor = match(true) {
                            $statusCode >= 500 => 'bg-red-100 text-red-800',
                            $statusCode >= 400 => 'bg-yellow-100 text-yellow-800',
                            $statusCode >= 300 => 'bg-sky-100 text-sky-800',
                            default            => 'bg-green-100 text-green-800',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors cursor-pointer"
                        onclick="window.location='{{ route('larascope.show', $requestLog) }}'">

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $methodColor }}">
                                {{ $requestLog->method }}
                            </span>
                        </td>

                        <td class="px-4 py-3 max-w-xs">
                            <a href="{{ route('larascope.show', $requestLog) }}"
                               class="text-indigo-600 hover:underline font-mono text-xs truncate block">
                                /{{ $requestLog->path }}
                            </a>
                            @if ($requestLog->route_name)
                                <span class="text-gray-400 text-xs font-mono">{{ $requestLog->route_name }}</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $statusColor }}">
                                {{ $statusCode }}
                            </span>
                        </td>

                        <td class="px-4 py-3 font-mono text-xs text-gray-700">
                            {{ number_format($requestLog->duration_ms, 2) }} ms
                        </td>

                        <td class="px-4 py-3 font-mono text-xs text-gray-700">
                            {{ number_format($requestLog->memory_peak_mb, 2) }} MB
                        </td>

                        <td class="px-4 py-3">
                            @if ($requestLog->query_count > 0)
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="font-mono text-xs text-gray-700">{{ $requestLog->query_count }}</span>
                                    @if ($requestLog->hasSlowQueries())
                                        <span class="bg-orange-100 text-orange-700 text-xs px-1.5 py-0.5 rounded font-semibold">
                                            slow
                                        </span>
                                    @endif
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                            {{ $requestLog->created_at->diffForHumans() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($requestLogs->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $requestLogs->links('pagination::tailwind') }}
            </div>
        @endif
    @endif
</div>

@endsection
