<script setup lang="ts">
import type { OrderDetail } from '~/types/order'

definePageMeta({ middleware: ['auth', 'supplier'] })

const route = useRoute()
const id = route.params.id as string
useSeoMeta({ title: `Order #${id}` })

const { data: order, error } = await useAsyncData(
  `supplier-order-${id}`,
  () => useApi()<{ data: OrderDetail }>(`/orders/${id}`).then(response => response.data),
)

// 403: another Supplier's Order. 404: no such Order.
const unavailable = computed(() => [403, 404].includes(apiStatus(error.value) ?? 0))

const subtotal = computed(() => order.value?.items.reduce((sum, item) => sum + (item.total_amount ?? 0), 0) ?? 0)
</script>

<template>
  <div class="space-y-6">
    <ULink
      to="/supplier/orders"
      class="text-sm text-muted"
    >
      ← All orders
    </ULink>

    <UCard
      v-if="unavailable"
      class="mx-auto max-w-md text-center"
    >
      <div class="space-y-4">
        <h1 class="text-xl font-semibold">
          Order unavailable
        </h1>
        <p class="text-muted">
          This order doesn't exist, or it isn't one of yours.
        </p>
        <UButton
          to="/supplier/orders"
          label="Back to your orders"
        />
      </div>
    </UCard>

    <UAlert
      v-else-if="error"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(error)"
    />

    <template v-else-if="order">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
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
        <SupplierOrderActions :order="order" />
      </div>
      <p class="text-sm text-muted">
        Placed {{ formatDate(order.created_at) }}
      </p>

      <div class="grid gap-6 md:grid-cols-2">
        <UCard>
          <template #header>
            <h2 class="font-semibold">
              Ship to
            </h2>
          </template>
          <address
            v-if="order.address"
            class="space-y-1 text-sm not-italic"
          >
            <p class="font-medium">
              {{ order.address.first_name }} {{ order.address.last_name }}
            </p>
            <p>{{ order.address.street_address }}</p>
            <p>{{ order.address.postal_code }} {{ order.address.city }}</p>
            <p>{{ order.address.country }}</p>
            <p class="pt-2 text-muted">
              Phone: {{ order.address.phone }}
            </p>
          </address>
          <p
            v-else
            class="text-sm text-muted"
          >
            No shipping address on this order.
          </p>
        </UCard>

        <UCard>
          <template #header>
            <h2 class="font-semibold">
              Payment
            </h2>
          </template>
          <dl class="grid grid-cols-2 gap-y-2 text-sm">
            <dt class="text-muted">
              Method
            </dt>
            <dd>{{ paymentMethodLabel(order.payment_method) }}</dd>
            <dt class="text-muted">
              Status
            </dt>
            <dd>
              <UBadge
                :label="paymentStatusLabel(order.payment_status)"
                :color="paymentStatusColor(order.payment_status)"
                variant="outline"
              />
            </dd>
          </dl>
          <p
            v-if="order.notes"
            class="mt-4 text-sm"
          >
            <span class="text-muted">Notes:</span> {{ order.notes }}
          </p>
        </UCard>
      </div>

      <UCard>
        <template #header>
          <h2 class="font-semibold">
            Items
          </h2>
        </template>
        <ul class="divide-y divide-default">
          <li
            v-for="item in order.items"
            :key="item.id"
            class="flex items-start justify-between gap-4 py-3 text-sm"
          >
            <div>
              <p class="font-medium">
                {{ item.product?.name ?? `Product #${item.product_id}` }}
              </p>
              <p
                v-if="Object.keys(item.attribute_labels).length"
                class="text-muted"
              >
                {{ Object.entries(item.attribute_labels).map(([name, option]) => `${name}: ${option}`).join(', ') }}
              </p>
              <p class="text-muted">
                {{ item.quantity }} × {{ item.unit_amount !== null ? formatPrice(item.unit_amount) : '—' }}
              </p>
            </div>
            <p class="font-medium">
              {{ item.total_amount !== null ? formatPrice(item.total_amount) : '—' }}
            </p>
          </li>
        </ul>
        <dl class="mt-4 space-y-1 border-t border-default pt-4 text-sm">
          <div class="flex justify-between">
            <dt class="text-muted">
              Subtotal
            </dt>
            <dd>{{ formatPrice(subtotal) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted">
              Shipping
            </dt>
            <dd>{{ formatPrice(order.shipping_amount ?? 0) }}</dd>
          </div>
          <div class="flex justify-between font-semibold">
            <dt>Total</dt>
            <dd>{{ order.grand_total !== null ? formatPrice(order.grand_total) : '—' }}</dd>
          </div>
        </dl>
      </UCard>
    </template>
  </div>
</template>
