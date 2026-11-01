<script setup lang="ts">
import type { OrderDetail } from '~/types/order'

// An Order's shipping address, payment and items, shared by the Buyer's and the Supplier's order pages.
const props = defineProps<{ order: OrderDetail }>()
const { t } = useI18n()

const subtotal = computed(() => props.order.items.reduce((sum, item) => sum + (item.total_amount ?? 0), 0))
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-6 md:grid-cols-2">
      <UCard>
        <template #header>
          <h2 class="font-semibold">
            {{ t('order_details.ship_to') }}
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
            {{ t('order_details.phone', { phone: order.address.phone }) }}
          </p>
        </address>
        <p
          v-else
          class="text-sm text-muted"
        >
          {{ t('order_details.no_address') }}
        </p>
      </UCard>

      <UCard>
        <template #header>
          <h2 class="font-semibold">
            {{ t('order_details.payment') }}
          </h2>
        </template>
        <dl class="grid grid-cols-2 gap-y-2 text-sm">
          <dt class="text-muted">
            {{ t('order_details.method') }}
          </dt>
          <dd>{{ paymentMethodLabel(order.payment_method) }}</dd>
          <dt class="text-muted">
            {{ t('order_details.status') }}
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
          <span class="text-muted">{{ t('order_details.notes') }}</span> {{ order.notes }}
        </p>
      </UCard>
    </div>

    <UCard>
      <template #header>
        <h2 class="font-semibold">
          {{ t('order_details.items') }}
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
              {{ item.product?.name ?? t('order_details.product_fallback', { id: item.product_id }) }}
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
            {{ t('order_details.subtotal') }}
          </dt>
          <dd>{{ formatPrice(subtotal) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-muted">
            {{ t('order_details.shipping') }}
          </dt>
          <dd>{{ formatPrice(order.shipping_amount ?? 0) }}</dd>
        </div>
        <div class="flex justify-between font-semibold">
          <dt>{{ t('order_details.total') }}</dt>
          <dd>{{ order.grand_total !== null ? formatPrice(order.grand_total) : '—' }}</dd>
        </div>
      </dl>
    </UCard>
  </div>
</template>
