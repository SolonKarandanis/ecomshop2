<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { Review } from '~/types/catalogue'

// Writes a new review, or edits `review` when given: the API treats a second
// submit from the same Buyer as an edit (ReviewController::store).
const props = defineProps<{ productId: number, review: Review | null }>()
const emit = defineEmits<{ saved: [review: Review] }>()

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()

const MAX_COMMENT = 2000

const state = reactive({ rating: props.review?.rating ?? 0, comment: props.review?.comment ?? '' })
const { error, submit } = useApiForm(() => Object.keys(state))
const submitting = ref(false)
// The session ran out while the page was open: say so, with a way back.
const sessionExpired = ref(false)

// SubmitReviewRequest's own rules, checked here first; the API still decides.
function validate(data: Partial<typeof state>): FormError[] {
  const errors: FormError[] = []
  if (!data.rating) errors.push({ name: 'rating', message: t('reviews.form.rating_required') })
  if ((data.comment ?? '').length > MAX_COMMENT) errors.push({ name: 'comment', message: t('reviews.form.comment_too_long', { max: MAX_COMMENT }) })
  return errors
}

async function onSubmit() {
  submitting.value = true
  sessionExpired.value = false
  await submit(async () => {
    try {
      const response = await useApi()<{ data: Review }>(`/products/${props.productId}/reviews`, {
        method: 'POST',
        body: { rating: state.rating, comment: state.comment.trim() || null },
      })
      emit('saved', response.data)
    }
    catch (e) {
      if (apiStatus(e) === 401) {
        sessionExpired.value = true
        return
      }
      // A 400 (not eligible) or 422 is shown by useApiForm.
      throw e
    }
  })
  submitting.value = false
}

const loginLink = computed(() => localePath({ path: '/login', query: { redirect: route.fullPath } }))
</script>

<template>
  <UForm
    ref="form"
    :state="state"
    :validate="validate"
    class="space-y-4"
    @submit="onSubmit"
  >
    <UAlert
      v-if="sessionExpired"
      color="warning"
      variant="subtle"
      :title="t('reviews.form.session_expired')"
      :actions="[{ label: t('nav.log_in'), to: loginLink, color: 'neutral', variant: 'outline' }]"
    />
    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :title="error"
    />

    <UFormField
      :label="t('reviews.form.rating')"
      name="rating"
      required
    >
      <div
        role="radiogroup"
        :aria-label="t('reviews.form.rating')"
        class="flex gap-1"
      >
        <button
          v-for="star in 5"
          :key="star"
          type="button"
          role="radio"
          :aria-checked="state.rating === star"
          :aria-label="t('reviews.stars', { rating: star })"
          class="cursor-pointer text-2xl leading-none text-warning"
          @click="state.rating = star"
        >
          {{ star <= state.rating ? '★' : '☆' }}
        </button>
      </div>
    </UFormField>

    <UFormField
      :label="t('reviews.form.comment')"
      name="comment"
      :hint="`${state.comment.length}/${MAX_COMMENT}`"
    >
      <UTextarea
        v-model="state.comment"
        :rows="4"
        :maxlength="MAX_COMMENT"
        class="w-full"
      />
    </UFormField>

    <UButton
      type="submit"
      :label="review ? t('reviews.form.update') : t('reviews.form.submit')"
      :loading="submitting"
      class="cursor-pointer"
    />
  </UForm>
</template>
