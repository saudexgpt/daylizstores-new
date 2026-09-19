<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * One-off bridge for a database that pre-dates the current migration set.
 *
 * The live database was built by 13 old migrations (2014_10_12_000000_create_users_table …).
 * The repository now ships a fresh baseline (2026_07_09_114727_create_*_table and
 * 2026_07_09_114730_add_foreign_keys_*) that re-describes those same tables, followed by
 * fix-up migrations. Run on the live database, `php artisan migrate` would therefore try to
 * CREATE tables that already exist and stop on the first one.
 *
 * This command records each baseline migration as already run *when the table it creates
 * (or alters) is really there*, and leaves everything else pending, so the next
 * `php artisan migrate` applies only what the live database is genuinely missing.
 * It never changes the schema or any data — it only writes rows to `migrations`.
 *
 *   php artisan migrate:adopt-legacy --dry-run   (see what it would do)
 *   php artisan migrate:adopt-legacy             (do it)
 *   php artisan migrate
 */
class AdoptLegacySchema extends Command
{
    protected $signature = 'migrate:adopt-legacy {--dry-run : Show what would be recorded without writing anything}';

    protected $description = 'Record the baseline migrations as run on a database that already has the legacy schema';

    // the marker that identifies a database built by the old migration set
    private const LEGACY_MARKER = '2014_10_12_000000_create_users_table';

    public function handle()
    {
        if (!Schema::hasTable('migrations')) {
            $this->error('There is no `migrations` table — this is not an existing database. Just run `php artisan migrate`.');
            return 1;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        if (!in_array(self::LEGACY_MARKER, $ran, true)) {
            $this->info('This database was not built by the legacy migration set — nothing to adopt. Run `php artisan migrate`.');
            return 0;
        }

        $batch = (int) DB::table('migrations')->max('batch') + 1;
        $dry = (bool) $this->option('dry-run');
        $adopted = [];
        $left = [];

        foreach ($this->baselineMigrations() as $name) {
            if (in_array($name, $ran, true)) {
                continue;
            }
            $table = $this->tableFor($name);
            if ($table && Schema::hasTable($table)) {
                $adopted[] = $name;
                if (!$dry) {
                    DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                }
            } else {
                $left[] = $name . ($table ? "  (table `$table` is missing, so it will be created)" : '');
            }
        }

        $this->line(($dry ? '[dry run] would record' : 'Recorded') . ' ' . count($adopted) . ' baseline migrations as already run (their tables exist).');
        foreach ($left as $name) {
            $this->warn('left pending: ' . $name);
        }
        $this->line('Next: php artisan migrate');

        return 0;
    }

    /** the baseline files, i.e. everything from the 2026_07_09_1147xx set */
    private function baselineMigrations(): array
    {
        $names = [];
        foreach (File::files(database_path('migrations')) as $file) {
            $name = $file->getFilenameWithoutExtension();
            if (preg_match('/^2026_07_09_11(4727|4730)_/', $name)) {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    /** create_<table>_table / add_foreign_keys_to_<table>_table -> <table> */
    private function tableFor(string $migration): ?string
    {
        if (preg_match('/_create_(.+)_table$/', $migration, $m) || preg_match('/_add_foreign_keys_to_(.+)_table$/', $migration, $m)) {
            return $m[1];
        }

        return null;
    }
}
