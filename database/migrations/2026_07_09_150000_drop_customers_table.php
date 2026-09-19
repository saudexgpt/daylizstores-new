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
        // The `customers` table was never wired up correctly (missing the
        // `user_id` column the App\Customer model and controllers assumed)
        // and has zero rows in production. Customer accounts live entirely
        // on the `users` table (role = 'customer').
        Schema::dropIfExists('customers');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('phone');
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
