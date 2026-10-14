<script setup lang="ts">
import type { Paginated, Product } from '~/types/catalogue'

useSeoMeta({ title: 'ecomshop' })

const [{ data: featured }, { data: categories }] = await Promise.all([
  useCatalogFetch<Paginated<Product>>('/products', { query: { featured: '1', per_page: '8' } }),
  useCategories(),
])

// Until an admin features some products, show the newest ones instead.
const hasFeatured = !!featured.value?.data.length
const { data: latest } = hasFeatured
  ? { data: ref(null) }
  : await useCatalogFetch<Paginated<Product>>('/products', { query: { per_page: '8' } })

const products = computed(() => (hasFeatured ? featured.value : latest.value)?.data ?? [])
</script>

<template>
  <div class="space-y-12">
    <section>
      <div class="flex items-end justify-between">
        <h1 class="text-3xl font-bold">
          {{ hasFeatured ? 'Featured products' : 'New arrivals' }}
        </h1>
        <UButton
          to="/products"
          label="Browse all"
          variant="link"
        />
      </div>
      <ProductGrid
        :products="products"
        class="mt-6"
      />
    </section>

    <section v-if="categories?.length">
      <h2 class="text-2xl font-bold">
        Shop by category
      </h2>
      <CategoryList
        :categories="categories"
        class="mt-6"
      />
    </section>
  </div>
</template>
