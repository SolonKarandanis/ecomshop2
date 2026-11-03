<script setup lang="ts">
import type { Paginated, Review } from '~/types/catalogue'

// `initial` is the first page, embedded in the SSR-rendered product response;
// later pages load client-side from GET /products/{product}/reviews.
const props = defineProps<{ productId: number, initial: Paginated<Review> }>()
// A review was hidden: the page refreshes its rating summary.
const emit = defineEmits<{ hidden: [review: Review] }>()
const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()

const reviews = ref<Review[]>([...props.initial.data])
const meta = ref(props.initial.meta)
const loading = ref(false)
const error = ref(false)

const hasMore = computed(() => meta.value.current_page < meta.value.last_page)

const hiding = ref<number | null>(null)

// Shown to admins only; the API's `admin-action` gate is what actually decides.
async function hide(review: Review) {
  hiding.value = review.id
  try {
    await useApi()(`/products/${props.productId}/reviews/${review.id}/hide`, { method: 'PATCH' })
    reviews.value = reviews.value.filter(r => r.id !== review.id)
    meta.value = { ...meta.value, total: Math.max(0, meta.value.total - 1) }
    emit('hidden', review)
  }
  catch (e) {
    toast.add({ title: apiErrorMessage(e, t('reviews.hide_error')), color: 'error' })
  }
  finally {
    hiding.value = null
  }
}

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
</script>

<template>
  <section>
    <h2 class="text-xl font-semibold">
      {{ t('reviews.title', { count: meta.total }) }}
    </h2>

    <p
      v-if="!reviews.length"
      class="mt-4 text-muted"
    >
      {{ t('reviews.empty') }}
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
            :aria-label="t('reviews.stars', { rating: review.rating })"
          >{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}</span>
          <div class="flex items-center gap-3">
            <time
              v-if="review.created_at"
              :datetime="review.created_at"
              class="text-muted"
            >{{ formatDate(review.created_at) }}</time>
            <ClientOnly>
              <UButton
                v-if="auth.isAdmin"
                :label="t('reviews.hide')"
                icon="i-lucide-eye-off"
                size="xs"
                color="neutral"
                variant="outline"
                class="cursor-pointer"
                :loading="hiding === review.id"
                @click="hide(review)"
              />
            </ClientOnly>
          </div>
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
      :title="t('reviews.load_error')"
      class="mt-4"
    />

    <UButton
      v-if="hasMore"
      :label="t('reviews.load_more')"
      color="neutral"
      variant="outline"
      class="mt-4"
      :loading="loading"
      @click="loadMore"
    />
  </section>
</template>
