<script setup lang="ts">
import type { FormError } from '@nuxt/ui'

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: 'Register' })

const route = useRoute()
const auth = useAuthStore()

const state = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const { error, submit } = useApiForm(() => Object.keys(state))

function validate(data: Partial<typeof state>): FormError[] {
  const errors: FormError[] = []
  if (!data.name) errors.push({ name: 'name', message: 'Enter your name.' })
  if (!data.email) errors.push({ name: 'email', message: 'Enter your email.' })
  if (!data.password) errors.push({ name: 'password', message: 'Choose a password.' })
  if (data.password_confirmation !== data.password) {
    errors.push({ name: 'password_confirmation', message: 'The passwords don\'t match.' })
  }
  return errors
}

async function onSubmit() {
  await submit(async () => {
    // The API only checks the password once; the confirmation is client-side.
    await auth.register({ name: state.name, email: state.email, password: state.password })
    await navigateTo(redirectTarget(route.query.redirect))
  })
}
</script>

<template>
  <UCard class="mx-auto max-w-md">
    <template #header>
      <h1 class="text-xl font-semibold">
        Create an account
      </h1>
    </template>

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

      <UFormField
        label="Name"
        name="name"
      >
        <UInput
          v-model="state.name"
          autocomplete="name"
          class="w-full"
        />
      </UFormField>

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
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Confirm password"
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
        label="Register"
        block
      />
    </UForm>

    <template #footer>
      <p class="text-sm text-muted">
        Already have an account?
        <ULink
          to="/login"
          class="text-primary"
        >
          Log in
        </ULink>
      </p>
    </template>
  </UCard>
</template>
