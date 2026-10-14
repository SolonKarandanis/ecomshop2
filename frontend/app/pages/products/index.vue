<script setup lang="ts">
import type { Paginated, Product } from '~/types/catalogue'
import type { ProductFilters } from '~/utils/catalogueQuery'

useSeoMeta({ title: 'Products' })

const route = useRoute()

// The URL is the source of truth for filters, so a reload or a shared link
// SSR-renders exactly the same results.
const filters = computed(() => filtersFromQuery(route.query))
const query = computed(() => queryFromFilters(filters.value))

const [{ data: products, error }, { data: categories }] = await Promise.all([
  useCatalogFetch<Paginated<Product>>('/products', { query }),
  useCategories(),
])

if (error.value) {
  throw createError({ statusCode: error.value.statusCode ?? 500, fatal: true })
}

function applyFilters(next: ProductFilters) {
  return navigateTo({ query: queryFromFilters(next) })
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-3xl font-bold">
      Products
    </h1>

    <ProductFilters
      :filters="filters"
      :categories="categories ?? []"
      @change="applyFilters"
    />

    <ProductGrid :products="products?.data ?? []" />

    <UPagination
      v-if="products && products.meta.last_page > 1"
      :page="filters.page"
      :total="products.meta.total"
      :items-per-page="products.meta.per_page"
      :to="page => ({ query: queryFromFilters({ ...filters, page }) })"
      class="flex justify-center"
    />
  </div>
</template>
