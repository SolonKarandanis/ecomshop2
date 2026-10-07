<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { ForgotPasswordPayload } from '~/types/auth'

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: 'Forgot password' })

const state = reactive<ForgotPasswordPayload>({ email: '' })
const sent = ref<string | null>(null)
const { error, submit } = useApiForm(() => Object.keys(state))

function validate(data: Partial<ForgotPasswordPayload>): FormError[] {
  return data.email ? [] : [{ name: 'email', message: 'Enter your email.' }]
}

async function onSubmit() {
  sent.value = null
  await submit(async () => {
    const response = await useApi()<{ message: string }>('/forgot-password', { method: 'POST', body: { ...state } })
    sent.value = response.message
  })
}
</script>

<template>
  <UCard class="mx-auto max-w-md">
    <template #header>
      <h1 class="text-xl font-semibold">
        Forgot your password?
      </h1>
      <p class="mt-1 text-sm text-muted">
        We'll email you a link to choose a new one.
      </p>
    </template>

    <UForm
      ref="form"
      :state="state"
      :validate="validate"
      class="space-y-4"
      @submit="onSubmit"
    >
      <UAlert
        v-if="sent"
        color="success"
        variant="subtle"
        :title="sent"
      />

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        :title="error"
      />

      <UFormField
        label="Email"
        name="email"
      >
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="email"
          class="w-full"
        />
      </UFormField>

      <UButton
        type="submit"
        label="Send reset link"
        block
      />
    </UForm>

    <template #footer>
      <ULink
        to="/login"
        class="text-sm text-primary"
      >
        Back to log in
      </ULink>
    </template>
  </UCard>
</template>
