<script setup lang="ts">
import type { CartItem } from '~/types/cart'

useSeoMeta({ title: 'Cart' })

const cart = useCartStore()
const error = ref<string | null>(null)
// The item being updated or removed, so its controls can't fire a second,
// out-of-order request before the first one's cart comes back.
const busyItemId = ref<string | null>(null)

async function run(item: CartItem, action: () => Promise<void>) {
  error.value = null
  busyItemId.value = item.id
  try {
    await action()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    busyItemId.value = null
  }
}

function updateQuantity(item: CartItem, quantity: number | null) {
  if (!quantity || quantity === item.quantity) return
  run(item, () => cart.updateItem(item.id, quantity))
}

function remove(item: CartItem) {
  run(item, () => cart.removeItem(item.id))
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <h1 class="text-3xl font-bold">
      Your cart
    </h1>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :title="error"
    />

    <div
      v-if="!cart.loaded"
      class="py-10 text-center text-muted"
    >
      Loading your cart…
    </div>

    <div
      v-else-if="cart.items.length === 0"
      class="space-y-4 py-10 text-center"
    >
      <p class="text-muted">
        Your cart is empty.
      </p>
      <UButton
        to="/products"
        label="Browse products"
      />
    </div>

    <template v-else>
      <ul class="divide-y divide-default border-y border-default">
        <li
          v-for="item in cart.items"
          :key="item.id"
          class="flex flex-wrap items-center gap-4 py-4"
        >
          <div class="min-w-0 flex-1">
            <NuxtLink
              v-if="item.product_slug"
              :to="`/products/${item.product_slug}`"
              class="font-medium hover:text-primary"
            >
              {{ item.product_name }}
            </NuxtLink>
            <span
              v-else
              class="font-medium"
            >{{ item.product_name ?? 'Unavailable product' }}</span>
            <p class="text-sm text-muted">
              {{ formatPrice(item.unit_price) }} each
            </p>
          </div>

          <UInputNumber
            :model-value="item.quantity"
            :min="1"
            :disabled="busyItemId === item.id"
            aria-label="Quantity"
            class="w-28"
            @update:model-value="updateQuantity(item, $event)"
          />

          <span class="w-24 text-right font-semibold">{{ formatPrice(item.total_price) }}</span>

          <UButton
            icon="i-lucide-trash-2"
            color="neutral"
            variant="ghost"
            :aria-label="`Remove ${item.product_name ?? 'item'}`"
            :disabled="busyItemId === item.id"
            @click="remove(item)"
          />
        </li>
      </ul>

      <div class="flex items-center justify-between">
        <span class="text-lg">Subtotal</span>
        <span class="text-2xl font-semibold">{{ formatPrice(cart.subtotal) }}</span>
      </div>

      <div class="flex justify-end">
        <UButton
          to="/checkout"
          label="Checkout"
          size="lg"
        />
      </div>
    </template>
  </div>
</template>
