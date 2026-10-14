<script setup lang="ts">
import type { Product } from '~/types/catalogue'

defineProps<{ products: Product[] }>()
</script>

<template>
  <div
    v-if="products.length"
    class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
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
