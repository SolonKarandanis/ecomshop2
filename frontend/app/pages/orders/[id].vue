<script setup lang="ts">
import type { TimelineItem } from '@nuxt/ui'
import type { OrderDetail } from '~/types/order'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const id = route.params.id as string
useSeoMeta({ title: `Order #${id}` })

const { data: order, error } = await useAsyncData(
  `order-${id}`,
  () => useApi()<{ data: OrderDetail }>(`/orders/${id}`).then(response => response.data),
)

// 403: another Buyer's Order. 404: no such Order.
const forbidden = computed(() => apiStatus(error.value) === 403)
const notFound = computed(() => apiStatus(error.value) === 404)

// The API sends the history oldest first.
const history = computed<TimelineItem[]>(() => (order.value?.status_history ?? []).map(entry => ({
  date: formatDateTime(entry.created_at),
  title: orderStatusLabel(entry.order_status),
  description: `Payment: ${paymentStatusLabel(entry.payment_status)}`,
  icon: 'i-lucide-circle-dot',
})))
</script>

<template>
  <div class="space-y-6">
    <ULink
      to="/orders"
      class="text-sm text-muted"
    >
      ← My orders
    </ULink>

    <OrderNotFound v-if="notFound" />

    <UCard
      v-else-if="forbidden"
      class="mx-auto max-w-md text-center"
    >
      <div class="space-y-4">
        <h1 class="text-xl font-semibold">
          This isn't your order
        </h1>
        <p class="text-muted">
          You can only view orders placed from your own account.
        </p>
        <UButton
          to="/orders"
          label="My orders"
        />
      </div>
    </UCard>

    <UAlert
      v-else-if="error"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(error, 'We couldn\'t load this order. Reload the page to try again.')"
    />

    <template v-else-if="order">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold">
          Order #{{ order.id }}
        </h1>
        <UBadge
          :label="orderStatusLabel(order.order_status)"
          :color="orderStatusColor(order.order_status)"
          variant="subtle"
          size="lg"
        />
      </div>
      <p class="text-sm text-muted">
        Placed {{ formatDate(order.created_at) }}
      </p>

      <OrderDetails :order="order" />

      <UCard>
        <template #header>
          <h2 class="font-semibold">
            Status history
          </h2>
        </template>
        <UTimeline
          v-if="history.length"
          :items="history"
          aria-label="Status history"
        />
        <p
          v-else
          class="text-sm text-muted"
        >
          No status changes recorded yet.
        </p>
      </UCard>
    </template>
  </div>
</template>
