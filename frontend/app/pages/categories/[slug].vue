<script setup lang="ts">
import type { Paginated, Product } from '~/types/catalogue'

const route = useRoute()

// The products filter takes category ids, so resolve the slug against the
// (small, shared) active-category list. Inactive or unknown slugs are a 404.
const { data: categories } = await useCategories()
const category = computed(() => categories.value?.find(c => c.slug === route.params.slug))

if (!category.value) {
  throw createError({ statusCode: 404, statusMessage: 'Category not found', fatal: true })
}

const filters = computed(() => ({ ...filtersFromQuery(route.query), categories: [category.value!.id] }))

const { data: products } = await useCatalogFetch<Paginated<Product>>('/products', {
  query: computed(() => ({ ...queryFromFilters(filters.value), per_page: '12' })),
})

useSeoMeta({ title: () => category.value!.name })
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-3xl font-bold">
      {{ category!.name }}
    </h1>

    <ProductGrid :products="products?.data ?? []" />

    <UPagination
      v-if="products && products.meta.last_page > 1"
      :page="filters.page"
      :total="products.meta.total"
      :items-per-page="products.meta.per_page"
      :to="page => ({ query: page > 1 ? { page: String(page) } : {} })"
      class="flex justify-center"
    />
  </div>
</template>
