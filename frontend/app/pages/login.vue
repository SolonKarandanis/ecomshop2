<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { LoginPayload } from '~/types/auth'

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: 'Log in' })

const route = useRoute()
const auth = useAuthStore()

const state = reactive<LoginPayload>({ email: '', password: '', remember: false })
const { error, submit } = useApiForm(() => Object.keys(state))

function validate(data: Partial<LoginPayload>): FormError[] {
  const errors: FormError[] = []
  if (!data.email) errors.push({ name: 'email', message: 'Enter your email.' })
  if (!data.password) errors.push({ name: 'password', message: 'Enter your password.' })
  return errors
}

async function onSubmit() {
  await submit(async () => {
    await auth.login({ ...state })
    await navigateTo(redirectTarget(route.query.redirect))
  })
}
</script>

<template>
  <UCard class="mx-auto max-w-md">
    <template #header>
      <h1 class="text-xl font-semibold">
        Log in
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
        Your password has been reset. Log in with your new password.
      </p>

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

      <UFormField
        label="Password"
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
          label="Remember me"
        />
        <ULink
          to="/forgot-password"
          class="text-sm"
        >
          Forgot password?
        </ULink>
      </div>

      <UButton
        type="submit"
        label="Log in"
        block
      />
    </UForm>

    <template #footer>
      <p class="text-sm text-muted">
        No account yet?
        <ULink
          to="/register"
          class="text-primary"
        >
          Register
        </ULink>
      </p>
    </template>
  </UCard>
</template>
