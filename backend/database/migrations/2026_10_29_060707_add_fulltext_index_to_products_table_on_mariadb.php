<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The original FULLTEXT migration (2026_07_28_065239) only ran on the `mysql` driver, so
// databases migrated on `mariadb` skipped it. Fresh installs get the index from that
// migration now; this adds it where it was skipped.
return new class extends Migration {
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasIndex('products', ['name', 'description'], 'fulltext')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'description']);
        });
    }

    // Nothing to undo: rolling back the original migration drops the index.
    public function down(): void {}
};
