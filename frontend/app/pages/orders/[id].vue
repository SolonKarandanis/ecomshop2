<script setup lang="ts">
import type { TimelineItem } from '@nuxt/ui'
import type { OrderDetail } from '~/types/order'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const id = route.params.id as string
useSeoMeta({ title: () => t('order.title', { id }) })

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
  description: t('order.history_payment', { status: paymentStatusLabel(entry.payment_status) }),
  icon: 'i-lucide-circle-dot',
})))
</script>

<template>
  <div class="space-y-6">
    <ULink
      :to="localePath('/orders')"
      class="text-sm text-muted"
    >
      {{ t('order.back') }}
    </ULink>

    <OrderNotFound v-if="notFound" />

    <UCard
      v-else-if="forbidden"
      class="mx-auto max-w-md text-center"
    >
      <div class="space-y-4">
        <h1 class="text-xl font-semibold">
          {{ t('order.forbidden.title') }}
        </h1>
        <p class="text-muted">
          {{ t('order.forbidden.text') }}
        </p>
        <UButton
          :to="localePath('/orders')"
          :label="t('nav.my_orders')"
        />
      </div>
    </UCard>

    <UAlert
      v-else-if="error"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(error, t('order.load_error'))"
    />

    <template v-else-if="order">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold">
          {{ t('order.title', { id: order.id }) }}
        </h1>
        <UBadge
          :label="orderStatusLabel(order.order_status)"
          :color="orderStatusColor(order.order_status)"
          variant="subtle"
          size="lg"
        />
      </div>
      <p class="text-sm text-muted">
        {{ t('order.placed', { date: formatDate(order.created_at) }) }}
      </p>

      <OrderDetails :order="order" />

      <UCard>
        <template #header>
          <h2 class="font-semibold">
            {{ t('order.history') }}
          </h2>
        </template>
        <UTimeline
          v-if="history.length"
          :items="history"
          :aria-label="t('order.history')"
        />
        <p
          v-else
          class="text-sm text-muted"
        >
          {{ t('order.history_empty') }}
        </p>
      </UCard>
    </template>
  </div>
</template>
