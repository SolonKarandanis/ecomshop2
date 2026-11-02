import type { BrowserContext, Page, Route } from '@playwright/test'

const API = 'http://localhost:8000'
const FRONTEND = 'http://localhost:3000'
const XSRF = 'fake-xsrf-token'

// The values GET /api/user really sends (RolesEnum, UserStatusEnum), so
// role-based UI is tested against what production renders it from.
const ROLE_ADMIN = 'role.admin'
const ROLE_BUYER = 'role.buyer'
const STATUS_ACTIVE = 'user.status.active'

export interface FakeNotification {
  id: string
  event_type: string
  order_id: number | null
  url: string | null
  order_url: string | null
  message: string
  read_at: string | null
  created_at: string
}

interface FakeUser {
  id: number
  name: string
  email: string
  password: string
  roles: string[]
}

/**
 * Stands in for the Laravel API so the auth flows run without a backend. It
 * mimics Sanctum: requests must carry the CSRF token from the primed cookie,
 * and responses carry the CORS headers a credentialed request needs.
 */
export async function fakeApi(page: Page, context: BrowserContext) {
  const users: FakeUser[] = [
    { id: 1, name: 'Ada Buyer', email: 'ada@example.com', password: 'secret123', roles: [ROLE_BUYER] },
    { id: 2, name: 'Grace Admin', email: 'grace@example.com', password: 'secret123', roles: [ROLE_ADMIN] },
  ]
  const state = {
    session: null as FakeUser | null,
    csrfPrimed: 0,
    tokensSeen: [] as (string | null)[],
    resetRequests: [] as Record<string, string>[],
    // The logged-in user's Notifications, newest first (NotificationData).
    notifications: [] as FakeNotification[],
  }
  const unreadCount = () => state.notifications.filter(n => !n.read_at).length
  const readNow = (n: FakeNotification) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })

  // UserData's shape: everything but the password, plus what the model adds.
  const publicUser = ({ password: _password, ...user }: FakeUser) => ({
    ...user,
    locale: 'en',
    status: STATUS_ACTIVE,
    email_verified_at: null,
    created_at: '2026-10-01T00:00:00.000000Z',
  })

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
        const user = { id: users.length + 1, name: body.name, email: body.email, password: body.password, roles: [ROLE_BUYER] }
        users.push(user)
        state.session = user
        return reply(route, 201, { data: publicUser(user) })
      }

      // Read by /register to decide whether to offer the Supplier role.
      case 'GET /api/config':
        return reply(route, 200, { data: { suppliers_enabled: false, max_product_images: 5, max_product_image_kb: 5120 } })

      // Loaded on every page for the header badge (plugins/cart.client.ts).
      case 'GET /api/cart':
        return reply(route, 200, { data: { total_price: 0, items: [], notice: null } })

      case 'GET /api/notifications':
        return reply(route, 200, { data: state.notifications, meta: { current_page: 1, last_page: 1, per_page: 20, total: state.notifications.length } })

      case 'GET /api/notifications/unread':
        return reply(route, 200, { data: state.notifications.filter(n => !n.read_at).slice(0, 5) })

      case 'GET /api/notifications/unread-count':
        return reply(route, 200, { count: unreadCount() })

      case 'POST /api/notifications/mark-read':
        state.notifications = state.notifications.map(n => body.ids.includes(n.id) ? readNow(n) : n)
        return reply(route, 200, { count: unreadCount() })

      case 'POST /api/notifications/mark-all-read':
        state.notifications = state.notifications.map(readNow)
        return reply(route, 200, { count: 0 })

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
