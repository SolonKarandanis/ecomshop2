<script setup lang="ts">
const props = withDefaults(defineProps<{
  productId: number
  // Show a quantity input next to the button (the product page); otherwise adds 1.
  withQuantity?: boolean
  inStock?: boolean
}>(), { withQuantity: false, inStock: true })

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
    toast.add({ title: 'Added to cart', color: 'success' })
  }
  catch (error) {
    // e.g. the 400 for a product from a second Supplier.
    toast.add({ title: apiErrorMessage(error, 'Could not add this product to your cart.'), color: 'error' })
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
        aria-label="Quantity"
        class="w-28"
      />
      <UButton
        label="Add to cart"
        icon="i-lucide-shopping-cart"
        :loading="adding"
        :disabled="!inStock"
        :block="!withQuantity"
        @click="add"
      />
    </div>
  </ClientOnly>
</template>
