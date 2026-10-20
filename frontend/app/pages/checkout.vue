<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { CheckoutPayload, CheckoutResponse, PaymentMethodOption } from '~/types/order'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'Checkout' })

const cart = useCartStore()

// Public lookup list: no session needed, so no credentials (like the catalogue reads).
const { data: paymentMethods, error: paymentMethodsError } = await useAsyncData(
  'payment-methods',
  () => useCatalogApi()<{ data: PaymentMethodOption[] }>('/payment-methods'),
  { transform: response => response.data },
)

const state = reactive<CheckoutPayload>({
  firstName: '',
  lastName: '',
  phone: '',
  address: '',
  city: '',
  country: '',
  zipCode: '',
  paymentMethod: '',
})
const { error, submit } = useApiForm(() => Object.keys(state))
const submitting = ref(false)

// Preselect the first method once the list arrives.
watch(paymentMethods, (methods) => {
  if (!state.paymentMethod && methods?.length) {
    state.paymentMethod = methods[0]!.key
  }
}, { immediate: true })

const paymentMethodItems = computed(() =>
  (paymentMethods.value ?? []).map(method => ({ label: method.label, value: method.key })),
)

const addressFields: { name: Exclude<keyof CheckoutPayload, 'paymentMethod'>, label: string, autocomplete: string }[] = [
  { name: 'firstName', label: 'First name', autocomplete: 'given-name' },
  { name: 'lastName', label: 'Last name', autocomplete: 'family-name' },
  { name: 'phone', label: 'Phone', autocomplete: 'tel' },
  { name: 'address', label: 'Street address', autocomplete: 'street-address' },
  { name: 'city', label: 'City', autocomplete: 'address-level2' },
  { name: 'zipCode', label: 'Postal code', autocomplete: 'postal-code' },
  { name: 'country', label: 'Country', autocomplete: 'country-name' },
]

function validate(data: Partial<CheckoutPayload>): FormError[] {
  const errors: FormError[] = addressFields
    .filter(field => !data[field.name]?.trim())
    .map(field => ({ name: field.name, message: `Enter your ${field.label.toLowerCase()}.` }))
  if (!data.paymentMethod) {
    errors.push({ name: 'paymentMethod', message: 'Choose a payment method.' })
  }
  return errors
}

async function onSubmit() {
  submitting.value = true
  await submit(async () => {
    const response = await useApi()<CheckoutResponse>('/checkout', { method: 'POST', body: { ...state } })
    // Stripe's hosted payment page, or our own /success?order_id=… for Cash on Delivery.
    await navigateTo(response.redirect_url, { external: true })
  })
  submitting.value = false
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <h1 class="text-3xl font-bold">
      Checkout
    </h1>

    <div
      v-if="!cart.loaded"
      aria-busy="true"
      class="space-y-4"
    >
      <span class="sr-only">Loading your cart…</span>
      <USkeleton class="h-64 w-full" />
    </div>

    <div
      v-else-if="cart.items.length === 0"
      class="space-y-4 py-10 text-center"
    >
      <p class="text-muted">
        Your cart is empty, so there's nothing to check out.
      </p>
      <UButton
        to="/products"
        label="Browse products"
      />
    </div>

    <div
      v-else
      class="grid gap-8 lg:grid-cols-[1fr_20rem]"
    >
      <UForm
        ref="form"
        :state="state"
        :validate="validate"
        class="space-y-6"
        @submit="onSubmit"
      >
        <UAlert
          v-if="error"
          color="error"
          variant="subtle"
          :title="error"
        />

        <section class="space-y-4">
          <h2 class="text-lg font-semibold">
            Shipping address
          </h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField
              v-for="field in addressFields"
              :key="field.name"
              :label="field.label"
              :name="field.name"
              required
              :class="field.name === 'address' ? 'sm:col-span-2' : undefined"
            >
              <UInput
                v-model="state[field.name]"
                :autocomplete="field.autocomplete"
                class="w-full"
              />
            </UFormField>
          </div>
        </section>

        <section class="space-y-4">
          <h2 class="text-lg font-semibold">
            Payment
          </h2>
          <UAlert
            v-if="paymentMethodsError"
            color="error"
            variant="subtle"
            title="We couldn't load the payment methods. Reload the page to try again."
          />
          <UFormField
            v-else
            name="paymentMethod"
          >
            <URadioGroup
              v-model="state.paymentMethod"
              :items="paymentMethodItems"
            />
          </UFormField>
        </section>

        <UButton
          type="submit"
          label="Place order"
          size="lg"
          :loading="submitting"
          :disabled="!paymentMethodItems.length"
        />
      </UForm>

      <UCard
        as="aside"
        class="h-fit"
      >
        <template #header>
          <h2 class="font-semibold">
            Order summary
          </h2>
        </template>
        <ul class="space-y-2 text-sm">
          <li
            v-for="item in cart.items"
            :key="item.id"
            class="flex justify-between gap-4"
          >
            <span class="min-w-0">{{ item.product_name ?? 'Unavailable product' }} × {{ item.quantity }}</span>
            <span class="shrink-0">{{ formatPrice(item.total_price) }}</span>
          </li>
        </ul>
        <template #footer>
          <div class="flex justify-between font-semibold">
            <span>Total</span>
            <span>{{ formatPrice(cart.subtotal) }}</span>
          </div>
        </template>
      </UCard>
    </div>
  </div>
</template>
