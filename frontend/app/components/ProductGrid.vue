<script setup lang="ts">
import type { Product } from '~/types/catalogue'

defineProps<{ products: Product[] }>()
</script>

<template>
  <div
    v-if="products.length"
    class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
  >
    <ProductCard
      v-for="product in products"
      :key="product.id"
      :product="product"
    />
  </div>
  <p
    v-else
    class="py-10 text-center text-muted"
  >
    No products match these filters.
  </p>
</template>
