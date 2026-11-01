<?php

use App\Dtos\ProductSearchFilterDto;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Http\Requests\ProductSearchRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Search\MeilisearchProductSearchEngine;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * A Product with exactly these translations, e.g. ['el' => ['name' => …]]; the
 * factory's own English name is removed.
 *
 * @param  array<string, array<string, string>>  $translations
 */
function translatedProduct(array $translations, array $attributes = []): Product
{
    $product = Product::factory()->create(['is_active' => true, ...$attributes]);
    $product->translations()->delete();
    $product->fill($translations)->save();

    return $product->fresh();
}

function translationsSupplier(): User
{
    config(['features.suppliers_enabled' => true]);
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);

    return $supplier;
}

/**
 * InnoDB's FULLTEXT index only sees committed rows, and RefreshDatabase keeps
 * each test in a transaction (see CatalogueTest), so search tests commit what
 * they create and delete it afterwards.
 */
function withCommittedProducts(Closure $create, Closure $assert): void
{
    DB::commit();
    $products = collect();

    try {
        $products = collect($create());
        $assert(...$products->all());
    } finally {
        foreach ($products as $product) {
            $product->delete();
            $product->category->delete();
            $product->brand->delete();
            User::whereKey($product->supplier_id)->delete();
        }
        DB::beginTransaction();
    }
}

function searchIds(string $locale, string $term): array
{
    return collect(test()->getJson('/api/products?q='.urlencode($term), ['Accept-Language' => $locale])->assertOk()->json('data'))
        ->pluck('id')
        ->all();
}

describe('reading translations', function () {
    it("returns a Product's name in the request's language, falling back to another that exists", function () {
        $product = translatedProduct([
            'el' => ['name' => 'Έξυπνη Τηλεόραση', 'description' => 'Ελληνική περιγραφή'],
            'tr' => ['name' => 'Akıllı Televizyon'],
        ]);

        $this->getJson("/api/products/{$product->slug}", ['Accept-Language' => 'tr'])
            ->assertJsonPath('data.name', 'Akıllı Televizyon')
            // tr has no description, so that one property falls back.
            ->assertJsonPath('data.description', 'Ελληνική περιγραφή');
        $this->getJson("/api/products/{$product->slug}", ['Accept-Language' => 'el'])
            ->assertJsonPath('data.name', 'Έξυπνη Τηλεόραση');
        // No English yet: the fallback runs en, el, tr.
        $this->getJson("/api/products/{$product->slug}")
            ->assertJsonPath('data.name', 'Έξυπνη Τηλεόραση');
    });

    it('shows an English-only category name on a Greek page', function () {
        Category::factory()->create(['is_active' => true, 'name' => 'Laptops']);

        $this->getJson('/api/categories', ['Accept-Language' => 'el'])
            ->assertJsonPath('data.0.name', 'Laptops');
    });

    it('loads translations for a whole listing in one query, not one per Product', function () {
        $translationQueries = function (int $products): int {
            Product::query()->delete();
            Product::factory()->count($products)->create(['is_active' => true]);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/products')->assertOk();
            $count = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'product_translations'))->count();
            DB::disableQueryLog();

            return $count;
        };

        expect($translationQueries(2))->toBe(1)
            ->and($translationQueries(6))->toBe(1);
    });
});

describe('search', function () {
    it("finds a Product by the active language's text, and not by another language's", function () {
        withCommittedProducts(
            fn () => [
                translatedProduct(['el' => ['name' => 'Samsung Smart Τηλεόραση'], 'tr' => ['name' => 'Samsung Akıllı Televizyon']]),
                translatedProduct(['el' => ['name' => 'Φωτιστικό γραφείου'], 'tr' => ['name' => 'Masa lambası']]),
            ],
            function (Product $tv) {
                expect(searchIds('el', 'Τηλεόραση'))->toBe([$tv->id])
                    ->and(searchIds('el', 'Televizyon'))->toBe([])
                    ->and(searchIds('tr', 'Televizyon'))->toBe([$tv->id])
                    ->and(searchIds('tr', 'Τηλεόραση'))->toBe([])
                    // No English text: an English page shows, and searches, the fallback.
                    ->and(searchIds('en', 'Τηλεόραση'))->toBe([$tv->id]);
            },
        );
    });

    it('matches lowercase, unaccented Greek on MariaDB FULLTEXT', function () {
        withCommittedProducts(
            fn () => [translatedProduct(['el' => ['name' => 'Samsung Smart Τηλεόραση 85"']])],
            fn (Product $tv) => expect(searchIds('el', 'τηλεοραση'))->toBe([$tv->id]),
        );
    })->skip(fn () => ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true), 'Case and accent folding comes from the MariaDB collation; CI runs this.');

    it("indexes each language's shown text for Scout, fallback included", function () {
        $product = translatedProduct(['el' => ['name' => 'Τηλεόραση', 'description' => 'Περιγραφή'], 'tr' => ['name' => 'Televizyon']]);

        expect($product->toSearchableArray())->toMatchArray([
            'name_el' => 'Τηλεόραση',
            'name_tr' => 'Televizyon',
            // No English: the storefront shows the Greek, so that's what's indexed.
            'name_en' => 'Τηλεόραση',
            'description_en' => 'Περιγραφή',
        ])->not->toHaveKey('name');
    });

    it("searches only the active language's fields on Meilisearch", function () {
        $engine = new class extends Engine
        {
            public array $options = [];

            public function update($models) {}

            public function delete($models) {}

            public function search(ScoutBuilder $builder)
            {
                $this->options = $builder->options;

                return [];
            }

            public function paginate(ScoutBuilder $builder, $perPage, $page)
            {
                return $this->search($builder);
            }

            public function mapIds($results)
            {
                return collect();
            }

            public function map(ScoutBuilder $builder, $results, $model)
            {
                return $model->newCollection();
            }

            public function lazyMap(ScoutBuilder $builder, $results, $model)
            {
                return $model->newCollection()->lazy();
            }

            public function getTotalCount($results)
            {
                return 0;
            }

            public function flush($model) {}

            public function createIndex($name, array $options = []) {}

            public function deleteIndex($name) {}
        };
        app(EngineManager::class)->extend('recording', fn () => $engine);
        config(['scout.driver' => 'recording']);
        app()->setLocale('tr');

        app(MeilisearchProductSearchEngine::class)->search(ProductSearchFilterDto::fromRequest(ProductSearchRequest::create('/api/products', 'GET', ['q' => 'televizyon'])));

        expect($engine->options['attributesToSearchOn'])->toBe(['name_tr', 'description_tr']);
    });
});

