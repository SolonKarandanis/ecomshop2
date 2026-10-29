<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { SavedAddress, SavedAddressPayload } from '~/types/profile'

// Adds a saved address, or edits `address` when it's given.
const props = defineProps<{ address?: SavedAddress | null }>()
const emit = defineEmits<{ saved: [address: SavedAddress], cancel: [] }>()

const state = reactive<SavedAddressPayload>({
  first_name: props.address?.first_name ?? '',
  last_name: props.address?.last_name ?? '',
  phone: props.address?.phone ?? '',
  street_address: props.address?.street_address ?? '',
  city: props.address?.city ?? '',
  country: props.address?.country ?? '',
  postal_code: props.address?.postal_code ?? '',
})
const { error, submit } = useApiForm(() => Object.keys(state))
const submitting = ref(false)

function validate(data: Partial<SavedAddressPayload>): FormError[] {
  return savedAddressFields
    .filter(field => !data[field.name]?.trim())
    .map(field => ({ name: field.name, message: `Enter the ${field.label.toLowerCase()}.` }))
}

async function onSubmit() {
  submitting.value = true
  await submit(async () => {
    const response = props.address
      ? await useApi()<{ data: SavedAddress }>(`/profile/addresses/${props.address.id}`, { method: 'PATCH', body: { ...state } })
      : await useApi()<{ data: SavedAddress }>('/profile/addresses', { method: 'POST', body: { ...state } })
    emit('saved', response.data)
  })
  submitting.value = false
}
</script>

<template>
  <UForm
    ref="form"
    :state="state"
    :validate="validate"
    class="space-y-4"
    @submit="onSubmit"
  >
    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :title="error"
    />

    <div class="grid gap-4 sm:grid-cols-2">
      <UFormField
        v-for="field in savedAddressFields"
        :key="field.name"
        :label="field.label"
        :name="field.name"
        required
        :class="field.name === 'street_address' ? 'sm:col-span-2' : undefined"
      >
        <UInput
          v-model="state[field.name]"
          :autocomplete="field.autocomplete"
          class="w-full"
        />
      </UFormField>
    </div>

    <div class="flex justify-end gap-2">
      <UButton
        label="Cancel"
        color="neutral"
        variant="ghost"
        class="cursor-pointer"
        @click="emit('cancel')"
      />
      <UButton
        type="submit"
        :label="address ? 'Save address' : 'Add address'"
        :loading="submitting"
        class="cursor-pointer"
      />
    </div>
  </UForm>
</template>
