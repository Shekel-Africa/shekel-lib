<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->index()->primary();
            $table->string('log')->default('default');
            $table->string('description')->nullable();
            $table->string('url');
            $table->json('headers')->nullable();
            $table->string('ip')->nullable();
            $table->foreignUuid('initiator_id');
            $table->foreignUuid('actor_id')->nullable();
            $table->string('status');
            $table->json('properties');
            $table->json('response_data');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
};
