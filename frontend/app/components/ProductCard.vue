<script setup lang="ts">
import type { Product } from '~/types/catalogue'

defineProps<{ product: Product }>()
</script>

<template>
  <UCard
    :ui="{ body: 'p-0 sm:p-0' }"
    class="overflow-hidden"
  >
    <NuxtLink
      :to="`/products/${product.slug}`"
      class="block"
    >
      <!-- The `thumb` conversion is 100x100 (Product::registerMediaConversions); shown at its own size, not stretched. -->
      <div class="flex h-32 items-center justify-center bg-elevated">
        <img
          :src="product.thumbnail"
          :alt="product.name"
          width="100"
          height="100"
          loading="lazy"
          class="size-25 object-contain"
        >
      </div>
      <div class="space-y-1.5 p-3">
        <div class="flex flex-wrap gap-1">
          <UBadge
            v-if="product.on_sale"
            label="On sale"
            color="error"
            variant="subtle"
          />
          <UBadge
            v-if="!product.in_stock"
            label="Out of stock"
            color="neutral"
            variant="subtle"
          />
        </div>
        <h3 class="line-clamp-2 text-sm font-medium">
          {{ product.name }}
        </h3>
        <div class="flex items-center justify-between text-sm">
          <span class="font-semibold">{{ formatPrice(product.price) }}</span>
          <span
            v-if="product.average_rating !== null"
            class="text-muted"
          >
            ★ {{ product.average_rating.toFixed(1) }} ({{ product.reviews_count }})
          </span>
        </div>
      </div>
    </NuxtLink>
  </UCard>
</template>
