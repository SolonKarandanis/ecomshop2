<script setup lang="ts">
import type { Order, OrderPaymentResult } from '~/types/order'
import { PAYMENT_FAILED, PAYMENT_PAID } from '~/types/order'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: () => t('success.title') })

const route = useRoute()
const cart = useCartStore()
const arrival = successArrival(route.query)

type Result
  = | { kind: 'stripe', payment: OrderPaymentResult }
    | { kind: 'cod', order: Order }

const { data: result, error } = await useAsyncData<Result | null>('checkout-success', async () => {
  if (arrival.kind === 'stripe') {
    // Finalizes the Order's payment status if the webhook hasn't already (ADR-0004).
    const payment = await useApi()<OrderPaymentResult>('/success', { query: { session_id: arrival.sessionId } })
    return { kind: 'stripe', payment }
  }
  if (arrival.kind === 'cod') {
    // Nothing to finalize for Cash on Delivery: just show the Order.
    const response = await useApi()<{ data: Order }>(`/orders/${arrival.orderId}`)
    return { kind: 'cod', order: response.data }
  }
  return null
})

// 403/404 (someone else's or no such Order) and 422 (malformed session id) all mean "no Order to show".
const notFound = computed(() => !result.value && (!error.value || [403, 404, 422].includes(apiStatus(error.value) ?? 0)))

// Checkout already emptied the cart on the server; bring the header badge in line.
onMounted(() => {
  cart.fetchCart().catch(() => null)
})

const heading = computed(() => {
  if (result.value?.kind !== 'stripe') return t('success.heading.thanks')
  switch (result.value.payment.payment_status) {
    case PAYMENT_PAID: return t('success.heading.paid')
    case PAYMENT_FAILED: return t('success.heading.failed')
    default: return t('success.heading.confirming')
  }
})

const orderId = computed(() => result.value?.kind === 'stripe' ? result.value.payment.order_id : result.value?.order.id)
</script>

<template>
  <OrderNotFound v-if="notFound" />

  <UAlert
    v-else-if="error"
    class="mx-auto max-w-md"
    color="error"
    variant="subtle"
    :title="apiErrorMessage(error)"
  />

  <UCard
    v-else-if="result"
    class="mx-auto max-w-md text-center"
  >
    <div class="space-y-4">
      <h1 class="text-xl font-semibold">
        {{ heading }}
      </h1>

      <p
        v-if="result.kind === 'cod'"
        class="text-muted"
      >
        {{ result.order.grand_total !== null
          ? t('success.cod_with_total', { id: result.order.id, total: formatPrice(result.order.grand_total) })
          : t('success.cod', { id: result.order.id }) }}
      </p>
      <p
        v-else-if="result.payment.payment_status === PAYMENT_PAID"
        class="text-muted"
      >
        {{ t('success.paid', { id: result.payment.order_id }) }}
      </p>
      <p
        v-else-if="result.payment.payment_status === PAYMENT_FAILED"
        class="text-muted"
      >
        {{ t('success.failed', { id: result.payment.order_id }) }}
      </p>
      <p
        v-else
        class="text-muted"
      >
        {{ t('success.confirming', { id: result.payment.order_id }) }}
      </p>

      <div class="flex justify-center gap-2">
        <UButton
          :to="localePath(`/orders/${orderId}`)"
          :label="t('orders.view')"
        />
        <UButton
          :to="localePath('/products')"
          :label="t('common.keep_shopping')"
          color="neutral"
          variant="outline"
        />
      </div>
    </div>
  </UCard>
</template>
