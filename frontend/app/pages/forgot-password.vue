<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { ForgotPasswordPayload } from '~/types/auth'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: () => t('forgot.title') })

const state = reactive<ForgotPasswordPayload>({ email: '' })
const sent = ref<string | null>(null)
const { error, submit } = useApiForm(() => Object.keys(state))

function validate(data: Partial<ForgotPasswordPayload>): FormError[] {
  return data.email ? [] : [{ name: 'email', message: t('auth.validation.email') }]
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
        {{ t('forgot.heading') }}
      </h1>
      <p class="mt-1 text-sm text-muted">
        {{ t('forgot.intro') }}
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

      <UButton
        type="submit"
        :label="t('forgot.submit')"
        block
      />
    </UForm>

    <template #footer>
      <ULink
        :to="localePath('/login')"
        class="text-sm text-primary"
      >
        {{ t('forgot.back') }}
      </ULink>
    </template>
  </UCard>
</template>
