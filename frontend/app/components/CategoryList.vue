<script setup lang="ts">
import type { Category } from '~/types/catalogue'

defineProps<{ categories: Category[] }>()
</script>

<template>
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
    <NuxtLink
      v-for="category in categories"
      :key="category.id"
      :to="`/categories/${category.slug}`"
      class="group overflow-hidden rounded-lg border border-default"
    >
      <div class="flex h-32 items-center justify-center bg-elevated">
        <img
          :src="category.thumbnail"
          :alt="category.name"
          width="100"
          height="100"
          loading="lazy"
          class="size-25 object-contain"
        >
      </div>
      <span class="block p-3 font-medium group-hover:text-primary">{{ category.name }}</span>
    </NuxtLink>
  </div>
</template>
