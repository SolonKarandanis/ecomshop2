<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { Profile, UpdateProfilePayload } from '~/types/profile'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'Your profile' })

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
  if (!data.name?.trim()) errors.push({ name: 'name', message: 'Enter your name.' })
  if (!data.email?.trim()) errors.push({ name: 'email', message: 'Enter your email.' })
  return errors
}

async function onSubmit() {
  submitting.value = true
  let saved = false
  await submit(async () => {
    const response = await useApi()<{ data: Profile }>('/profile', { method: 'PATCH', body: { ...state } })
    profile.value = response.data
    saved = true
    toast.add({ title: 'Your profile was saved.', color: 'success' })
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
      Your account
    </h1>
    <ProfileNav />

    <UAlert
      v-if="loadError"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(loadError, 'We couldn\'t load your profile. Reload the page to try again.')"
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

      <div class="flex justify-end">
        <UButton
          type="submit"
          label="Save profile"
          :loading="submitting"
          class="cursor-pointer"
        />
      </div>
    </UForm>
  </div>
</template>
