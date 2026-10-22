import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody, setResponseStatus } from 'h3'
import ProfilePage from '~/pages/profile/index.vue'
import PasswordPage from '~/pages/profile/password.vue'

// Toasts render in the app's UApp, which mountSuspended doesn't mount.
const { toastAdd } = vi.hoisted(() => ({ toastAdd: vi.fn() }))
mockNuxtImport('useToast', () => () => ({ add: toastAdd }))

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>

let userName: string
let userFetches: number
let profileUpdates: Record<string, unknown>[]
let passwordChanges: Record<string, unknown>[]
// Laravel's 422 `errors` for the next PATCH, if any.
let profileErrors: Record<string, string[]> | null
let passwordErrors: Record<string, string[]> | null

beforeEach(() => {
  userName = 'Sam'
  userFetches = 0
  profileUpdates = []
  passwordChanges = []
  profileErrors = null
  passwordErrors = null
  toastAdd.mockClear()
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()

  registerEndpoint('/api/user', () => {
    userFetches++
    return { data: { id: 5, name: userName, email: 'sam@example.com', status: 'user.status.active', roles: ['role.buyer'], email_verified_at: null, created_at: null } }
  })
  registerEndpoint('/api/profile', {
    method: 'GET',
    handler: () => ({ data: { name: 'Sam', email: 'sam@example.com' } }),
  })
  registerEndpoint('/api/profile', {
    method: 'PATCH',
    handler: async (event) => {
      const body = await readBody(event)
      profileUpdates.push(body)
      if (profileErrors) {
        setResponseStatus(event, 422)
        return { message: 'The given data was invalid.', errors: profileErrors }
      }
      userName = body.name
      return { data: { name: body.name, email: body.email } }
    },
  })
  registerEndpoint('/api/profile/password', {
    method: 'PATCH',
    handler: async (event) => {
      passwordChanges.push(await readBody(event))
      if (passwordErrors) {
        setResponseStatus(event, 422)
        return { message: 'The given data was invalid.', errors: passwordErrors }
      }
      setResponseStatus(event, 204)
      return null
    },
  })
})

function field(page: Wrapper, label: string) {
  const match = page.findAll('label').find(candidate => candidate.text().startsWith(label))
  if (!match) {
    throw new Error(`No field labelled ${label}`)
  }
  return {
    input: page.find<HTMLInputElement>(`[id="${match.attributes('for')}"]`),
    text: () => match.element.closest('[data-slot="root"]')?.textContent ?? '',
  }
}

async function submit(page: Wrapper) {
  await page.find('form').trigger('submit')
  await flushPromises()
  await flushPromises()
}

describe('profile page', () => {
  it('pre-fills the form from GET /profile and links the account pages', async () => {
    const page = await mountSuspended(ProfilePage, { route: '/profile' })

    expect(field(page, 'Name').input.element.value).toBe('Sam')
    expect(field(page, 'Email').input.element.value).toBe('sam@example.com')
    expect(page.find('a[href="/profile/password"]').exists()).toBe(true)
  })

  it('saves the profile and refreshes the logged-in User, so the header shows the new name', async () => {
    const page = await mountSuspended(ProfilePage, { route: '/profile' })
    const fetchesBefore = userFetches

    await field(page, 'Name').input.setValue('Samantha')
    await submit(page)

    expect(profileUpdates).toEqual([{ name: 'Samantha', email: 'sam@example.com' }])
    await vi.waitFor(() => expect(useAuthStore().user?.name).toBe('Samantha'))
    expect(userFetches).toBe(fetchesBefore + 1)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Your profile was saved.', color: 'success' }))
  })

  it('shows an email another account uses next to the email field', async () => {
    profileErrors = { email: ['This email address is already in use by another account.'] }
    const page = await mountSuspended(ProfilePage, { route: '/profile' })
    const fetchesBefore = userFetches

    await field(page, 'Email').input.setValue('taken@example.com')
    await submit(page)

    expect(profileUpdates).toHaveLength(1)
    expect(field(page, 'Email').text()).toContain('This email address is already in use by another account.')
    expect(toastAdd).not.toHaveBeenCalled()
    expect(userFetches).toBe(fetchesBefore)
  })

  it('requires a name and an email before calling the API', async () => {
    const page = await mountSuspended(ProfilePage, { route: '/profile' })

    await field(page, 'Name').input.setValue('')
    await field(page, 'Email').input.setValue('')
    await submit(page)

    expect(profileUpdates).toHaveLength(0)
    expect(page.text()).toContain('Enter your name.')
    expect(page.text()).toContain('Enter your email.')
  })
})

describe('change password page', () => {
  async function fill(page: Wrapper, current: string, next: string, confirmation: string) {
    await field(page, 'Current password').input.setValue(current)
    await field(page, 'New password').input.setValue(next)
    await field(page, 'Confirm new password').input.setValue(confirmation)
  }

  it('checks that the new passwords match before calling the API', async () => {
    const page = await mountSuspended(PasswordPage, { route: '/profile/password' })

    await fill(page, 'old-password', 'new-password', 'different')
    await submit(page)

    expect(passwordChanges).toHaveLength(0)
    expect(field(page, 'Confirm new password').text()).toContain('The passwords don\'t match.')
  })

  it('shows a wrong current password next to that field', async () => {
    passwordErrors = { currentPassword: ['The current password you entered is incorrect.'] }
    const page = await mountSuspended(PasswordPage, { route: '/profile/password' })

    await fill(page, 'wrong-password', 'new-password', 'new-password')
    await submit(page)

    expect(passwordChanges).toHaveLength(1)
    expect(field(page, 'Current password').text()).toContain('The current password you entered is incorrect.')
    expect(toastAdd).not.toHaveBeenCalled()
  })

  it('changes the password, then clears the form', async () => {
    const page = await mountSuspended(PasswordPage, { route: '/profile/password' })

    await fill(page, 'old-password', 'new-password', 'new-password')
    await submit(page)

    expect(passwordChanges).toEqual([{ currentPassword: 'old-password', newPassword: 'new-password', newPasswordConfirmation: 'new-password' }])
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Your password was changed.', color: 'success' }))
    expect(field(page, 'Current password').input.element.value).toBe('')
    expect(field(page, 'New password').input.element.value).toBe('')
  })
})
