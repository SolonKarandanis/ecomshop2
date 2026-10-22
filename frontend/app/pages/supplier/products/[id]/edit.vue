<script setup lang="ts">
import type { SupplierProduct } from '~/types/supplierProduct'

definePageMeta({ middleware: ['auth', 'supplier'] })

const route = useRoute()
const id = route.params.id as string
const toast = useToast()

const { data: product, error } = await useAsyncData(
  `supplier-product-${id}`,
  () => useApi()<{ data: SupplierProduct }>(`/supplier-products/${id}`).then(response => response.data),
)
useSeoMeta({ title: () => product.value ? `Edit ${product.value.name}` : 'Edit product' })

// 403: another Supplier's Product. 404: no such Product.
const unavailable = computed(() => [403, 404].includes(apiStatus(error.value) ?? 0))

async function onSaved(saved: SupplierProduct) {
  toast.add({ title: `"${saved.name}" was saved.`, color: 'success' })
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

    <UCard
      v-if="unavailable"
      class="mx-auto max-w-md text-center"
    >
      <div class="space-y-4">
        <h1 class="text-xl font-semibold">
          Product unavailable
        </h1>
        <p class="text-muted">
          This product doesn't exist, or it isn't one of yours.
        </p>
        <UButton
          to="/supplier/products"
          label="Back to your products"
        />
      </div>
    </UCard>

    <UAlert
      v-else-if="error"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(error)"
    />

    <template v-else-if="product">
      <h1 class="text-3xl font-bold">
        Edit {{ product.name }}
      </h1>
      <SupplierProductForm
        :product="product"
        @saved="onSaved"
      />
    </template>
  </div>
</template>
