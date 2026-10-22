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
      <!--
        On phones the nav links wrap onto their own row below the logo and
        auth buttons; from `sm` up everything sits on one row.
      -->
      <UContainer class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-3 sm:h-16 sm:flex-nowrap sm:py-0">
        <NuxtLink
          to="/"
          class="text-lg font-bold"
        >
          ecomshop
        </NuxtLink>

        <nav class="order-last flex w-full items-center gap-6 sm:order-none sm:w-auto sm:flex-1">
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
          <!-- Client-only for the same reason as the auth buttons below. -->
          <ClientOnly>
            <template v-if="auth.isSupplier">
              <NuxtLink
                to="/supplier/orders"
                class="text-sm hover:text-primary"
                active-class="text-primary"
              >
                Supplier orders
              </NuxtLink>
              <NuxtLink
                to="/supplier/products"
                class="text-sm hover:text-primary"
                active-class="text-primary"
              >
                My products
              </NuxtLink>
            </template>
          </ClientOnly>
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
            <!-- Suppliers and admins can't add to a cart (`buyer-action` gate), so they get no cart. -->
            <CartIcon v-if="!auth.user || auth.isBuyer" />
            <template v-if="auth.user">
              <NuxtLink
                to="/profile"
                class="text-sm text-muted hover:text-primary"
                active-class="text-primary"
              >
                {{ auth.user.name }}
              </NuxtLink>
              <UButton class="cursor-pointer"
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
