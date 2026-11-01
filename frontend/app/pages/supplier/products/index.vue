<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Paginated } from '~/types/catalogue'
import type { SupplierProduct } from '~/types/supplierProduct'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: ['auth', 'supplier'] })
useSeoMeta({ title: () => t('supplier.products.title') })

const route = useRoute()
const toast = useToast()

// The URL holds the page, so a reload keeps it.
const page = computed(() => Number(route.query.page) || 1)

const { data: products, error, status: loadStatus, refresh } = await useAsyncData(
  // Per language: product and category names come back translated.
  useLocaleKey('supplier-products'),
  () => useApi()<Paginated<SupplierProduct>>('/supplier-products', {
    query: { page: page.value, perPage: 20 },
  }),
  { watch: [page] },
)

const forbidden = computed(() => apiStatus(error.value) === 403)

const deleting = ref<SupplierProduct | null>(null)
const deletePending = ref(false)

async function confirmDelete() {
  const product = deleting.value
  if (!product) {
    return
  }

  deletePending.value = true
  try {
    await useApi()(`/supplier-products/${product.id}`, { method: 'DELETE' })
    toast.add({ title: t('supplier.products.deleted', { name: product.name }), color: 'success' })
    deleting.value = null
    await refresh()
  }
  catch (e) {
    // e.g. 409: the Product is in existing Orders, so it can only be deactivated.
    toast.add({ title: apiErrorMessage(e, t('supplier.products.delete_error')), color: 'error' })
    deleting.value = null
  }
  finally {
    deletePending.value = false
  }
}

const columns = computed<TableColumn<SupplierProduct>[]>(() => [
  { accessorKey: 'name', header: t('supplier.products.column.product') },
  { accessorKey: 'price', header: t('supplier.products.column.price') },
  { id: 'status', header: t('supplier.products.column.status') },
  { id: 'actions', header: '' },
])
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-3xl font-bold">
        {{ t('supplier.products.title') }}
      </h1>
      <UButton
        :to="localePath('/supplier/products/new')"
        :label="t('supplier.products.new')"
        icon="i-lucide-plus"
      />
    </div>

    <UAlert
      v-if="forbidden"
      color="warning"
      variant="subtle"
      :title="t('supplier.products.forbidden')"
      :description="t('supplier.orders.forbidden_hint')"
    />

    <UAlert
      v-else-if="error"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(error)"
    />

    <template v-else>
      <UTable
        :data="products?.data ?? []"
        :columns="columns"
        :loading="loadStatus === 'pending'"
        :empty="t('supplier.products.empty')"
      >
        <template #name-cell="{ row }">
          <div class="flex items-center gap-3">
            <img
              v-if="row.original.images?.[0]"
              :src="row.original.images[0].thumbnail || row.original.images[0].url"
              alt=""
              class="size-10 rounded object-cover"
            >
            <div>
              <p class="font-medium">
                {{ row.original.name }}
              </p>
              <p class="text-xs text-muted">
                {{ row.original.category?.name }}<template v-if="row.original.category && row.original.brand">
                  ·
                </template>{{ row.original.brand?.name }}
              </p>
            </div>
          </div>
        </template>
        <template #price-cell="{ row }">
          {{ formatPrice(row.original.price) }}
        </template>
        <template #status-cell="{ row }">
          <div class="flex flex-wrap gap-1">
            <UBadge
              :label="row.original.is_active ? t('supplier.products.active') : t('supplier.products.inactive')"
              :color="row.original.is_active ? 'success' : 'neutral'"
              variant="subtle"
            />
            <UBadge
              :label="row.original.in_stock ? t('product.in_stock') : t('product.badge.out_of_stock')"
              :color="row.original.in_stock ? 'info' : 'warning'"
              variant="outline"
            />
            <UBadge
              v-if="row.original.on_sale"
              :label="t('product.badge.on_sale')"
              color="primary"
              variant="outline"
            />
          </div>
        </template>
        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-2">
            <UButton
              :to="localePath(`/supplier/products/${row.original.id}/edit`)"
              :label="t('supplier.products.edit')"
              size="sm"
              color="neutral"
              variant="outline"
            />
            <UButton
              :label="t('supplier.products.delete')"
              size="sm"
              color="error"
              variant="outline"
              class="cursor-pointer"
              @click="deleting = row.original"
            />
          </div>
        </template>
      </UTable>

      <UPagination
        v-if="products && products.meta.last_page > 1"
        :page="page"
        :total="products.meta.total"
        :items-per-page="products.meta.per_page"
        :to="(p: number) => ({ query: { ...route.query, page: p } })"
        class="flex justify-center"
      />
    </template>

    <UModal
      :open="deleting !== null"
      :title="t('supplier.products.delete_title', { name: deleting?.name ?? t('supplier.products.this_product') })"
      :description="t('supplier.products.delete_description')"
      @update:open="(open: boolean) => { if (!open) deleting = null }"
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            :label="t('supplier.products.keep')"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="deleting = null"
          />
          <UButton
            :label="t('supplier.products.confirm_delete')"
            color="error"
            class="cursor-pointer"
            :loading="deletePending"
            @click="confirmDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
