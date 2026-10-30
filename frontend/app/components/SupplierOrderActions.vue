<script setup lang="ts">
import type { Order } from '~/types/order'

const props = defineProps<{
  order: Pick<Order, 'id' | 'order_status' | 'payment_status' | 'supplier_actions'>
}>()

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
      label="Ship"
      size="sm"
      class="cursor-pointer"
      :loading="pending"
      @click="run(order, 'ship')"
    />
    <UButton
      v-if="actions.includes('deliver')"
      label="Mark delivered"
      size="sm"
      color="success"
      class="cursor-pointer"
      :loading="pending"
      @click="run(order, 'deliver')"
    />
    <UButton
      v-if="actions.includes('cancel')"
      label="Cancel"
      size="sm"
      color="error"
      variant="outline"
      class="cursor-pointer"
      :disabled="pending"
      @click="confirmingCancel = true"
    />

    <UModal
      v-model:open="confirmingCancel"
      :title="`Cancel order #${order.id}?`"
      description="The Buyer will be notified. This can't be undone."
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            label="Keep order"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="confirmingCancel = false"
          />
          <UButton
            label="Cancel order"
            color="error"
            class="cursor-pointer"
            @click="cancel"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
