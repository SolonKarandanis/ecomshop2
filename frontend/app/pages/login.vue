<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { LoginPayload } from '~/types/auth'

const localePath = useLocalePath()
const { t } = useI18n()

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: () => t('login.title') })

const route = useRoute()
const auth = useAuthStore()
const { pathForUser } = useLocaleSwitch()

const state = reactive<LoginPayload>({ email: '', password: '', remember: false })
const { error, submit } = useApiForm(() => Object.keys(state))

function validate(data: Partial<LoginPayload>): FormError[] {
  const errors: FormError[] = []
  if (!data.email) errors.push({ name: 'email', message: t('auth.validation.email') })
  if (!data.password) errors.push({ name: 'password', message: t('auth.validation.password') })
  return errors
}

async function onSubmit() {
  await submit(async () => {
    await auth.login({ ...state })
    // Lands in the user's saved language, which may differ from this page's.
    await navigateTo(pathForUser(redirectTarget(route.query.redirect, localePath('/'))))
  })
}
</script>

<template>
  <UCard class="mx-auto max-w-md">
    <template #header>
      <h1 class="text-xl font-semibold">
        {{ t('login.title') }}
      </h1>
    </template>

    <UForm
      ref="form"
      :state="state"
      :validate="validate"
      class="space-y-4"
      @submit="onSubmit"
    >
      <p
        v-if="route.query.reset"
        class="text-sm text-success"
      >
        {{ t('login.reset_done') }}
      </p>

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
        :label="t('auth.field.password')"
        name="password"
      >
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="current-password"
          class="w-full"
        />
      </UFormField>

      <div class="flex items-center justify-between">
        <UCheckbox
          v-model="state.remember"
          :label="t('login.remember')"
        />
        <ULink
          :to="localePath('/forgot-password')"
          class="text-sm"
        >
          {{ t('login.forgot') }}
        </ULink>
      </div>

      <UButton
        type="submit"
        :label="t('login.submit')"
        class="cursor-pointer"
        block
      />
    </UForm>

    <template #footer>
      <p class="text-sm text-muted">
        {{ t('login.no_account') }}
        <ULink
          :to="localePath('/register')"
          class="text-primary"
        >
          {{ t('login.register') }}
        </ULink>
      </p>
    </template>
  </UCard>
</template>
