<script setup lang="ts">
const cart = useCartStore()
</script>

<template>
  <UButton
    to="/cart"
    icon="i-lucide-shopping-cart"
    color="neutral"
    variant="ghost"
    :aria-label="`Cart, ${cart.itemCount} items`"
  >
    <UBadge
      v-if="cart.itemCount > 0"
      :label="String(cart.itemCount)"
      size="sm"
    />
  </UButton>
</template>
