<script setup lang="ts">
import type { Order } from '~/types/order'

const props = defineProps<{
  order: Pick<Order, 'id' | 'order_status' | 'payment_status' | 'supplier_actions'>
}>()

const { t } = useI18n()
const { pending, run } = useSupplierOrderActions()
const confirmingCancel = ref(false)

// The backend decides what's allowed (#82); this only shows what it sends.
const actions = computed(() => props.order.supplier_actions ?? [])

async function cancel() {
  confirmingCancel.value = false
  await run(props.order, 'cancel')
}
</script>

<template>
  <div
    v-if="actions.length"
    class="flex flex-wrap gap-2"
  >
    <UButton
      v-if="actions.includes('ship')"
      :label="t('supplier.actions.ship')"
      size="sm"
      class="cursor-pointer"
      :loading="pending"
      @click="run(order, 'ship')"
    />
    <UButton
      v-if="actions.includes('deliver')"
      :label="t('supplier.actions.deliver')"
      size="sm"
      color="success"
      class="cursor-pointer"
      :loading="pending"
      @click="run(order, 'deliver')"
    />
    <UButton
      v-if="actions.includes('cancel')"
      :label="t('supplier.actions.cancel')"
      size="sm"
      color="error"
      variant="outline"
      class="cursor-pointer"
      :disabled="pending"
      @click="confirmingCancel = true"
    />

    <UModal
      v-model:open="confirmingCancel"
      :title="t('supplier.actions.cancel_title', { id: order.id })"
      :description="t('supplier.actions.cancel_description')"
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            :label="t('supplier.actions.keep')"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="confirmingCancel = false"
          />
          <UButton
            :label="t('supplier.actions.confirm_cancel')"
            color="error"
            class="cursor-pointer"
            @click="cancel"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
