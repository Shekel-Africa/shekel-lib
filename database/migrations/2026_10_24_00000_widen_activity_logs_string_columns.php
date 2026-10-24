<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * url holds the full request URL (pagination cursors push it past 255 chars),
     * ip holds the raw X-Forwarded-For chain, and user_agent is client-controlled.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->text('url')->change();
            $table->text('ip')->nullable()->change();
            $table->text('user_agent')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Fails if any row now holds a value longer than 255 characters.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('url')->change();
            $table->string('ip')->nullable()->change();
            $table->string('user_agent')->nullable()->change();
        });
    }
};
