<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { CheckoutPayload, CheckoutResponse, PaymentMethodOption } from '~/types/order'
import type { SavedAddress } from '~/types/profile'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: () => t('checkout.title') })

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
  saveAddress: false,
})
const { error, submit } = useApiForm(() => Object.keys(state))
const submitting = ref(false)

// Preselect the first method once the list arrives.
watch(paymentMethods, (methods) => {
  if (!state.paymentMethod && methods?.length) {
    state.paymentMethod = methods[0]!.key
  }
}, { immediate: true })

// The address book is a convenience: if it can't load, the Buyer just types the address.
const { data: savedAddresses } = await useAsyncData(
  'checkout-saved-addresses',
  () => useApi()<{ data: SavedAddress[] }>('/profile/addresses').then(response => response.data),
)
const selectedAddressId = ref<number | undefined>(undefined)

const savedAddressItems = computed(() =>
  (savedAddresses.value ?? []).map(address => ({
    label: address.is_default ? t('checkout.default_address', { address: formatSavedAddress(address) }) : formatSavedAddress(address),
    value: address.id,
  })),
)

// Fills the shipping fields from a saved address; they stay editable.
function pickAddress(id: number | undefined) {
  selectedAddressId.value = id
  const address = savedAddresses.value?.find(candidate => candidate.id === id)
  if (address) {
    Object.assign(state, savedAddressToCheckout(address))
  }
}

// The page renders only once the address book has loaded, so the form is still empty here.
const defaultAddress = savedAddresses.value?.find(address => address.is_default)
if (defaultAddress) {
  pickAddress(defaultAddress.id)
}

const paymentMethodItems = computed(() =>
  (paymentMethods.value ?? []).map(method => ({ label: method.label, value: method.key })),
)

// The same labels as the address book's fields, under CheckoutRequest's field names.
const addressFields: { name: Exclude<keyof CheckoutPayload, 'paymentMethod' | 'saveAddress'>, labelKey: string, autocomplete: string }[] = [
  { name: 'firstName', labelKey: 'address.field.first_name', autocomplete: 'given-name' },
  { name: 'lastName', labelKey: 'address.field.last_name', autocomplete: 'family-name' },
  { name: 'phone', labelKey: 'address.field.phone', autocomplete: 'tel' },
  { name: 'address', labelKey: 'address.field.street_address', autocomplete: 'street-address' },
  { name: 'city', labelKey: 'address.field.city', autocomplete: 'address-level2' },
  { name: 'zipCode', labelKey: 'address.field.postal_code', autocomplete: 'postal-code' },
  { name: 'country', labelKey: 'address.field.country', autocomplete: 'country-name' },
]

function validate(data: Partial<CheckoutPayload>): FormError[] {
  const errors: FormError[] = addressFields
    .filter(field => !data[field.name]?.trim())
    .map(field => ({ name: field.name, message: t('common.validation.required', { field: t(field.labelKey) }) }))
  if (!data.paymentMethod) {
    errors.push({ name: 'paymentMethod', message: t('checkout.choose_payment') })
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
      {{ t('checkout.title') }}
    </h1>

    <div
      v-if="!cart.loaded"
      aria-busy="true"
      class="space-y-4"
    >
      <span class="sr-only">{{ t('cart.loading') }}</span>
      <USkeleton class="h-64 w-full" />
    </div>

    <div
      v-else-if="cart.items.length === 0"
      class="space-y-4 py-10 text-center"
    >
      <p class="text-muted">
        {{ t('checkout.empty') }}
      </p>
      <UButton
        :to="localePath('/products')"
        :label="t('cart.browse')"
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
            {{ t('checkout.shipping_address') }}
          </h2>
          <UFormField
            v-if="savedAddressItems.length"
            :label="t('checkout.saved_addresses')"
          >
            <USelect
              :model-value="selectedAddressId"
              :items="savedAddressItems"
              :placeholder="t('checkout.choose_saved_address')"
              class="w-full"
              @update:model-value="pickAddress"
            />
          </UFormField>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField
              v-for="field in addressFields"
              :key="field.name"
              :label="t(field.labelKey)"
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
          <UCheckbox
            v-model="state.saveAddress"
            :label="t('checkout.save_address')"
          />
        </section>

        <section class="space-y-4">
          <h2 class="text-lg font-semibold">
            {{ t('checkout.payment') }}
          </h2>
          <UAlert
            v-if="paymentMethodsError"
            color="error"
            variant="subtle"
            :title="t('checkout.payment_methods_error')"
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
          :label="t('checkout.place_order')"
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
            {{ t('checkout.summary') }}
          </h2>
        </template>
        <ul class="space-y-2 text-sm">
          <li
            v-for="item in cart.items"
            :key="item.id"
            class="flex justify-between gap-4"
          >
            <span class="min-w-0">{{ item.product_name ?? t('cart.unavailable') }} × {{ item.quantity }}</span>
            <span class="shrink-0">{{ formatPrice(item.total_price) }}</span>
          </li>
        </ul>
        <template #footer>
          <div class="flex justify-between font-semibold">
            <span>{{ t('checkout.total') }}</span>
            <span>{{ formatPrice(cart.subtotal) }}</span>
          </div>
        </template>
      </UCard>
    </div>
  </div>
</template>
