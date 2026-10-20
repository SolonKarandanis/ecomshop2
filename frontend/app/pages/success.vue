<script setup lang="ts">
import type { Order, OrderPaymentResult } from '~/types/order'
import { PAYMENT_FAILED, PAYMENT_PAID } from '~/types/order'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'Order placed' })

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
  if (result.value?.kind !== 'stripe') return 'Thank you for your order'
  switch (result.value.payment.payment_status) {
    case PAYMENT_PAID: return 'Payment received'
    case PAYMENT_FAILED: return 'Payment failed'
    default: return 'Confirming your payment'
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
        Order #{{ result.order.id }} is placed. You'll pay
        <template v-if="result.order.grand_total !== null">
          {{ formatPrice(result.order.grand_total) }}
        </template>
        on delivery.
      </p>
      <p
        v-else-if="result.payment.payment_status === PAYMENT_PAID"
        class="text-muted"
      >
        Order #{{ result.payment.order_id }} is paid. We'll let you know when it ships.
      </p>
      <p
        v-else-if="result.payment.payment_status === PAYMENT_FAILED"
        class="text-muted"
      >
        Stripe couldn't take the payment for order #{{ result.payment.order_id }}, so it hasn't been placed.
      </p>
      <p
        v-else
        class="text-muted"
      >
        Stripe is still confirming the payment for order #{{ result.payment.order_id }}. We'll notify you as soon as it's through.
      </p>

      <div class="flex justify-center gap-2">
        <UButton
          :to="`/orders/${orderId}`"
          label="View order"
        />
        <UButton
          to="/products"
          label="Keep shopping"
          color="neutral"
          variant="outline"
        />
      </div>
    </div>
  </UCard>
</template>
