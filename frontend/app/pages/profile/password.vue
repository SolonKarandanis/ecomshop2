<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { ChangePasswordPayload } from '~/types/profile'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'Change password' })

const toast = useToast()

const state = reactive<ChangePasswordPayload>({ currentPassword: '', newPassword: '', newPasswordConfirmation: '' })
const { error, submit } = useApiForm(() => Object.keys(state))
const submitting = ref(false)

// ChangePasswordRequest's own rules, checked here first; the API still decides.
function validate(data: Partial<ChangePasswordPayload>): FormError[] {
  const errors: FormError[] = []
  if (!data.currentPassword) errors.push({ name: 'currentPassword', message: 'Enter your current password.' })
  if ((data.newPassword ?? '').length < 8) errors.push({ name: 'newPassword', message: 'Use at least 8 characters.' })
  if (data.newPasswordConfirmation !== data.newPassword) {
    errors.push({ name: 'newPasswordConfirmation', message: 'The passwords don\'t match.' })
  }
  return errors
}

async function onSubmit() {
  submitting.value = true
  await submit(async () => {
    // 204 with no body.
    await useApi()('/profile/password', { method: 'PATCH', body: { ...state } })
    state.currentPassword = ''
    state.newPassword = ''
    state.newPasswordConfirmation = ''
    toast.add({ title: 'Your password was changed.', color: 'success' })
  })
  submitting.value = false
}
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <h1 class="text-3xl font-bold">
      Your account
    </h1>
    <ProfileNav />

    <UForm
      ref="form"
      :state="state"
      :validate="validate"
      class="space-y-4"
      @submit="onSubmit"
    >
      <p class="text-sm text-muted">
        You'll stay logged in here. Anywhere else you're logged in will be logged out.
      </p>

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        :title="error"
      />

      <UFormField
        label="Current password"
        name="currentPassword"
      >
        <UInput
          v-model="state.currentPassword"
          type="password"
          autocomplete="current-password"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="New password"
        name="newPassword"
        help="At least 8 characters."
      >
        <UInput
          v-model="state.newPassword"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Confirm new password"
        name="newPasswordConfirmation"
      >
        <UInput
          v-model="state.newPasswordConfirmation"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <div class="flex justify-end">
        <UButton
          type="submit"
          label="Change password"
          :loading="submitting"
          class="cursor-pointer"
        />
      </div>
    </UForm>
  </div>
</template>
