<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * One report = one class. It declares its filters and columns and produces rows; the
 * controller, the on-screen table, the CSV stream and the Excel export are all generic.
 *
 * A report is one of two shapes:
 *   - a LISTING (many rows, so paged in SQL): override query() — it must have a stable,
 *     total ORDER BY so paging and exports never repeat or skip a row;
 *   - a SUMMARY (a bounded set of rows built in PHP, e.g. a statement): override all().
 *
 * Row values are plain scalars keyed by column key. A row may also carry `_kind`
 * (header|line|subtotal|total|opening|closing) which only affects on-screen styling.
 */
abstract class Report
{
    /** Excel is built in the browser, so it is capped; CSV streams and has no cap */
    public const EXCEL_ROW_LIMIT = 25000;

    abstract public function key(): string;

    abstract public function title(): string;

    /** Sales | Inventory | Customers | Finance */
    abstract public function group(): string;

    abstract public function description(): string;

    /** @return array<int,array> see Column */
    abstract public function columns(array $filters = []): array;

    /** @return array<int,array> see Filter */
    public function filters(): array
    {
        return [];
    }

    /** every one of these permissions is required */
    public function permissions(): array
    {
        return ['view reports'];
    }

    /** an optional note shown under the title, for definitions the reader should know */
    public function note(): ?string
    {
        return null;
    }

    public function query(array $f): ?Builder
    {
        return null;
    }

    /** @return array<int,array> */
    protected function all(array $f): array
    {
        return [];
    }

    /** headline figures: [['label' => 'Net sales', 'value' => 1234.5, 'type' => 'money'], …] */
    public function summary(array $f): array
    {
        return [];
    }

    /** an optional final "Total" row for summary-type reports (shown and exported) */
    public function footer(array $f): ?array
    {
        return null;
    }

    /** runs before anything is read (e.g. bring the ledger up to date) */
    public function prepare(array $f): void
    {
    }

    private array $memo = [];

    /** compute once per request: rows, footer and summary all need the same numbers */
    protected function remember(string $name, array $f, callable $compute)
    {
        return $this->memo[$name . md5(json_encode($f))] ??= $compute();
    }

    protected function shape($row): array
    {
        return (array) $row;
    }

    /** turn raw query rows into output rows; override to add derived columns */
    protected function shapeRows(array $rows, array $f): array
    {
        return array_map(fn ($r) => $this->shape($r), $rows);
    }

    // ------------------------------------------------------------------ run

    public function allowedFor($user): bool
    {
        foreach ($this->permissions() as $permission) {
            if (!$user || !$user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{rows: array, total: int} one page of rows, and how many there are in all */
    public function page(array $f, int $page, int $perPage): array
    {
        if ($query = $this->query($f)) {
            $p = $query->paginate($perPage, ['*'], 'page', $page);

            return ['rows' => $this->shapeRows($p->items(), $f), 'total' => $p->total()];
        }

        $rows = $this->all($f);

        return ['rows' => array_slice($rows, ($page - 1) * $perPage, $perPage), 'total' => count($rows)];
    }

    /** every row, lazily — for the CSV stream */
    public function each(array $f, int $chunk = 1000): \Generator
    {
        if ($query = $this->query($f)) {
            $page = 1;
            do {
                $rows = (clone $query)->forPage($page++, $chunk)->get()->all();
                yield from $this->shapeRows($rows, $f);
            } while (count($rows) === $chunk);

            return;
        }

        yield from $this->all($f);
    }

    // ------------------------------------------------------------------ filters

    /** what the browser needs to draw this report's controls (rules are server-side only) */
    public function describe(): array
    {
        return [
            'key' => $this->key(), 'title' => $this->title(), 'group' => $this->group(),
            'description' => $this->description(), 'note' => $this->note(),
            'filters' => array_map(fn ($s) => Arr::except($s, 'rules'), $this->filters()),
            'columns' => $this->columns([]),
        ];
    }

    /**
     * Validate the query string against the filter spec and fill in defaults.
     * Unknown parameters are ignored; a bad value is a 422, never silently dropped.
     */
    public function resolve(array $input): array
    {
        $rules = [];
        $defaults = [];
        foreach ($this->filters() as $spec) {
            $rules += $spec['rules'];
            if ($spec['type'] === 'date_range') {
                $defaults += $spec['default'];
            } elseif ($spec['default'] !== null) {
                $defaults[$spec['key']] = $spec['default'];
            }
        }

        // what was asked for, over the defaults, so a `required` filter that has a default still passes
        $given = array_filter(Arr::only($input, array_keys($rules)), fn ($v) => $v !== null && $v !== '');
        $f = array_filter(Validator::make($given + $defaults, $rules)->validate(), fn ($v) => $v !== null && $v !== '');

        foreach ($this->filters() as $spec) {
            if ($spec['type'] === 'date_range' && Carbon::parse($f['to'])->lt(Carbon::parse($f['from']))) {
                throw ValidationException::withMessages(['to' => 'The end date cannot be before the start date.']);
            }
        }

        return $f;
    }
}
