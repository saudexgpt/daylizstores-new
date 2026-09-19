<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            // BIGINT UNSIGNED, as it actually is in the live database (this used to say
            // increments() = INT, which made the foreign key on order_items impossible there)
            $table->bigIncrements('id');
            $table->string('order_uniq_id')->nullable();
            $table->integer('user_id')->index('user_id');
            $table->string('location');
            $table->string('order_number')->nullable();
            $table->enum('order_status', ['Pending', 'On Transit', 'Delivered', 'Cancelled', 'CARP'])->default('Pending');
            $table->enum('payment_status', ['pending', 'paid', 'cancelled', 'carp'])->default('pending');
            $table->double('amount', 10, 2);
            $table->string('payment_method', 50)->default('Bank Deposit/Transfer');
            $table->string('receipt_image')->nullable();
            $table->double('delivery_cost', 10, 2)->default(0);
            $table->double('total', 10, 2);
            $table->text('address')->nullable();
            $table->string('nearest_bustop')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('valid_till')->useCurrent();
            $table->tinyInteger('cancelled_status_reversed')->default(0);
            $table->tinyInteger('bulk_order_cancellation')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['order_number', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
