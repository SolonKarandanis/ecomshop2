<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Paginated } from '~/types/catalogue'
import type { Order } from '~/types/order'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: () => t('orders.title') })

const route = useRoute()

// The URL holds the page, so a reload keeps it.
const page = computed(() => Number(route.query.page) || 1)

// The API lists only the logged-in Buyer's own Orders, newest first.
const { data: orders, error, status: loadStatus } = await useAsyncData(
  'my-orders',
  () => useApi()<Paginated<Order>>('/orders', { query: { page: page.value, perPage: 20 } }),
  { watch: [page] },
)

const exporting = ref(false)
const exportError = ref<string | null>(null)

async function exportOrders() {
  exporting.value = true
  exportError.value = null
  try {
    const file = await useApi()<Blob>('/orders/export', { responseType: 'blob' })
    saveBlob(file, 'orders.xlsx')
  }
  catch (e) {
    // e.g. 400 when there are more than 10,000 Orders to export.
    exportError.value = await blobErrorMessage(e, t('orders.export_error'))
  }
  finally {
    exporting.value = false
  }
}

const columns = computed<TableColumn<Order>[]>(() => [
  { accessorKey: 'id', header: t('orders.column.order') },
  { accessorKey: 'created_at', header: t('orders.column.placed') },
  { accessorKey: 'order_status', header: t('orders.column.status') },
  { accessorKey: 'payment_status', header: t('orders.column.payment') },
  { accessorKey: 'grand_total', header: t('orders.column.total') },
])
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-3xl font-bold">
        {{ t('orders.title') }}
      </h1>
      <UButton
        v-if="orders?.data.length"
        :label="t('orders.export')"
        icon="i-lucide-download"
        color="neutral"
        variant="outline"
        class="cursor-pointer"
        :loading="exporting"
        @click="exportOrders"
      />
    </div>

    <UAlert
      v-if="exportError"
      color="error"
      variant="subtle"
      :title="exportError"
    />

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(error, t('orders.load_error'))"
    />

    <template v-else>
      <UTable
        :data="orders?.data ?? []"
        :columns="columns"
        :loading="loadStatus === 'pending'"
        :empty="t('orders.empty')"
      >
        <template #id-cell="{ row }">
          <ULink
            :to="localePath(`/orders/${row.original.id}`)"
            class="font-medium text-primary"
          >
            #{{ row.original.id }}
          </ULink>
        </template>
        <template #created_at-cell="{ row }">
          {{ formatDate(row.original.created_at) }}
        </template>
        <template #order_status-cell="{ row }">
          <UBadge
            :label="orderStatusLabel(row.original.order_status)"
            :color="orderStatusColor(row.original.order_status)"
            variant="subtle"
          />
        </template>
        <template #payment_status-cell="{ row }">
          <UBadge
            :label="paymentStatusLabel(row.original.payment_status)"
            :color="paymentStatusColor(row.original.payment_status)"
            variant="outline"
          />
        </template>
        <template #grand_total-cell="{ row }">
          {{ row.original.grand_total !== null ? formatPrice(row.original.grand_total) : '—' }}
        </template>
      </UTable>

      <UPagination
        v-if="orders && orders.meta.last_page > 1"
        :page="page"
        :total="orders.meta.total"
        :items-per-page="orders.meta.per_page"
        :to="(p: number) => ({ query: { ...route.query, page: p } })"
        class="flex justify-center"
      />
    </template>
  </div>
</template>
