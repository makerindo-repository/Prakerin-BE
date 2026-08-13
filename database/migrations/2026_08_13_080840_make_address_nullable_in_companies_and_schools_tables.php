<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Make 'address' nullable in companies and schools tables,
     * since address is not required at registration time.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('address')->nullable()->change();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->text('address')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('address')->nullable(false)->change();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->text('address')->nullable(false)->change();
        });
    }
};
