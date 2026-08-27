@php
    $fieldClasses = 'w-full rounded border border-rule bg-raised px-2.5 py-1.5 font-mono text-xs text-ink placeholder:text-muted/70 focus:border-signal focus:outline-none transition-colors';
    $labelClasses = 'block mb-1 font-mono text-[10px] uppercase tracking-[0.14em] text-muted';
    $selectedMethods = $filters->methods();
    $dateValue = static fn (string $parameter): string => str_replace(' ', 'T', (string) request($parameter));
@endphp

<form method="GET" action="{{ route('larascope.index') }}"
      class="mb-4 rounded-md border border-rule bg-surface">

    {{-- What you reach for on almost every visit. --}}
    <div class="p-4 flex flex-wrap items-end gap-x-4 gap-y-3">
        <div class="grow min-w-[12rem] max-w-sm">
            <label for="ls-path" class="{{ $labelClasses }}">Path contains</label>
            <input id="ls-path" type="text" name="path" value="{{ request('path') }}" placeholder="api/users"
                   class="{{ $fieldClasses }}">
        </div>

        <fieldset class="m-0 border-0 p-0">
            <legend class="{{ $labelClasses }}">Method</legend>
            <div class="flex flex-wrap gap-1.5">
                @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $httpMethod)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="method[]" value="{{ $httpMethod }}" class="peer sr-only"
                               @checked(in_array($httpMethod, $selectedMethods, true))>
                        <span class="block rounded border border-rule px-2 py-1.5 font-mono text-[11px] leading-none text-muted transition-colors hover:border-muted peer-checked:border-signal peer-checked:bg-signal/10 peer-checked:text-signal peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-signal">
                            {{ $httpMethod }}
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="w-28">
            <label for="ls-status-class" class="{{ $labelClasses }}">Status</label>
            <select id="ls-status-class" name="status_class" class="{{ $fieldClasses }}">
                <option value="">Any</option>
                @foreach (['2xx' => '2xx', '3xx' => '3xx', '4xx' => '4xx', '5xx' => '5xx'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status_class') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-36">
            <label for="ls-range" class="{{ $labelClasses }}">Time</label>
            <select id="ls-range" name="range" class="{{ $fieldClasses }}">
                <option value="">Any time</option>
                @foreach (['15m' => 'Last 15 min', '1h' => 'Last hour', '24h' => 'Last 24 hours', '7d' => 'Last 7 days'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('range') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-36">
            <label for="ls-sort" class="{{ $labelClasses }}">Sort by</label>
            <select id="ls-sort" name="sort" onchange="this.form.submit()" class="{{ $fieldClasses }}">
                @foreach (['recent' => 'Most recent', 'slowest' => 'Slowest', 'memory' => 'Most memory', 'queries' => 'Most queries'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters->sort() === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <button type="submit"
                    class="rounded bg-signal px-3.5 py-1.5 font-mono text-xs font-semibold text-surface hover:opacity-90 transition-opacity">
                Apply
            </button>
            <a href="{{ route('larascope.index') }}"
               class="rounded border border-rule px-3.5 py-1.5 font-mono text-xs text-muted hover:text-ink hover:border-muted transition-colors">
                Reset
            </a>
        </div>
    </div>

    {{-- Opens itself when one of these is already narrowing the results. --}}
    <details class="border-t border-rule" {{ $filters->hasAdvancedFilters() ? 'open' : '' }}>
        <summary class="flex cursor-pointer items-center gap-2 px-4 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em] text-muted hover:text-ink transition-colors">
            <svg class="ls-caret h-3 w-3 transition-transform" viewBox="0 0 12 12" fill="none"
                 stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 2l4 4-4 4" />
            </svg>
            Advanced
        </summary>

        <div class="px-4 pb-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-x-4 gap-y-3">
            <div>
                <label for="ls-status" class="{{ $labelClasses }}">Exact status</label>
                <input id="ls-status" type="number" name="status" value="{{ request('status') }}" placeholder="404"
                       class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-route" class="{{ $labelClasses }}">Route name</label>
                <input id="ls-route" type="text" name="route" value="{{ request('route') }}" placeholder="users.index"
                       class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-ip" class="{{ $labelClasses }}">IP address</label>
                <input id="ls-ip" type="text" name="ip" value="{{ request('ip') }}" placeholder="10.0.0."
                       class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-user" class="{{ $labelClasses }}">User ID</label>
                <input id="ls-user" type="number" name="user_id" value="{{ request('user_id') }}" placeholder="1"
                       class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-from" class="{{ $labelClasses }}">From</label>
                <input id="ls-from" type="datetime-local" name="from" value="{{ $dateValue('from') }}"
                       class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-to" class="{{ $labelClasses }}">To</label>
                <input id="ls-to" type="datetime-local" name="to" value="{{ $dateValue('to') }}"
                       class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-min-duration" class="{{ $labelClasses }}">Min duration (ms)</label>
                <input id="ls-min-duration" type="number" step="any" name="min_duration" value="{{ request('min_duration') }}"
                       placeholder="500" class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-min-memory" class="{{ $labelClasses }}">Min memory (MB)</label>
                <input id="ls-min-memory" type="number" step="any" name="min_memory" value="{{ request('min_memory') }}"
                       placeholder="32" class="{{ $fieldClasses }}">
            </div>

            <div>
                <label for="ls-min-queries" class="{{ $labelClasses }}">Min queries</label>
                <input id="ls-min-queries" type="number" name="min_queries" value="{{ request('min_queries') }}"
                       placeholder="50" class="{{ $fieldClasses }}">
            </div>

            <div class="col-span-2 md:col-span-3 lg:col-span-3 flex items-end">
                <label class="flex cursor-pointer items-center gap-2 pb-1.5">
                    <input type="checkbox" name="slow" value="1" class="peer sr-only" @checked(request()->boolean('slow'))>
                    <span class="grid h-4 w-4 place-items-center rounded-sm border border-rule text-transparent transition-colors peer-checked:border-attention peer-checked:bg-attention peer-checked:text-surface peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-signal">
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 6.5l2.5 2.5 4.5-5" />
                        </svg>
                    </span>
                    <span class="font-mono text-xs text-muted">Only requests with slow queries</span>
                </label>
            </div>
        </div>
    </details>
</form>

@if ($filters->isActive())
    <div class="mb-5 flex flex-wrap items-center gap-2">
        @foreach ($filters->activeChips() as $chip)
            <a href="{{ $chip['url'] }}" title="Remove the {{ strtolower($chip['label']) }} filter"
               class="group inline-flex items-center gap-1.5 rounded border border-rule bg-surface py-1 pl-2.5 pr-2 font-mono text-[11px] text-ink transition-colors hover:border-fault hover:text-fault">
                <span class="text-muted transition-colors group-hover:text-fault">{{ $chip['label'] }}</span>
                {{ $chip['value'] }}
                <svg class="h-3 w-3 text-muted transition-colors group-hover:text-fault" viewBox="0 0 12 12"
                     fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" d="M3 3l6 6M9 3l-6 6" />
                </svg>
                <span class="sr-only">Remove</span>
            </a>
        @endforeach

        <a href="{{ route('larascope.index') }}"
           class="font-mono text-[11px] text-muted underline decoration-rule underline-offset-4 hover:text-ink hover:decoration-muted transition-colors">
            Clear all
        </a>
    </div>
@endif
