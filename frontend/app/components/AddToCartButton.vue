<script setup lang="ts">
const props = withDefaults(defineProps<{
  productId: number
  // Show a quantity input next to the button (the product page); otherwise adds 1.
  withQuantity?: boolean
  inStock?: boolean
}>(), { withQuantity: false, inStock: true })

const { t } = useI18n()
const auth = useAuthStore()
const cart = useCartStore()
const toast = useToast()

const quantity = ref(1)
const adding = ref(false)

// POST /cart/items needs the `buyer-action` gate, so a logged-in Supplier or
// admin would get a 403: offer the button to guests and Buyers only.
const visible = computed(() => auth.resolved && (!auth.user || auth.isBuyer))

async function add() {
  adding.value = true
  try {
    await cart.addItem(props.productId, quantity.value)
    toast.add({ title: t('cart.add.added'), color: 'success' })
  }
  catch (error) {
    // e.g. the 400 for a product from a second Supplier.
    toast.add({ title: apiErrorMessage(error, t('cart.add.error')), color: 'error' })
  }
  finally {
    adding.value = false
  }
}
</script>

<template>
  <!-- Client-only: who may buy depends on the session, which the server never sees (ADR-0002). -->
  <ClientOnly>
    <div
      v-if="visible"
      class="flex items-center gap-2"
    >
      <UInputNumber
        v-if="withQuantity"
        v-model="quantity"
        :min="1"
        :aria-label="t('common.quantity')"
        class="w-28"
      />
      <UButton class="cursor-pointer"
        :label="t('cart.add.button')"
        icon="i-lucide-shopping-cart"
        :loading="adding"
        :disabled="!inStock"
        :block="!withQuantity"
        @click="add"
      />
    </div>
  </ClientOnly>
</template>
