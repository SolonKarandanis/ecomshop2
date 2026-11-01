<script setup lang="ts">
import type { SupplierProduct } from '~/types/supplierProduct'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: ['auth', 'supplier'] })
useSeoMeta({ title: () => t('supplier.products.new') })

const toast = useToast()

async function onSaved(product: SupplierProduct) {
  toast.add({ title: t('supplier.products.created', { name: product.name }), color: 'success' })
  await navigateTo(localePath('/supplier/products'))
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <ULink
      :to="localePath('/supplier/products')"
      class="text-sm text-muted"
    >
      {{ t('supplier.products.back') }}
    </ULink>
    <h1 class="text-3xl font-bold">
      {{ t('supplier.products.new') }}
    </h1>
    <SupplierProductForm @saved="onSaved" />
  </div>
</template>
