<script setup lang="ts">
const auth = useAuthStore()
const loggingOut = ref(false)

async function logout() {
  loggingOut.value = true
  try {
    await auth.logout()
    await navigateTo('/')
  }
  finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <div>
    <header class="border-b border-default">
      <UContainer class="flex h-16 items-center justify-between">
        <nav class="flex items-center gap-6">
          <NuxtLink
            to="/"
            class="text-lg font-bold"
          >
            ecomshop
          </NuxtLink>
          <NuxtLink
            to="/products"
            class="text-sm hover:text-primary"
            active-class="text-primary"
          >
            Products
          </NuxtLink>
          <NuxtLink
            to="/categories"
            class="text-sm hover:text-primary"
            active-class="text-primary"
          >
            Categories
          </NuxtLink>
        </nav>

        <!--
          Client-only: the server never sees the session (ADR-0002), and the
          session check can finish before hydration. Hidden until resolved so
          logged-in users don't see a flash of "Log in".
        -->
        <ClientOnly>
          <div
            v-if="auth.resolved"
            class="flex items-center gap-2"
          >
            <template v-if="auth.user">
              <span class="text-sm text-muted">{{ auth.user.name }}</span>
              <UButton
                label="Log out"
                color="neutral"
                variant="ghost"
                :loading="loggingOut"
                @click="logout"
              />
            </template>
            <template v-else>
              <UButton
                to="/login"
                label="Log in"
                color="neutral"
                variant="ghost"
              />
              <UButton
                to="/register"
                label="Register"
              />
            </template>
          </div>
        </ClientOnly>
      </UContainer>
    </header>

    <UContainer
      as="main"
      class="py-10"
    >
      <slot />
    </UContainer>
  </div>
</template>
