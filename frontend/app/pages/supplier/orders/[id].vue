<script setup lang="ts">
import type { OrderDetail } from '~/types/order'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: ['auth', 'supplier'] })

const route = useRoute()
const id = route.params.id as string
useSeoMeta({ title: () => t('order.title', { id }) })

const { data: order, error } = await useAsyncData(
  `supplier-order-${id}`,
  () => useApi()<{ data: OrderDetail }>(`/orders/${id}`).then(response => response.data),
)

// 403: another Supplier's Order. 404: no such Order.
const unavailable = computed(() => [403, 404].includes(apiStatus(error.value) ?? 0))

</script>

<template>
  <div class="space-y-6">
    <ULink
      :to="localePath('/supplier/orders')"
      class="text-sm text-muted"
    >
      {{ t('supplier.order.back') }}
    </ULink>

    <UCard
      v-if="unavailable"
      class="mx-auto max-w-md text-center"
    >
      <div class="space-y-4">
        <h1 class="text-xl font-semibold">
          {{ t('supplier.order.unavailable') }}
        </h1>
        <p class="text-muted">
          {{ t('supplier.order.unavailable_text') }}
        </p>
        <UButton
          :to="localePath('/supplier/orders')"
          :label="t('supplier.order.back_to_orders')"
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
            {{ t('order.title', { id: order.id }) }}
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
        {{ t('order.placed', { date: formatDate(order.created_at) }) }}
      </p>

      <OrderDetails :order="order" />
    </template>
  </div>
</template>
