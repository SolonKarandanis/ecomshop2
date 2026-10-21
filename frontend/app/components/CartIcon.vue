<script setup lang="ts">
const cart = useCartStore()
</script>

<template>
  <UButton
    to="/cart"
    icon="i-lucide-shopping-cart"
    color="neutral"
    variant="ghost"
    :aria-label="`Cart, ${cart.productCount} ${cart.productCount === 1 ? 'product' : 'products'}`"
  >
    <UBadge
      v-if="cart.productCount > 0"
      :label="String(cart.productCount)"
      size="sm"
    />
  </UButton>
</template>
