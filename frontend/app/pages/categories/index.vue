<script setup lang="ts">
const { t } = useI18n()

useSeoMeta({ title: () => t('categories.title') })

const { data: categories } = await useCategories()
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-3xl font-bold">
      {{ t('categories.title') }}
    </h1>
    <CategoryList :categories="categories ?? []" />
  </div>
</template>
