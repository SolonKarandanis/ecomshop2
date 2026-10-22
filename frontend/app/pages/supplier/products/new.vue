<script setup lang="ts">
import type { SupplierProduct } from '~/types/supplierProduct'

definePageMeta({ middleware: ['auth', 'supplier'] })
useSeoMeta({ title: 'New product' })

const toast = useToast()

async function onSaved(product: SupplierProduct) {
  toast.add({ title: `"${product.name}" was created.`, color: 'success' })
  await navigateTo('/supplier/products')
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <ULink
      to="/supplier/products"
      class="text-sm text-muted"
    >
      ← All products
    </ULink>
    <h1 class="text-3xl font-bold">
      New product
    </h1>
    <SupplierProductForm @saved="onSaved" />
  </div>
</template>
