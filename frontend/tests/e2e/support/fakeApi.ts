import type { BrowserContext, Page, Route } from '@playwright/test'

const API = 'http://localhost:8000'
const FRONTEND = 'http://localhost:3000'
const XSRF = 'fake-xsrf-token'

interface FakeUser {
  id: number
  name: string
  email: string
  password: string
}

/**
 * Stands in for the Laravel API so the auth flows run without a backend. It
 * mimics Sanctum: requests must carry the CSRF token from the primed cookie,
 * and responses carry the CORS headers a credentialed request needs.
 */
export async function fakeApi(page: Page, context: BrowserContext) {
  const users: FakeUser[] = [{ id: 1, name: 'Ada Buyer', email: 'ada@example.com', password: 'secret123' }]
  const state = {
    session: null as FakeUser | null,
    csrfPrimed: 0,
    tokensSeen: [] as (string | null)[],
    resetRequests: [] as Record<string, string>[],
  }

  const publicUser = ({ password: _password, ...user }: FakeUser) => ({ ...user, status: 'active', roles: ['ROLE_BUYER'] })

  async function reply(route: Route, status: number, body?: unknown) {
    await route.fulfill({
      status,
      contentType: 'application/json',
      headers: {
        'Access-Control-Allow-Origin': FRONTEND,
        'Access-Control-Allow-Credentials': 'true',
        'Access-Control-Allow-Headers': 'accept, content-type, x-xsrf-token',
        'Access-Control-Allow-Methods': 'GET, POST, PATCH, DELETE',
      },
      body: body === undefined ? '' : JSON.stringify(body),
    })
  }

  const invalid = (field: string, message: string) => ({ message, errors: { [field]: [message] } })

  await page.route(`${API}/**`, async (route) => {
    const request = route.request()
    const method = request.method()
    const path = new URL(request.url()).pathname

    if (method === 'OPTIONS') return reply(route, 204)

    if (path === '/sanctum/csrf-cookie') {
      state.csrfPrimed++
      await context.addCookies([{ name: 'XSRF-TOKEN', value: XSRF, domain: 'localhost', path: '/' }])
      return reply(route, 204)
    }

    if (method !== 'GET') {
      const token = request.headers()['x-xsrf-token'] ?? null
      state.tokensSeen.push(token)
      if (token !== XSRF) return reply(route, 419, { message: 'CSRF token mismatch.' })
    }

    const body = method === 'GET' ? {} : request.postDataJSON() ?? {}

    switch (`${method} ${path}`) {
      case 'GET /api/user':
        return state.session ? reply(route, 200, { data: publicUser(state.session) }) : reply(route, 401, { message: 'Unauthenticated.' })

      case 'POST /api/login': {
        const user = users.find(u => u.email === body.email && u.password === body.password)
        if (!user) return reply(route, 422, invalid('email', 'These credentials do not match our records.'))
        state.session = user
        return reply(route, 200, { data: publicUser(user) })
      }

      case 'POST /api/register': {
        if (users.some(u => u.email === body.email)) return reply(route, 422, invalid('email', 'The email has already been taken.'))
        const user = { id: users.length + 1, name: body.name, email: body.email, password: body.password }
        users.push(user)
        state.session = user
        return reply(route, 201, { data: publicUser(user) })
      }

      case 'POST /api/logout':
        state.session = null
        return reply(route, 204)

      case 'POST /api/forgot-password':
        return reply(route, 200, { message: 'We have emailed your password reset link.' })

      case 'POST /api/reset-password':
        state.resetRequests.push(body)
        if (body.token !== 'valid-token') return reply(route, 422, { message: 'This password reset token is invalid.' })
        return reply(route, 200, { message: 'Your password has been reset.' })

      default:
        return reply(route, 404, { message: 'Resource not found' })
    }
  })

  return {
    state,
    logIn(email = 'ada@example.com') {
      state.session = users.find(u => u.email === email) ?? null
    },
  }
}
