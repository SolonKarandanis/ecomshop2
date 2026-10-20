<template>
  <UCard class="mx-auto max-w-md text-center">
    <div class="space-y-4">
      <h1 class="text-xl font-semibold">
        Order not found
      </h1>
      <p class="text-muted">
        We couldn't find that order. Your orders are listed on your account.
      </p>
      <div class="flex justify-center gap-2">
        <UButton
          to="/orders"
          label="My orders"
        />
        <UButton
          to="/products"
          label="Keep shopping"
          color="neutral"
          variant="outline"
        />
      </div>
    </div>
  </UCard>
</template>
