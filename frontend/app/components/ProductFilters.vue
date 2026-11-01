<script setup lang="ts">
import type { Category } from '~/types/catalogue'
import type { ProductFilters, ProductSort } from '~/utils/catalogueQuery'

const props = defineProps<{ filters: ProductFilters, categories: Category[] }>()
const emit = defineEmits<{ change: [filters: ProductFilters] }>()
const { t } = useI18n()

const sortItems = computed(() => SORT_OPTIONS.map(option => ({ label: t(option.labelKey), value: option.value })))

// The search box only applies on submit; sort and categories apply immediately.
const search = ref(props.filters.q)
watch(() => props.filters.q, (q) => { search.value = q })

// Any filter change starts over from the first page.
function apply(changes: Partial<ProductFilters>) {
  emit('change', { ...props.filters, ...changes, page: 1 })
}
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
    <form
      class="flex flex-1 gap-2"
      role="search"
      @submit.prevent="apply({ q: search.trim() })"
    >
      <UInput
        v-model="search"
        :placeholder="t('filters.search_placeholder')"
        :aria-label="t('filters.search_placeholder')"
        class="flex-1"
      />
      <UButton
        type="submit"
        :label="t('filters.search')"
        color="neutral"
        variant="outline"
      />
    </form>

    <USelectMenu
      :model-value="filters.categories"
      :items="categories"
      value-key="id"
      label-key="name"
      multiple
      :placeholder="t('filters.all_categories')"
      :aria-label="t('filters.categories')"
      class="sm:w-56"
      @update:model-value="(ids: number[]) => apply({ categories: ids })"
    />

    <USelect
      :model-value="filters.sort"
      :items="sortItems"
      :aria-label="t('filters.sort_by')"
      class="sm:w-48"
      @update:model-value="(sort: ProductSort) => apply({ sort })"
    />
  </div>
</template>
