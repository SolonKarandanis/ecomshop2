<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const localePath = useLocalePath()

// `exact`, so "Profile" (/profile) isn't also highlighted on /profile/password.
const items = computed<NavigationMenuItem[]>(() => [
  { label: 'Profile', icon: 'i-lucide-user', to: localePath('/profile'), exact: true },
  { label: 'Password', icon: 'i-lucide-lock', to: localePath('/profile/password'), exact: true },
  { label: 'Addresses', icon: 'i-lucide-map-pin', to: localePath('/profile/addresses'), exact: true },
])
</script>

<template>
  <UNavigationMenu
    :items="items"
    aria-label="Account"
    class="border-b border-default"
  />
</template>
