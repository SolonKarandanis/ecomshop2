import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody, setResponseStatus } from 'h3'
import ProductPage from '~/pages/products/[slug].vue'
import type { MyReview, Review } from '~/types/catalogue'

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>

const BUYER = { id: 5, name: 'Sam Buyer', email: 'sam@example.com', status: 'user.status.active', locale: 'en', roles: ['role.buyer'] }
const ADMIN = { id: 1, name: 'Ada Admin', email: 'ada@example.com', status: 'user.status.active', locale: 'en', roles: ['role.admin'] }
const PUBLISHED = 'review.status.published'
const HIDDEN = 'review.status.hidden'

// A fake API for one product, with its reviews and the logged-in user.
let user: typeof BUYER | null
let reviews: Review[]
let eligible: boolean
let submitted: Record<string, unknown>[]
let hidden: number[]
// Set to answer the next review submission with this status instead.
let refuseSubmit: { status: number, body: unknown } | null
let mineAnswered: number

function review(id: number, userId: number, rating: number, fields: Partial<Review> = {}): Review {
  return { id, product_id: 3, user_id: userId, rating, comment: `Review ${id}`, status: PUBLISHED, admin_reply: null, created_at: '2026-10-01T10:00:00Z', ...fields }
}

const published = () => reviews.filter(r => r.status === PUBLISHED)

beforeEach(() => {
  user = null
  reviews = [review(1, 8, 5), review(2, 9, 3)]
  eligible = false
  submitted = []
  hidden = []
  refuseSubmit = null
  mineAnswered = 0
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()
  useAuthStore().user = null

  registerEndpoint('/api/user', (event) => {
    if (!user) {
      setResponseStatus(event, 401)
      return { message: 'Unauthenticated.' }
    }
    return { data: user }
  })
  registerEndpoint('/api/cart', () => ({ data: { total_price: 0, items: [], notice: null } }))
  registerEndpoint('/api/products/mug', () => {
    const shown = published()
    const average = shown.length ? shown.reduce((sum, r) => sum + r.rating, 0) / shown.length : null
    return {
      data: { id: 3, name: 'Mug', slug: 'mug', description: null, price: 10, is_featured: false, in_stock: true, on_sale: false, average_rating: average, reviews_count: shown.length, thumbnail: '', image: '' },
      reviews: { data: [...shown].reverse(), meta: { current_page: 1, last_page: 1, per_page: 5, total: shown.length } },
    }
  })
  registerEndpoint('/api/products/3/reviews/mine', (): { data: MyReview } => {
    const mine = reviews.find(r => r.user_id === user?.id) ?? null
    mineAnswered++
    return { data: { can_review: !mine && eligible, review: mine } }
  })
  registerEndpoint('/api/products/3/reviews', {
    method: 'POST',
    handler: async (event) => {
      if (refuseSubmit) {
        setResponseStatus(event, refuseSubmit.status)
        return refuseSubmit.body
      }
      const body = await readBody<{ rating: number, comment: string | null }>(event)
      submitted.push(body)
      const existing = reviews.find(r => r.user_id === user!.id)
      if (existing) {
        Object.assign(existing, body)
        return { data: existing }
      }
      const created = review(10, user!.id, body.rating, { comment: body.comment, created_at: '2026-11-03T10:00:00Z' })
      reviews.push(created)
      setResponseStatus(event, 201)
      return { data: created }
    },
  })
  for (const id of [1, 2]) {
    registerEndpoint(`/api/products/3/reviews/${id}/hide`, {
      method: 'PATCH',
      handler: () => {
        hidden.push(id)
        reviews.find(r => r.id === id)!.status = HIDDEN
        return { data: reviews.find(r => r.id === id) }
      },
    })
  }
})

let mounted: Wrapper[] = []
afterEach(() => {
  // Unmounted before the next test's clearNuxtData() empties the page's data.
  mounted.forEach(page => page.unmount())
  mounted = []
})

// API calls go through registerEndpoint's handlers, which take a few ticks.
async function settle(until?: () => boolean) {
  await vi.waitFor(() => {
    if (until && !until()) throw new Error('not yet')
  }, { timeout: 1000, interval: 10 })
  await flushPromises()
}

async function open(as: typeof BUYER | null): Promise<Wrapper> {
  user = as
  if (as) {
    await useAuthStore().fetchUser()
  }
  const page = await mountSuspended(ProductPage, { route: '/products/mug' })
  mounted.push(page)
  // The user's own review section loads after mount, client-side.
  await settle(() => !as || mineAnswered > 0)
  await flushPromises()
  return page
}

const summary = (page: Wrapper) => page.text().match(/★ [\d.]+ from \d+ reviews?/)?.[0]
const reviewTexts = (page: Wrapper) => page.findAll('li').map(li => li.find('p')?.text()).filter(Boolean)

