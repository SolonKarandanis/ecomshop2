<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Paginated } from '~/types/catalogue'
import type { Order } from '~/types/order'
import { ORDER_CANCELLED, ORDER_DELIVERED, ORDER_PAID, ORDER_SHIPPED } from '~/types/order'

definePageMeta({ middleware: ['auth', 'supplier'] })
useSeoMeta({ title: 'Supplier orders' })

const route = useRoute()

// Reka's select can't hold an empty value, so "all" stands for no filter.
const ALL = 'all'
// Draft Orders never reach a Supplier, so they aren't offered.
const statusItems = [
  { label: 'All statuses', value: ALL },
  ...[ORDER_PAID, ORDER_SHIPPED, ORDER_DELIVERED, ORDER_CANCELLED].map(value => ({ label: orderStatusLabel(value), value })),
]

// The URL holds the filter and page, so a reload keeps them.
const status = computed(() => typeof route.query.status === 'string' ? route.query.status : ALL)
const page = computed(() => Number(route.query.page) || 1)

const { data: orders, error, status: loadStatus } = await useAsyncData(
  'supplier-orders',
  () => useApi()<Paginated<Order>>('/supplier-orders', {
    query: {
      page: page.value,
      perPage: 20,
      ...(status.value !== ALL ? { orderStatus: status.value } : {}),
    },
  }),
  { watch: [status, page] },
)

const forbidden = computed(() => apiStatus(error.value) === 403)

function filterBy(value: string) {
  return navigateTo({ query: value === ALL ? {} : { status: value } })
}

const columns: TableColumn<Order>[] = [
  { accessorKey: 'id', header: 'Order' },
  { accessorKey: 'created_at', header: 'Placed' },
  { accessorKey: 'order_status', header: 'Status' },
  { accessorKey: 'payment_status', header: 'Payment' },
  { accessorKey: 'grand_total', header: 'Total' },
  { id: 'actions', header: '' },
]
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-3xl font-bold">
        Supplier orders
      </h1>
      <USelect
        :model-value="status"
        :items="statusItems"
        aria-label="Filter by status"
        class="w-48"
        @update:model-value="filterBy"
      />
    </div>

    <UAlert
      v-if="forbidden"
      color="warning"
      variant="subtle"
      title="Your Supplier account can't manage orders right now."
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
        :data="orders?.data ?? []"
        :columns="columns"
        :loading="loadStatus === 'pending'"
        empty="No orders to show."
      >
        <template #id-cell="{ row }">
          <ULink
            :to="`/supplier/orders/${row.original.id}`"
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
        <template #actions-cell="{ row }">
          <SupplierOrderActions :order="row.original" />
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
