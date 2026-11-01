import { afterEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { defineComponent, h, ref } from 'vue'
import { useCatalogFetch } from '~/composables/useCatalogFetch'

const NAMES: Record<string, string> = { en: 'Smart TV', el: 'Τηλεόραση', tr: 'Televizyon' }

registerEndpoint('/api/catalogue-locale-thing', (event: { headers: Headers, path: string }) => {
  const locale = event.headers.get('accept-language') ?? 'en'
  const page = new URL(event.path, 'http://test').searchParams.get('page') ?? '1'
  return { name: NAMES[locale], page }
})

afterEach(async () => {
  await useNuxtApp().$i18n.setLocale('en')
})

function mountFetcher(page = ref('1')) {
  return mountSuspended(defineComponent({
    async setup() {
      const { data } = await useCatalogFetch<{ name: string, page: string }>('/catalogue-locale-thing', { query: { page } })
      return () => h('p', `${data.value?.name} ${data.value?.page}`)
    },
  }))
}

describe('useCatalogFetch', () => {
  it("fetches the new language's content after switching, instead of reusing the last one", async () => {
    const wrapper = await mountFetcher()
    expect(wrapper.text()).toBe('Smart TV 1')

    await useNuxtApp().$i18n.setLocale('tr')
    await vi.waitFor(() => expect(wrapper.text()).toBe('Televizyon 1'))

    await useNuxtApp().$i18n.setLocale('el')
    await vi.waitFor(() => expect(wrapper.text()).toBe('Τηλεόραση 1'))
  })


  it('still refetches when the query changes', async () => {
    const page = ref('1')
    const wrapper = await mountFetcher(page)

    page.value = '2'
    await vi.waitFor(() => expect(wrapper.text()).toBe('Smart TV 2'))
  })
})
