import { afterEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { formatDate, formatDateTime } from '~/utils/formatDate'
import { formatPrice } from '~/utils/formatPrice'
import { currentLanguage, currentLocale, routeLocale } from '~/utils/locale'
import { useCatalogApi } from '~/composables/useCatalogFetch'

afterEach(async () => {
  await useNuxtApp().$i18n.setLocale('en')
})

describe('formatting in the page language', () => {
  it('formats prices in EUR for each language', () => {
    expect(formatPrice(1234.5, 'en-IE')).toBe('€1,234.50')
    // Greek puts a non-breaking space before the euro sign.
    expect(formatPrice(1234.5, 'el-GR')).toBe('1.234,50\u00A0€')
    expect(formatPrice(1234.5, 'tr-TR')).toBe('€1.234,50')
  })

  it('formats dates in UTC for each language', () => {
    const value = '2026-03-05T23:30:00Z'

    expect(formatDate(value, 'en-IE')).toBe('5 Mar 2026')
    expect(formatDate(value, 'el-GR')).toBe('5 Μαρ 2026')
    expect(formatDate(value, 'tr-TR')).toBe('5 Mar 2026')
    expect(formatDateTime(value, 'el-GR')).toContain('11:30')
    expect(formatDate(null)).toBe('')
  })

  it('uses the active locale when none is given', async () => {
    expect(currentLanguage()).toBe('en-IE')
    expect(formatPrice(10)).toBe('€10.00')

    await useNuxtApp().$i18n.setLocale('el')

    expect(currentLocale()).toBe('el')
    expect(currentLanguage()).toBe('el-GR')
    expect(formatPrice(10)).toBe('10,00\u00A0€')
  })
})

describe('routeLocale', () => {
  it('reads the locale from a localised route name', () => {
    expect(routeLocale({ name: 'products-slug___tr' })).toBe('tr')
    expect(routeLocale({ name: 'index___en' })).toBe('en')
  })

  it('falls back to English for a route without one', () => {
    expect(routeLocale({ name: undefined })).toBe('en')
    expect(routeLocale({ name: 'unnamed' })).toBe('en')
  })
})

describe('useCatalogApi', () => {
  it("asks for the page's language without sending credentials", async () => {
    let seen: string | null = null
    registerEndpoint('/api/catalogue-thing', (event: { headers: Headers }) => {
      seen = event.headers.get('accept-language')
      return {}
    })

    await useNuxtApp().$i18n.setLocale('tr')
    await useCatalogApi()('/catalogue-thing')

    expect(seen).toBe('tr')
  })
})
