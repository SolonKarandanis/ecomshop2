<?php

use App\Enums\RolesEnum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()
                ->after('brand_id')->constrained('users');
        });

        if(DB::table('products')->whereNull('supplier_id')->exists()){
            $admin = User::role(RolesEnum::ROLE_ADMIN)->orderBy('id')->first();
            abort_unless($admin !== null, 500, 'Cannot backfill products.supplier_id: no Admin user exists.');

            DB::table('products')->whereNull('supplier_id')->update([
                'supplier_id' => $admin->id,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
