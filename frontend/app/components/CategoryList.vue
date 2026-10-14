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
      <img
        :src="category.thumbnail"
        :alt="category.name"
        loading="lazy"
        class="aspect-video w-full bg-elevated object-cover"
      >
      <span class="block p-3 font-medium group-hover:text-primary">{{ category.name }}</span>
    </NuxtLink>
  </div>
</template>
