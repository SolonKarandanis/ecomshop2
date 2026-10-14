<script setup lang="ts">
import type { Paginated, Review } from '~/types/catalogue'

// `initial` is the first page, embedded in the SSR-rendered product response;
// later pages load client-side from GET /products/{product}/reviews.
const props = defineProps<{ productId: number, initial: Paginated<Review> }>()

const reviews = ref<Review[]>([...props.initial.data])
const meta = ref(props.initial.meta)
const loading = ref(false)
const error = ref(false)

const hasMore = computed(() => meta.value.current_page < meta.value.last_page)

async function loadMore() {
  loading.value = true
  error.value = false
  try {
    const page = await useCatalogApi()<Paginated<Review>>(`/products/${props.productId}/reviews`, {
      query: { page: meta.value.current_page + 1 },
    })
    reviews.value.push(...page.data)
    meta.value = page.meta
  }
  catch {
    error.value = true
  }
  finally {
    loading.value = false
  }
}

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('en-IE', { dateStyle: 'medium', timeZone: 'UTC' }) : ''
}
</script>

<template>
  <section>
    <h2 class="text-xl font-semibold">
      Reviews ({{ meta.total }})
    </h2>

    <p
      v-if="!reviews.length"
      class="mt-4 text-muted"
    >
      No reviews yet.
    </p>

    <ul
      v-else
      class="mt-4 divide-y divide-default"
    >
      <li
        v-for="review in reviews"
        :key="review.id"
        class="py-4"
      >
        <div class="flex items-center justify-between text-sm">
          <span
            class="text-warning"
            :aria-label="`${review.rating} out of 5 stars`"
          >{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}</span>
          <time
            v-if="review.created_at"
            :datetime="review.created_at"
            class="text-muted"
          >{{ formatDate(review.created_at) }}</time>
        </div>
        <p
          v-if="review.comment"
          class="mt-2"
        >
          {{ review.comment }}
        </p>
        <p
          v-if="review.admin_reply"
          class="mt-2 border-l-2 border-default pl-3 text-sm text-muted"
        >
          {{ review.admin_reply }}
        </p>
      </li>
    </ul>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      title="Couldn't load more reviews. Try again."
      class="mt-4"
    />

    <UButton
      v-if="hasMore"
      label="Load more reviews"
      color="neutral"
      variant="outline"
      class="mt-4"
      :loading="loading"
      @click="loadMore"
    />
  </section>
</template>
