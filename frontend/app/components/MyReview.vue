<script setup lang="ts">
import { REVIEW_HIDDEN, type MyReview, type Review } from '~/types/catalogue'

// The logged-in user's side of a product's reviews: the write form, their own
// review to edit, or why they can't review yet. Client-side only: the product
// page is server-rendered, and this call needs the session (ADR-0002).
const props = defineProps<{ productId: number }>()
const emit = defineEmits<{ saved: [review: Review] }>()

const { t } = useI18n()
const auth = useAuthStore()

const mine = ref<MyReview | null>(null)
const loadError = ref(false)
const justSaved = ref(false)

async function load() {
  loadError.value = false
  try {
    mine.value = (await useApi()<{ data: MyReview }>(`/products/${props.productId}/reviews/mine`)).data
  }
  catch {
    mine.value = null
    loadError.value = true
  }
}

// The session check may still be running when the page mounts; logging out
// on this page clears it again.
watch(() => auth.user?.id, (id) => {
  mine.value = null
  justSaved.value = false
  if (id) load()
}, { immediate: true })

// Admins and Suppliers can't review, so for them there's nothing to show.
const visible = computed(() => loadError.value || !!mine.value?.review || !!mine.value?.can_review || (!!mine.value && auth.isBuyer))

function onSaved(review: Review) {
  mine.value = { can_review: false, review }
  justSaved.value = true
  emit('saved', review)
}
</script>

<template>
  <section
    v-if="auth.user && visible"
    class="rounded-lg border border-default p-4"
  >
    <UAlert
      v-if="loadError"
      color="error"
      variant="subtle"
      :title="t('reviews.mine.load_error')"
    />

    <template v-else-if="mine?.review">
      <h2 class="mb-3 text-lg font-semibold">
        {{ t('reviews.mine.title') }}
      </h2>
      <UAlert
        v-if="mine.review.status === REVIEW_HIDDEN"
        color="warning"
        variant="subtle"
        :title="t('reviews.mine.hidden')"
        class="mb-4"
      />
      <UAlert
        v-else-if="justSaved"
        color="success"
        variant="subtle"
        :title="t('reviews.mine.saved')"
        class="mb-4"
      />
      <ReviewForm
        :key="mine.review.id"
        :product-id="productId"
        :review="mine.review"
        @saved="onSaved"
      />
    </template>

    <template v-else-if="mine?.can_review">
      <h2 class="mb-3 text-lg font-semibold">
        {{ t('reviews.mine.write') }}
      </h2>
      <ReviewForm
        :product-id="productId"
        :review="null"
        @saved="onSaved"
      />
    </template>

    <p
      v-else
      class="text-sm text-muted"
    >
      {{ t('reviews.mine.not_eligible') }}
    </p>
  </section>
</template>
