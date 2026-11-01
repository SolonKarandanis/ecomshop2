<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const localePath = useLocalePath()
const { t } = useI18n()

// `exact`, so "Profile" (/profile) isn't also highlighted on /profile/password.
const items = computed<NavigationMenuItem[]>(() => [
  { label: t('profile.nav.profile'), icon: 'i-lucide-user', to: localePath('/profile'), exact: true },
  { label: t('profile.nav.password'), icon: 'i-lucide-lock', to: localePath('/profile/password'), exact: true },
  { label: t('profile.nav.addresses'), icon: 'i-lucide-map-pin', to: localePath('/profile/addresses'), exact: true },
])
</script>

<template>
  <UNavigationMenu
    :items="items"
    :aria-label="t('profile.nav.label')"
    class="border-b border-default"
  />
</template>
