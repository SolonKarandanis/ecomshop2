<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Paginated } from '~/types/catalogue'
import type { SupplierProduct } from '~/types/supplierProduct'

definePageMeta({ middleware: ['auth', 'supplier'] })
useSeoMeta({ title: 'My products' })

const route = useRoute()
const toast = useToast()

// The URL holds the page, so a reload keeps it.
const page = computed(() => Number(route.query.page) || 1)

const { data: products, error, status: loadStatus, refresh } = await useAsyncData(
  'supplier-products',
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
    toast.add({ title: `"${product.name}" was deleted.`, color: 'success' })
    deleting.value = null
    await refresh()
  }
  catch (e) {
    // e.g. 409: the Product is in existing Orders, so it can only be deactivated.
    toast.add({ title: apiErrorMessage(e, 'The product couldn\'t be deleted. Please try again.'), color: 'error' })
    deleting.value = null
  }
  finally {
    deletePending.value = false
  }
}

const columns: TableColumn<SupplierProduct>[] = [
  { accessorKey: 'name', header: 'Product' },
  { accessorKey: 'price', header: 'Price' },
  { id: 'status', header: 'Status' },
  { id: 'actions', header: '' },
]
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-3xl font-bold">
        My products
      </h1>
      <UButton
        to="/supplier/products/new"
        label="New product"
        icon="i-lucide-plus"
      />
    </div>

    <UAlert
      v-if="forbidden"
      color="warning"
      variant="subtle"
      title="Your Supplier account can't manage products right now."
      description="If your account was deactivated, contact an admin."
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
        empty="You haven't added any products yet."
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
              :label="row.original.is_active ? 'Active' : 'Inactive'"
              :color="row.original.is_active ? 'success' : 'neutral'"
              variant="subtle"
            />
            <UBadge
              :label="row.original.in_stock ? 'In stock' : 'Out of stock'"
              :color="row.original.in_stock ? 'info' : 'warning'"
              variant="outline"
            />
            <UBadge
              v-if="row.original.on_sale"
              label="On sale"
              color="primary"
              variant="outline"
            />
          </div>
        </template>
        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-2">
            <UButton
              :to="`/supplier/products/${row.original.id}/edit`"
              label="Edit"
              size="sm"
              color="neutral"
              variant="outline"
            />
            <UButton
              label="Delete"
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
      :title="`Delete ${deleting?.name ?? 'this product'}?`"
      description="It will be removed from the catalogue and from Buyers' carts. This can't be undone."
      @update:open="(open: boolean) => { if (!open) deleting = null }"
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            label="Keep product"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="deleting = null"
          />
          <UButton
            label="Delete product"
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
