<?php

namespace App\Console\Commands;

use App\Services\Accounting\LedgerException;
use App\Services\Accounting\SalesPoster;
use Illuminate\Console\Command;

class PostSalesToLedger extends Command
{
    protected $signature = 'accounting:post-sales {--from= : first day to (re)check, Y-m-d} {--to= : last day, Y-m-d}';

    protected $description = 'Post paid orders to the ledger as daily sales summaries (idempotent — posts only what changed since the last run)';

    public function handle(SalesPoster $poster)
    {
        try {
            $result = $poster->sync($this->option('from') ?: null, $this->option('to') ?: null);
        } catch (LedgerException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        if ($result['skipped']) {
            $this->warn('Another sales sync is already running — nothing done.');

            return 0;
        }

        $this->info(sprintf(
            'Checked %d day(s); posted %d entr%s (net %s).',
            $result['days'],
            $result['entries'],
            $result['entries'] === 1 ? 'y' : 'ies',
            number_format($result['net_adjustment'], 2)
        ));

        return 0;
    }
}
