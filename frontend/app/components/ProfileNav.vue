<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

// `exact`, so "Profile" (/profile) isn't also highlighted on /profile/password.
const items: NavigationMenuItem[] = [
  { label: 'Profile', icon: 'i-lucide-user', to: '/profile', exact: true },
  { label: 'Password', icon: 'i-lucide-lock', to: '/profile/password', exact: true },
  { label: 'Addresses', icon: 'i-lucide-map-pin', to: '/profile/addresses', exact: true },
]
</script>

<template>
  <UNavigationMenu
    :items="items"
    aria-label="Account"
    class="border-b border-default"
  />
</template>
