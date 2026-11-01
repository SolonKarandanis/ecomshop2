<script setup lang="ts">
const localePath = useLocalePath()
const { t } = useI18n()

const cart = useCartStore()
</script>

<template>
  <UButton
    :to="localePath('/cart')"
    icon="i-lucide-shopping-cart"
    color="neutral"
    variant="ghost"
    :aria-label="t('cart.icon_label', { count: cart.productCount }, cart.productCount)"
  >
    <UBadge
      v-if="cart.productCount > 0"
      :label="String(cart.productCount)"
      size="sm"
    />
  </UButton>
</template>
