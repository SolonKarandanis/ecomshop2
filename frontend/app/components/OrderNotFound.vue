<script setup lang="ts">
const localePath = useLocalePath()
const { t } = useI18n()
</script>

<template>
  <UCard class="mx-auto max-w-md text-center">
    <div class="space-y-4">
      <h1 class="text-xl font-semibold">
        {{ t('orders.not_found.title') }}
      </h1>
      <p class="text-muted">
        {{ t('orders.not_found.text') }}
      </p>
      <div class="flex justify-center gap-2">
        <UButton
          :to="localePath('/orders')"
          :label="t('nav.my_orders')"
        />
        <UButton
          :to="localePath('/products')"
          :label="t('common.keep_shopping')"
          color="neutral"
          variant="outline"
        />
      </div>
    </div>
  </UCard>
</template>
