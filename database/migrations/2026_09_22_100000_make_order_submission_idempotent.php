<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One checkout attempt = one order, enforced by the DATABASE.
 *
 * `orders.order_uniq_id` is the id the checkout page generates once per attempt; a repeat click sends the same
 * id. Until now it was protected only by a 30-second cache lock and a "look, then insert" check, so a slow first
 * request (still running when the lock expired, or not yet committed when the retry looked) let a second order
 * through. A unique index makes that impossible whatever the timing; the application turns the resulting error
 * into "you already placed this order" (OrdersController::placeOrder).
 *
 * Existing data: blank ids become NULL (NULLs never clash), and where earlier double-submits left several orders
 * sharing an id, the FIRST keeps it and the later copies have it cleared. No order is changed or deleted — only
 * that technical id — and every affected pair is written to storage/app/duplicate-orders-<time>.csv (and the log)
 * so the double orders can be reviewed and, if they really are doubles, cancelled.
 */
return new class extends Migration
{
    public function up()
    {
        DB::table('orders')->where('order_uniq_id', '')->update(['order_uniq_id' => null]);

        $groups = DB::table('orders')->whereNotNull('order_uniq_id')->groupBy('order_uniq_id')
            ->havingRaw('COUNT(*) > 1')->pluck('order_uniq_id');

        $report = [];
        foreach ($groups as $uuid) {
            $copies = DB::table('orders as o')->leftJoin('users as u', 'u.id', '=', 'o.user_id')
                ->where('o.order_uniq_id', $uuid)->orderBy('o.id')
                ->get(['o.id', 'o.order_number', 'o.order_status', 'o.payment_status', 'o.total', 'o.created_at', 'u.email']);
            $kept = $copies->first();
            foreach ($copies->slice(1) as $copy) {
                DB::table('orders')->where('id', $copy->id)->update(['order_uniq_id' => null]);
                $report[] = [$kept->order_number, $kept->order_status, $kept->payment_status, $copy->order_number, $copy->order_status, $copy->payment_status, $copy->total, $copy->email, $copy->created_at];
            }
        }
        if ($report) {
            $this->writeReport($report);
        }

        // the plain index on this column is replaced by the unique one
        foreach (Schema::getIndexes('orders') as $index) {
            if ($index['columns'] === ['order_uniq_id'] && !$index['unique'] && !$index['primary']) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropIndex($index['name']));
            }
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_uniq_id', 'orders_order_uniq_id_unique');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_order_uniq_id_unique');
            $table->index('order_uniq_id');
        });
    }

    private function writeReport(array $rows): void
    {
        $path = storage_path('app/duplicate-orders-' . date('Ymd-His') . '.csv');
        $out = fopen($path, 'w');
        fputcsv($out, ['first order', 'status', 'payment', 'DUPLICATE order', 'status', 'payment', 'total', 'customer email', 'placed']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);

        Log::warning(count($rows) . ' duplicate orders (same checkout id) were found and their duplicate id cleared; the pairs are listed in ' . $path);
    }
};
