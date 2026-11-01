<script setup lang="ts">
import type { FormError } from '@nuxt/ui'

const localePath = useLocalePath()
const { locale, t } = useI18n()

definePageMeta({ middleware: 'guest' })
useSeoMeta({ title: () => t('register.title') })

const route = useRoute()
const auth = useAuthStore()
const { data: config } = await usePublicConfig()

const state = reactive({ name: '', email: '', password: '', password_confirmation: '', role: ROLE_BUYER })
const { error, submit } = useApiForm(() => Object.keys(state))
// Set once a Supplier registers: they start PENDING and aren't logged in.
const awaitingApproval = ref(false)

const roleItems = computed(() => [
  { label: t('register.role.buy'), value: ROLE_BUYER },
  { label: t('register.role.sell'), value: ROLE_SUPPLIER },
])

function validate(data: Partial<typeof state>): FormError[] {
  const errors: FormError[] = []
  if (!data.name) errors.push({ name: 'name', message: t('auth.validation.name') })
  if (!data.email) errors.push({ name: 'email', message: t('auth.validation.email') })
  if (!data.password) errors.push({ name: 'password', message: t('auth.validation.choose_password') })
  if (data.password_confirmation !== data.password) {
    errors.push({ name: 'password_confirmation', message: t('auth.validation.passwords_match') })
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
    // A new account takes the language it signed up in; it's not essential to the sign-up.
    await auth.saveLocale(locale.value).catch(() => null)
    await navigateTo(redirectTarget(route.query.redirect, localePath('/')))
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
        {{ t('register.thanks') }}
      </h1>
    </template>

    <p class="text-sm">
      {{ t('register.awaiting_approval') }}
    </p>

    <template #footer>
      <UButton
        :to="localePath('/')"
        :label="t('register.back_to_shop')"
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
        {{ t('register.heading') }}
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
        :label="t('register.account_type')"
        name="role"
      >
        <URadioGroup
          v-model="state.role"
          :items="roleItems"
        />
      </UFormField>

      <UFormField
        :label="t('auth.field.name')"
        name="name"
      >
        <UInput
          v-model="state.name"
          autocomplete="name"
          class="w-full"
        />
      </UFormField>

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
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UFormField
        :label="t('auth.field.password_confirmation')"
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
        :label="t('register.submit')"
        class="cursor-pointer"
        block
      />
    </UForm>

    <template #footer>
      <p class="text-sm text-muted">
        {{ t('register.have_account') }}
        <ULink
          :to="localePath('/login')"
          class="text-primary"
        >
          {{ t('register.log_in') }}
        </ULink>
      </p>
    </template>
  </UCard>
</template>
