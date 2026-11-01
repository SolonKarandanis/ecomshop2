<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product names/descriptions and category names become translatable (#81), in
 * translation tables rather than JSON columns: they keep normal TEXT columns
 * and collation, so MariaDB FULLTEXT search stays case- and accent-insensitive
 * and can be limited to one language (#77).
 *
 * Existing values move by the language they're in (confirmed in #81): product
 * names and descriptions are Greek, category names are English. Brands aren't
 * translated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->unique(['product_id', 'locale']);
        });

        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->unique(['category_id', 'locale']);
        });

        DB::table('product_translations')->insertUsing(
            ['product_id', 'locale', 'name', 'description'],
            DB::table('products')->select('id', DB::raw("'el'"), 'name', 'description'),
        );
        DB::table('category_translations')->insertUsing(
            ['category_id', 'locale', 'name'],
            DB::table('categories')->select('id', DB::raw("'en'"), 'name'),
        );

        if ($this->supportsFullText()) {
            // The index the search engine now uses; the old one goes with its columns.
            Schema::table('product_translations', function (Blueprint $table) {
                $table->fullText(['name', 'description']);
            });

            if (Schema::hasIndex('products', ['name', 'description'], 'fulltext')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->dropFullText(['name', 'description']);
                });
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name')->default('')->after('brand_id');
            $table->longText('description')->nullable()->after('slug');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->default('')->after('id');
        });

        // Each row gets back the language it came from, or the first other one
        // that exists if it was added later.
        foreach (DB::table('products')->pluck('id') as $productId) {
            $translation = $this->preferred('product_translations', 'product_id', $productId, ['el', 'en', 'tr']);
            if ($translation !== null) {
                DB::table('products')->where('id', $productId)->update([
                    'name' => $translation->name,
                    'description' => $translation->description,
                ]);
            }
        }
        foreach (DB::table('categories')->pluck('id') as $categoryId) {
            $translation = $this->preferred('category_translations', 'category_id', $categoryId, ['en', 'el', 'tr']);
            if ($translation !== null) {
                DB::table('categories')->where('id', $categoryId)->update(['name' => $translation->name]);
            }
        }

        if ($this->supportsFullText()) {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText(['name', 'description']);
            });
        }

        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('category_translations');
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @param  list<string>  $locales  in order of preference
     */
    private function preferred(string $table, string $foreignKey, int $id, array $locales): ?object
    {
        $rows = DB::table($table)->where($foreignKey, $id)->get()->keyBy('locale');

        foreach ($locales as $locale) {
            if ($rows->has($locale)) {
                return $rows->get($locale);
            }
        }

        return null;
    }
};
