<script setup lang="ts">
import type { FormError } from '@nuxt/ui'

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: 'Register' })

const route = useRoute()
const auth = useAuthStore()
const { data: config } = await usePublicConfig()

const state = reactive({ name: '', email: '', password: '', password_confirmation: '', role: ROLE_BUYER })
const { error, submit } = useApiForm(() => Object.keys(state))
// Set once a Supplier registers: they start PENDING and aren't logged in.
const awaitingApproval = ref(false)

const roleItems = [
  { label: 'I want to buy', value: ROLE_BUYER },
  { label: 'I want to sell (Supplier)', value: ROLE_SUPPLIER },
]

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
    // The role is only sent while the Suppliers feature is on.
    const user = await auth.register({
      name: state.name,
      email: state.email,
      password: state.password,
      ...(config.value?.suppliers_enabled ? { role: state.role } : {}),
    })
    if (user.status === USER_STATUS_PENDING) {
      awaitingApproval.value = true
      return
    }
    await navigateTo(redirectTarget(route.query.redirect))
  })
}
</script>

<template>
  <UCard
    v-if="awaitingApproval"
    class="mx-auto max-w-md"
  >
    <template #header>
      <h1 class="text-xl font-semibold">
        Thanks for registering
      </h1>
    </template>

    <p class="text-sm">
      Your Supplier account is awaiting approval. We'll email you once an admin
      has approved it, and you'll be able to log in then.
    </p>

    <template #footer>
      <UButton
        to="/"
        label="Back to the shop"
        color="neutral"
        variant="ghost"
      />
    </template>
  </UCard>

  <UCard
    v-else
    class="mx-auto max-w-md"
  >
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
        v-if="config?.suppliers_enabled"
        label="Account type"
        name="role"
      >
        <URadioGroup
          v-model="state.role"
          :items="roleItems"
        />
      </UFormField>

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
        class="cursor-pointer"
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
