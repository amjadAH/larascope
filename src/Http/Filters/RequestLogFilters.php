<?php

namespace Amjad\LaraScope\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Translates dashboard query-string parameters into constraints on a
 * RequestLog query. Unknown, blank and malformed values are ignored so a
 * hand-edited URL can never produce an error page.
 */
class RequestLogFilters
{
    /** Sort keys mapped to the column they order by, descending. */
    private const SORT_COLUMNS = [
        'recent'  => 'created_at',
        'slowest' => 'duration_ms',
        'memory'  => 'memory_peak_mb',
        'queries' => 'query_count',
    ];

    /** Quick time-range presets mapped to their window in minutes. */
    private const RANGE_PRESETS = [
        '15m' => 15,
        '1h'  => 60,
        '24h' => 1440,
        '7d'  => 10080,
    ];

    /** Substring filter parameters mapped to the column they search. */
    private const SUBSTRING_COLUMNS = [
        'path'  => 'path',
        'route' => 'route_name',
        'ip'    => 'ip_address',
    ];

    /** Lower-bound filter parameters mapped to the column they constrain. */
    private const THRESHOLD_COLUMNS = [
        'min_duration' => 'duration_ms',
        'min_memory'   => 'memory_peak_mb',
        'min_queries'  => 'query_count',
    ];

    /** Filters shown in the always-visible row of the filter panel. */
    private const PRIMARY_PARAMETERS = ['path', 'method', 'status_class', 'range'];

    /** Filters tucked behind the collapsed "Advanced" section. */
    private const ADVANCED_PARAMETERS = [
        'status', 'route', 'ip', 'user_id', 'from', 'to',
        'min_duration', 'min_memory', 'min_queries', 'slow',
    ];

