import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { useApi } from '~/composables/useApi'

// vitest.config.ts sets apiBase to '', so these relative paths reach registerEndpoint.

const XSRF = 'abc/123=='
let csrfPrimed = 0
let seenToken: string | null | undefined

function clearXsrfCookie() {
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
}

beforeEach(() => {
  csrfPrimed = 0
  seenToken = undefined
  clearXsrfCookie()

  registerEndpoint('/sanctum/csrf-cookie', () => {
    csrfPrimed++
    // Laravel sets this via Set-Cookie (URL-encoded); the fake fetch can't, so set it directly.
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(XSRF)}; path=/`
    return null
  })

  const echoToken = (event: { headers: Headers }) => {
    seenToken = event.headers.get('x-xsrf-token')
    return { ok: true }
  }
  registerEndpoint('/api/thing', { method: 'GET', handler: echoToken })
  registerEndpoint('/api/thing', { method: 'POST', handler: echoToken })
})

afterEach(clearXsrfCookie)

describe('useApi', () => {
  it('sends GET requests to /api without priming the CSRF cookie', async () => {
    await expect(useApi()('/thing')).resolves.toEqual({ ok: true })

    expect(csrfPrimed).toBe(0)
    expect(seenToken).toBeNull()
  })

  it('primes the CSRF cookie before the first state-changing request and echoes it decoded', async () => {
    await useApi()('/thing', { method: 'POST', body: {} })

    expect(csrfPrimed).toBe(1)
    expect(seenToken).toBe(XSRF)
  })

  it('reuses an existing CSRF cookie instead of priming again', async () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(XSRF)}; path=/`

    await useApi()('/thing', { method: 'POST', body: {} })

    expect(csrfPrimed).toBe(0)
    expect(seenToken).toBe(XSRF)
  })
})