describe('supplier products', function () {
    it('creates a Product with a name and description per language', function () {
        $supplier = translationsSupplier();

        $response = $this->actingAs($supplier)->postJson('/api/supplier-products', [
            'el' => ['name' => 'Τηλεόραση 55"', 'description' => 'Ελληνικά'],
            'tr' => ['name' => 'Televizyon 55"'],
            'price' => 499,
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
        ])->assertCreated();

        $product = Product::findOrFail($response->json('data.id'));
        expect($product->translations->pluck('name', 'locale')->all())->toEqualCanonicalizing(['el' => 'Τηλεόραση 55"', 'tr' => 'Televizyon 55"'])
            // No name in the request's language, so the slug comes from the first language block.
            ->and($product->slug)->toBe('tileorasi-55')
            ->and($response->json('data.translations.tr.name'))->toBe('Televizyon 55"');
    });

    it("saves a plain name as the request's language", function () {
        $supplier = translationsSupplier();

        $response = $this->actingAs($supplier)->postJson('/api/supplier-products', [
            'name' => 'Φορητός υπολογιστής',
            'price' => 999,
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
        ], ['Accept-Language' => 'el'])->assertCreated();

        expect(Product::findOrFail($response->json('data.id'))->translations->pluck('name', 'locale')->all())
            ->toBe(['el' => 'Φορητός υπολογιστής']);
    });

    it('adds a language to an existing Product', function () {
        $supplier = translationsSupplier();
        $product = translatedProduct(['el' => ['name' => 'Τηλεόραση']], ['supplier_id' => $supplier->id]);

        $this->actingAs($supplier)
            ->patchJson("/api/supplier-products/{$product->id}", ['en' => ['name' => 'Smart TV']])
            ->assertOk()
            ->assertJsonPath('data.translations.en.name', 'Smart TV')
            ->assertJsonPath('data.translations.el.name', 'Τηλεόραση');
    });

    it('requires a name in at least one language, and one in every language block sent', function () {
        $supplier = translationsSupplier();
        $base = ['price' => 10, 'category_id' => Category::factory()->create()->id, 'brand_id' => Brand::factory()->create()->id];

        $this->actingAs($supplier)->postJson('/api/supplier-products', $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->postJson('/api/supplier-products', [...$base, 'name' => 'Lamp', 'el' => ['description' => 'Χωρίς όνομα']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('el.name');
    });
});

describe('Filament', function () {
    beforeEach(function () {
        Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
        $admin = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
        $admin->assignRole(RolesEnum::ROLE_ADMIN->value);
        $this->actingAs($admin);
    });

    it('creates a category with a name in one language, and the slug from it', function () {
        Livewire::test(CreateCategory::class)
            ->fillForm(['el' => ['name' => 'Τηλεοράσεις'], 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->latest('id')->firstOrFail();
        expect($category->translations->pluck('name', 'locale')->all())->toBe(['el' => 'Τηλεοράσεις'])
            ->and($category->slug)->toBe('tileoraseis');
    });

    it('requires a category name in at least one language', function () {
        Livewire::test(CreateCategory::class)
            ->fillForm(['slug' => 'nameless', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['en.name' => 'required']);

        expect(Category::count())->toBe(0);
    });

    it("fills a product's languages into their tabs, and deletes one the admin clears", function () {
        $product = translatedProduct(['el' => ['name' => 'Τηλεόραση'], 'tr' => ['name' => 'Televizyon']]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet(['el.name' => 'Τηλεόραση', 'tr.name' => 'Televizyon'])
            ->fillForm(['tr' => ['name' => ''], 'en' => ['name' => 'Smart TV']])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->fresh()->translations->pluck('name', 'locale')->all())
            ->toEqualCanonicalizing(['el' => 'Τηλεόραση', 'en' => 'Smart TV']);
    });
});
