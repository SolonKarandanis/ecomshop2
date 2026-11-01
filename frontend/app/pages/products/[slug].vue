<script setup lang="ts">
import type { ProductDetailResponse } from '~/types/catalogue'

const localePath = useLocalePath()
const { t } = useI18n()

const route = useRoute()

const { data, error } = await useCatalogFetch<ProductDetailResponse>(() => `/products/${route.params.slug}`)

if (error.value || !data.value) {
  throw createError({ statusCode: error.value?.statusCode ?? 404, statusMessage: t('product.not_found'), fatal: true })
}

const product = computed(() => data.value!.data)

useSeoMeta({
  title: () => product.value.name,
  description: () => product.value.description ?? undefined,
  ogImage: () => product.value.image,
})
</script>

<template>
  <div class="space-y-12">
    <div class="grid gap-8 md:grid-cols-2">
      <img
        :src="product.image"
        :alt="product.name"
        class="aspect-square w-full rounded-lg bg-elevated object-cover"
      >

      <div class="space-y-4">
        <div class="flex flex-wrap gap-2 text-sm text-muted">
          <NuxtLink
            v-if="product.category"
            :to="localePath(`/categories/${product.category.slug}`)"
            class="hover:text-primary"
          >
            {{ product.category.name }}
          </NuxtLink>
          <span v-if="product.brand">· {{ product.brand.name }}</span>
        </div>

        <h1 class="text-3xl font-bold">
          {{ product.name }}
        </h1>

        <div class="flex items-center gap-3">
          <span class="text-2xl font-semibold">{{ formatPrice(product.price) }}</span>
          <UBadge
            v-if="product.on_sale"
            :label="t('product.badge.on_sale')"
            color="error"
            variant="subtle"
          />
          <UBadge
            :label="product.in_stock ? t('product.in_stock') : t('product.badge.out_of_stock')"
            :color="product.in_stock ? 'success' : 'neutral'"
            variant="subtle"
          />
        </div>

        <AddToCartButton
          :product-id="product.id"
          :in-stock="product.in_stock"
          with-quantity
        />

        <p
          v-if="product.average_rating !== null"
          class="text-sm text-muted"
        >
          {{ t('product.rating', { rating: product.average_rating.toFixed(1), count: product.reviews_count }, product.reviews_count) }}
        </p>

        <!-- Stored as Markdown (Filament MarkdownEditor); shown as plain text for now. -->
        <p
          v-if="product.description"
          class="whitespace-pre-line"
        >
          {{ product.description }}
        </p>
      </div>
    </div>

    <ReviewList
      :key="product.id"
      :product-id="product.id"
      :initial="data!.reviews"
    />
  </div>
</template>
