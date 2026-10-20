<script setup lang="ts">
import type { StripeCancelResult } from '~/types/order'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'Payment not completed' })

const route = useRoute()
const orderId = orderIdFromQuery(route.query)

// Doesn't change the Order: it stays pending, and its items stay in the Order,
// not the cart, so the cart is left alone (ADR-0004).
const { data: result, error } = await useAsyncData('checkout-cancel', () =>
  orderId === null
    ? Promise.resolve(null)
    : useApi()<StripeCancelResult>('/cancel', { query: { order_id: orderId } }),
)

const notFound = computed(() => !result.value && (!error.value || [403, 404, 422].includes(apiStatus(error.value) ?? 0)))

const retrying = ref(false)

async function retry(url: string) {
  retrying.value = true
  // Back to the same Stripe Checkout Session: no new Order is created.
  await navigateTo(url, { external: true })
}
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
        Payment not completed
      </h1>

      <template v-if="result.retry_url">
        <p class="text-muted">
          Order #{{ result.order_id }} is waiting for payment. You can go back to Stripe and pay for it now.
        </p>
        <div class="flex justify-center gap-2">
          <UButton
            label="Try payment again"
            :loading="retrying"
            @click="retry(result.retry_url)"
          />
          <UButton
            :to="`/orders/${result.order_id}`"
            label="View order"
            color="neutral"
            variant="outline"
          />
        </div>
      </template>

      <template v-else>
        <p class="text-muted">
          The payment window for order #{{ result.order_id }} has closed, so it can't be paid any more.
        </p>
        <div class="flex justify-center gap-2">
          <UButton
            :to="`/orders/${result.order_id}`"
            label="View order"
          />
          <UButton
            to="/products"
            label="Keep shopping"
            color="neutral"
            variant="outline"
          />
        </div>
      </template>
    </div>
  </UCard>
</template>
