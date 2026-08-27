{{--
    Readout cluster. Every figure describes the currently filtered set, so the
    numbers always match the table underneath.
--}}
@php
    $errorRate = $stats->errorRate();
    $errorTone = match (true) {
        $errorRate >= 5.0 => 'text-fault',
        $errorRate > 0.0  => 'text-attention',
        default           => 'text-ink',
    };
    $slowShare = $stats->total > 0 ? $stats->slowCount / $stats->total * 100 : 0;
@endphp

<section aria-label="Summary"
         class="mb-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-px bg-rule border border-rule rounded-md overflow-hidden">

    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none text-ink">{{ number_format($stats->total) }}</p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Requests</p>
    </div>

    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none {{ $errorTone }}">{{ $errorRate }}%</p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Error rate</p>
        <div class="mt-2 h-0.5 bg-rule overflow-hidden" aria-hidden="true">
            <div class="h-full bg-fault" style="width: {{ min($errorRate, 100) }}%"></div>
        </div>
    </div>

    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none text-ink">
            {{ number_format($stats->averageDurationMs, 1) }}<span class="ml-1 text-sm font-normal text-muted">ms</span>
        </p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Average</p>
    </div>

    <div class="bg-surface px-5 py-4">
        <p class="font-mono text-2xl font-semibold leading-none text-ink">
            {{ number_format($stats->maxDurationMs, 1) }}<span class="ml-1 text-sm font-normal text-muted">ms</span>
        </p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Slowest</p>
    </div>

    <div class="bg-surface px-5 py-4 col-span-2 sm:col-span-1">
        <p class="font-mono text-2xl font-semibold leading-none {{ $stats->slowCount > 0 ? 'text-attention' : 'text-ink' }}">
            {{ number_format($stats->slowCount) }}
        </p>
        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Slow queries</p>
        <div class="mt-2 h-0.5 bg-rule overflow-hidden" aria-hidden="true">
            <div class="h-full bg-attention" style="width: {{ min($slowShare, 100) }}%"></div>
        </div>
    </div>

</section>
