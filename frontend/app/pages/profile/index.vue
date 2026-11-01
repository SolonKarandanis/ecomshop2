<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { Profile, UpdateProfilePayload } from '~/types/profile'

const { t } = useI18n()

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: () => t('profile.title') })

const auth = useAuthStore()
const toast = useToast()

const { data: profile, error: loadError } = await useAsyncData(
  'profile',
  () => useApi()<{ data: Profile }>('/profile').then(response => response.data),
)

const state = reactive<UpdateProfilePayload>({ name: '', email: '' })
watch(profile, (value) => {
  if (value) {
    state.name = value.name
    state.email = value.email
  }
}, { immediate: true })

const { error, submit } = useApiForm(() => Object.keys(state))
const submitting = ref(false)

function validate(data: Partial<UpdateProfilePayload>): FormError[] {
  const errors: FormError[] = []
  if (!data.name?.trim()) errors.push({ name: 'name', message: t('auth.validation.name') })
  if (!data.email?.trim()) errors.push({ name: 'email', message: t('auth.validation.email') })
  return errors
}

async function onSubmit() {
  submitting.value = true
  let saved = false
  await submit(async () => {
    const response = await useApi()<{ data: Profile }>('/profile', { method: 'PATCH', body: { ...state } })
    profile.value = response.data
    saved = true
    toast.add({ title: t('profile.saved'), color: 'success' })
  })
  submitting.value = false

  if (saved) {
    // ProfileData has no id or roles, so refetch the User for the header's name.
    await auth.fetchUser().catch(() => null)
  }
}
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <h1 class="text-3xl font-bold">
      {{ t('profile.heading') }}
    </h1>
    <ProfileNav />

    <UAlert
      v-if="loadError"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(loadError, t('profile.load_error'))"
    />

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

      <div class="flex justify-end">
        <UButton
          type="submit"
          :label="t('profile.save')"
          :loading="submitting"
          class="cursor-pointer"
        />
      </div>
    </UForm>
  </div>
</template>
