<?php

use App\Enums\RolesEnum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()
                ->after('user_id')->constrained('users');
        });

        // Backfill from each Order's items' Products' supplier_id.
        // Single-supplier-per-order is guaranteed (#23's audit found 0 mixed-supplier
        // Orders), so the first matching product per order is unambiguous.
        DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->whereNull('o.supplier_id')
            ->select('o.id', 'p.supplier_id')
            ->orderBy('o.id')
            ->get()
            ->unique('id')
            ->each(function ($row) {
                DB::table('orders')->where('id', $row->id)->update([
                    'supplier_id' => $row->supplier_id,
                ]);
            });

        // Any order still null after that (no items — orphaned/edge-case data) falls back to the first Admin, same as the products migration
        if (DB::table('orders')->whereNull('supplier_id')->exists()) {
            $admin = User::role(RolesEnum::ROLE_ADMIN)->orderBy('id')->first();
            abort_unless($admin !== null, 500, 'Cannot backfill orders.supplier_id: no Admin user exists.');

            DB::table('orders')->whereNull('supplier_id')->update([
                'supplier_id' => $admin->id,
            ]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