    /** Human-readable chip labels, in the order chips are rendered. */
    private const CHIP_LABELS = [
        'method'       => 'Method',
        'path'         => 'Path',
        'status'       => 'Status',
        'status_class' => 'Status class',
        'range'        => 'Range',
        'from'         => 'From',
        'to'           => 'To',
        'route'        => 'Route',
        'ip'           => 'IP',
        'user_id'      => 'User',
        'min_duration' => 'Min duration',
        'min_memory'   => 'Min memory',
        'min_queries'  => 'Min queries',
        'slow'         => 'Slow queries',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * Constrain the query to the logs matching the active filters.
     * Ordering is applied separately so callers can run aggregates over
     * the filtered set without paying for a sort.
     */
    public function apply(Builder $query): Builder
    {
        $this->applyMethods($query);
        $this->applyStatus($query);
        $this->applySubstrings($query);
        $this->applyUser($query);
        $this->applyTimeWindow($query);
        $this->applyThresholds($query);

        return $query;
    }

    public function applySort(Builder $query): Builder
    {
        return $query
            ->orderByDesc(self::SORT_COLUMNS[$this->sort()])
            ->orderByDesc('id');
    }

    /** The active sort key, falling back to 'recent' for anything unrecognised. */
    public function sort(): string
    {
        $sort = (string) $this->request->input('sort', 'recent');

        return array_key_exists($sort, self::SORT_COLUMNS) ? $sort : 'recent';
    }

    /**
     * The selected HTTP methods, uppercased.
     *
     * @return array<int, string>
     */
    public function methods(): array
    {
        $methods = array_map(
            static fn ($method): string => strtoupper(trim((string) $method)),
            (array) $this->request->input('method', [])
        );

        return array_values(array_filter($methods, static fn (string $method): bool => $method !== ''));
    }

    /** Whether any filter (sorting aside) is narrowing the result set. */
    public function isActive(): bool
    {
        $parameters = array_merge(self::PRIMARY_PARAMETERS, self::ADVANCED_PARAMETERS);

        foreach ($parameters as $parameter) {
            if ($this->isFilterActive($parameter)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the collapsed "Advanced" section should start open. */
    public function hasAdvancedFilters(): bool
    {
        foreach (self::ADVANCED_PARAMETERS as $parameter) {
            if ($this->isFilterActive($parameter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One dismissible chip per active filter. Each URL rebuilds the current
     * request without that one filter, so chips work without JavaScript.
     *
     * @return array<int, array{parameter: string, label: string, value: string, url: string}>
     */
    public function activeChips(): array
    {
        $chips = [];

        foreach (self::CHIP_LABELS as $parameter => $label) {
            if (!$this->isFilterActive($parameter)) {
                continue;
            }

            $chips[] = [
                'parameter' => $parameter,
                'label'     => $label,
                'value'     => $this->chipValue($parameter),
                'url'       => $this->urlWithout($parameter),
            ];
        }

        return $chips;
    }

    private function isFilterActive(string $parameter): bool
    {
        return match ($parameter) {
            // '0' is "filled" as far as the request is concerned, so the
            // toggle has to be read as a boolean to stay off when unchecked.
            'slow'   => $this->request->boolean('slow'),
            'method' => $this->methods() !== [],
            default  => $this->request->filled($parameter),
        };
    }

    private function chipValue(string $parameter): string
    {
        return match ($parameter) {
            'method'       => implode(', ', $this->methods()),
            'slow'         => 'yes',
            'min_duration' => $this->request->input('min_duration') . ' ms',
            'min_memory'   => $this->request->input('min_memory') . ' MB',
            default        => (string) $this->request->input($parameter),
        };
    }

    /** The current URL with one filter removed and pagination reset. */
    private function urlWithout(string $parameter): string
    {
        $queryParameters = $this->request->query();
        unset($queryParameters[$parameter], $queryParameters['page']);

        return $queryParameters === []
            ? $this->request->url()
            : $this->request->url() . '?' . http_build_query($queryParameters);
    }

    private function applyMethods(Builder $query): void
    {
        $methods = $this->methods();

        if ($methods !== []) {
            $query->whereIn('method', $methods);
        }
    }

    private function applyStatus(Builder $query): void
    {
        if ($this->request->filled('status')) {
            $query->where('status_code', $this->request->integer('status'));
        }

        $statusClass = (string) $this->request->input('status_class', '');

        if (preg_match('/^([1-5])xx$/', $statusClass, $matches) === 1) {
            $lowestCode = ((int) $matches[1]) * 100;

            $query->whereBetween('status_code', [$lowestCode, $lowestCode + 99]);
        }
    }

    private function applySubstrings(Builder $query): void
    {
        foreach (self::SUBSTRING_COLUMNS as $parameter => $column) {
            if ($this->request->filled($parameter)) {
                $query->where($column, 'like', '%' . $this->request->input($parameter) . '%');
            }
        }
    }

    private function applyUser(Builder $query): void
    {
        if ($this->request->filled('user_id')) {
            $query->where('user_id', $this->request->integer('user_id'));
        }
    }

    private function applyTimeWindow(Builder $query): void
    {
        $from = $this->parseDate($this->request->input('from'));
        $to   = $this->parseDate($this->request->input('to'));

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        // A quick preset only applies when no explicit window was given.
        if ($from !== null || $to !== null) {
            return;
        }

        $windowMinutes = self::RANGE_PRESETS[(string) $this->request->input('range', '')] ?? null;

        if ($windowMinutes !== null) {
            $query->where('created_at', '>=', now()->subMinutes($windowMinutes));
        }
    }

    private function applyThresholds(Builder $query): void
    {
        foreach (self::THRESHOLD_COLUMNS as $parameter => $column) {
            $value = $this->request->input($parameter);

            if ($this->request->filled($parameter) && is_numeric($value)) {
                $query->where($column, '>=', (float) $value);
            }
        }

        if ($this->request->boolean('slow')) {
            $query->where('has_slow_queries', true);
        }
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