async function rate(page: Wrapper, stars: number) {
  await page.find(`[role="radiogroup"] button:nth-child(${stars})`).trigger('click')
}

async function submit(page: Wrapper, until: () => boolean) {
  await page.find('form').trigger('submit')
  await settle(until)
}

describe('product page reviews', () => {
  it('shows a logged-out visitor the reviews but no form', async () => {
    const page = await open(null)

    expect(reviewTexts(page)).toEqual(['Review 2', 'Review 1'])
    expect(page.find('form').exists()).toBe(false)
    expect(page.text()).not.toContain('Write a review')
  })

  it('lets an eligible Buyer write a review, which appears with the new rating summary', async () => {
    eligible = true
    const page = await open(BUYER)
    expect(page.text()).toContain('Write a review')
    expect(summary(page)).toBe('★ 4.0 from 2 reviews')

    await rate(page, 2)
    await page.find('textarea').setValue('  Chipped on arrival  ')
    await submit(page, () => page.text().includes('★ 3.3 from 3 reviews'))

    expect(submitted).toEqual([{ rating: 2, comment: 'Chipped on arrival' }])
    expect(reviewTexts(page)[0]).toBe('Chipped on arrival')
    expect(summary(page)).toBe('★ 3.3 from 3 reviews')
    expect(page.text()).toContain('Your review')
    expect(page.text()).toContain('Thanks, your review has been saved.')
    expect(page.find('button[type="submit"]').text()).toBe('Update review')
  })

  it('asks for a rating before sending anything', async () => {
    eligible = true
    const page = await open(BUYER)

    await submit(page, () => page.text().includes('Choose a rating'))

    expect(submitted).toEqual([])
    expect(page.text()).toContain('Choose a rating from 1 to 5 stars.')
  })

  it('prefills a Buyer\'s existing review, and saves the edit in place', async () => {
    reviews.push(review(7, BUYER.id, 4, { comment: 'Good mug' }))
    const page = await open(BUYER)

    expect(page.text()).toContain('Your review')
    expect(page.find('[role="radio"][aria-checked="true"]').attributes('aria-label')).toBe('4 out of 5 stars')
    expect((page.find('textarea').element as HTMLTextAreaElement).value).toBe('Good mug')

    await rate(page, 5)
    await submit(page, () => page.text().includes('★ 4.3 from 3 reviews'))

    expect(submitted).toEqual([{ rating: 5, comment: 'Good mug' }])
    expect(reviewTexts(page)).toContain('Good mug')
    expect(summary(page)).toBe('★ 4.3 from 3 reviews')
  })

  it('tells a Buyer when a moderator has hidden their review', async () => {
    reviews.push(review(7, BUYER.id, 1, { comment: 'Rude words', status: HIDDEN }))
    const page = await open(BUYER)

    expect(page.text()).toContain('A moderator has hidden this review')
    expect(reviewTexts(page)).not.toContain('Rude words')
  })

  it('explains to a Buyer without a delivered order why they can\'t review yet', async () => {
    const page = await open(BUYER)

    expect(page.text()).toContain('You can review this product once an order containing it has been delivered.')
    expect(page.find('form').exists()).toBe(false)
  })

  it('shows the API\'s "not eligible" answer as a form error', async () => {
    eligible = true
    refuseSubmit = { status: 400, body: { status: false, message: 'You are not eligible to review this product.', data: null, error: 'ReviewException' } }
    const page = await open(BUYER)

    await rate(page, 5)
    await submit(page, () => page.text().includes('not eligible'))

    expect(page.text()).toContain('You are not eligible to review this product.')
  })

  it('asks the user to log in again when the session expired mid-page', async () => {
    eligible = true
    const page = await open(BUYER)
    refuseSubmit = { status: 401, body: { message: 'Unauthenticated.' } }

    await rate(page, 5)
    await submit(page, () => page.text().includes('session has expired'))

    expect(page.text()).toContain('Your session has expired.')
    expect(page.find('a[href^="/login?redirect="]').exists()).toBe(true)
  })

  it('gives an admin a Hide button that takes the review off the list and out of the summary', async () => {
    const page = await open(ADMIN)
    expect(page.find('form').exists()).toBe(false)
    expect(page.text()).not.toContain('You can review this product')

    const hideButtons = page.findAll('button').filter(b => b.text() === 'Hide')
    expect(hideButtons).toHaveLength(2)

    // The first listed is review 2 (3 stars).
    await hideButtons[0]!.trigger('click')
    await settle(() => page.text().includes('★ 5.0 from 1 review'))

    expect(hidden).toEqual([2])
    expect(reviewTexts(page)).toEqual(['Review 1'])
    expect(summary(page)).toBe('★ 5.0 from 1 review')
  })

  it('shows no Hide button to a Buyer', async () => {
    const page = await open(BUYER)

    expect(page.findAll('button').filter(b => b.text() === 'Hide')).toHaveLength(0)
  })
})
