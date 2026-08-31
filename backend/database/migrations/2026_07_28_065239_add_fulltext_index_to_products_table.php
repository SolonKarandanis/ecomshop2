<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(DB::connection()->getDriverName() !== 'mysql') return;
        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name','description']);
        });
    }

    public function down(): void
    {
        if(DB::connection()->getDriverName() !== 'mysql') return;
        Schema::table('products', function (Blueprint $table) {
            $table->dropFulltext(['name','description']);
        });
    }
};
