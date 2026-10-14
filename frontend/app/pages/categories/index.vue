<script setup lang="ts">
useSeoMeta({ title: 'Categories' })

const { data: categories } = await useCategories()
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-3xl font-bold">
      Categories
    </h1>
    <CategoryList :categories="categories ?? []" />
  </div>
</template>
