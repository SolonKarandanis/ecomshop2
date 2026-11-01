<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { ResetPasswordPayload } from '~/types/auth'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: () => t('reset.title') })

// The backend's reset email links here with `?token=...&email=...`.
const route = useRoute()
const token = String(route.query.token ?? '')

const state = reactive({
  email: String(route.query.email ?? ''),
  password: '',
  password_confirmation: '',
})
const { error, submit } = useApiForm(() => Object.keys(state))

function validate(data: Partial<typeof state>): FormError[] {
  const errors: FormError[] = []
  if (!data.email) errors.push({ name: 'email', message: t('auth.validation.email') })
  if (!data.password) errors.push({ name: 'password', message: t('reset.validation.password') })
  if (data.password_confirmation !== data.password) {
    errors.push({ name: 'password_confirmation', message: t('auth.validation.passwords_match') })
  }
  return errors
}

async function onSubmit() {
  await submit(async () => {
    const payload: ResetPasswordPayload = { token, ...state }
    await useApi()('/reset-password', { method: 'POST', body: payload })
    // Resetting doesn't start a session, so send the user to log in.
    await navigateTo(localePath({ path: '/login', query: { reset: '1' } }))
  })
}
</script>

<template>
  <UCard class="mx-auto max-w-md">
    <template #header>
      <h1 class="text-xl font-semibold">
        {{ t('reset.heading') }}
      </h1>
    </template>

    <UAlert
      v-if="!token"
      color="error"
      variant="subtle"
      :title="t('reset.incomplete')"
    >
      <template #actions>
        <UButton
          :to="localePath('/forgot-password')"
          :label="t('reset.request_new')"
          size="xs"
        />
      </template>
    </UAlert>

    <UForm
      v-else
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

      <UFormField
        :label="t('auth.field.email')"
        name="email"
      >
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="email"
          class="w-full"
        />
      </UFormField>

      <UFormField
        :label="t('reset.new_password')"
        name="password"
      >
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UFormField
        :label="t('reset.confirm')"
        name="password_confirmation"
      >
        <UInput
          v-model="state.password_confirmation"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UButton
        type="submit"
        :label="t('reset.submit')"
        block
      />
    </UForm>
  </UCard>
</template>
