<script setup lang="ts">
import type { SupplierProduct } from '~/types/supplierProduct'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: ['auth', 'supplier'] })

const route = useRoute()
const id = route.params.id as string
const toast = useToast()

const { data: product, error } = await useAsyncData(
  // Per language: the name and description come back in the page's language.
  useLocaleKey(`supplier-product-${id}`),
  () => useApi()<{ data: SupplierProduct }>(`/supplier-products/${id}`).then(response => response.data),
)
useSeoMeta({ title: () => product.value ? t('supplier.products.edit_title', { name: product.value.name }) : t('supplier.products.edit_fallback') })

// 403: another Supplier's Product. 404: no such Product.
const unavailable = computed(() => [403, 404].includes(apiStatus(error.value) ?? 0))

async function onSaved(saved: SupplierProduct) {
  toast.add({ title: t('supplier.products.saved', { name: saved.name }), color: 'success' })
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

    <UCard
      v-if="unavailable"
      class="mx-auto max-w-md text-center"
    >
      <div class="space-y-4">
        <h1 class="text-xl font-semibold">
          {{ t('supplier.products.unavailable') }}
        </h1>
        <p class="text-muted">
          {{ t('supplier.products.unavailable_text') }}
        </p>
        <UButton
          :to="localePath('/supplier/products')"
          :label="t('supplier.products.back_to_products')"
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
        {{ t('supplier.products.edit_title', { name: product.name }) }}
      </h1>
      <SupplierProductForm
        :product="product"
        @saved="onSaved"
      />
    </template>
  </div>
</template>
