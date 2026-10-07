import { expect, test } from '@playwright/test'
import { fakeApi } from './support/fakeApi'

test('logs in, primes CSRF once, and returns to the redirect target', async ({ page, context }) => {
  const api = await fakeApi(page, context)

  await page.goto('/login?redirect=/forgot-password')
  await page.getByLabel('Email').fill('ada@example.com')
  await page.getByLabel('Password').fill('secret123')
  await page.getByRole('button', { name: 'Log in' }).click()

  // /forgot-password is guest-only, so a logged-in user bounces on to /.
  await expect(page).toHaveURL('/')
  await expect(page.getByText('Ada Buyer')).toBeVisible()
  expect(api.state.csrfPrimed).toBe(1)
  expect(api.state.tokensSeen).toEqual(['fake-xsrf-token'])
})

test('shows invalid credentials inline on the email field', async ({ page, context }) => {
  await fakeApi(page, context)

  await page.goto('/login')
  await page.getByLabel('Email').fill('ada@example.com')
  await page.getByLabel('Password').fill('wrong-password')
  await page.getByRole('button', { name: 'Log in' }).click()

  await expect(page.getByText('These credentials do not match our records.')).toBeVisible()
  await expect(page).toHaveURL('/login')
})

test('registers a new account and ends up logged in', async ({ page, context }) => {
  await fakeApi(page, context)

  await page.goto('/register')
  await page.getByLabel('Name').fill('New Buyer')
  await page.getByLabel('Email').fill('new@example.com')
  await page.getByLabel('Password', { exact: true }).fill('password123')
  await page.getByLabel('Confirm password').fill('password123')
  await page.getByRole('button', { name: 'Register' }).click()

  await expect(page).toHaveURL('/')
  await expect(page.getByText('New Buyer')).toBeVisible()
})

test('shows a taken email inline when registering', async ({ page, context }) => {
  await fakeApi(page, context)

  await page.goto('/register')
  await page.getByLabel('Name').fill('Ada Again')
  await page.getByLabel('Email').fill('ada@example.com')
  await page.getByLabel('Password', { exact: true }).fill('password123')
  await page.getByLabel('Confirm password').fill('password123')
  await page.getByRole('button', { name: 'Register' }).click()

  await expect(page.getByText('The email has already been taken.')).toBeVisible()
})

test('logs out from the header', async ({ page, context }) => {
  const api = await fakeApi(page, context)
  api.logIn()

  await page.goto('/')
  await page.getByRole('button', { name: 'Log out' }).click()

  await expect(page.getByRole('link', { name: 'Log in' })).toBeVisible()
  expect(api.state.session).toBeNull()
})

test('keeps a logged-in user away from the login page', async ({ page, context }) => {
  const api = await fakeApi(page, context)
  api.logIn()

  await page.goto('/login')

  await expect(page).toHaveURL('/')
})

test('requests a password reset link', async ({ page, context }) => {
  await fakeApi(page, context)

  await page.goto('/forgot-password')
  await page.getByLabel('Email').fill('ada@example.com')
  await page.getByRole('button', { name: 'Send reset link' }).click()

  await expect(page.getByText('We have emailed your password reset link.')).toBeVisible()
})

test('resets the password from the emailed link, then asks the user to log in', async ({ page, context }) => {
  const api = await fakeApi(page, context)

  await page.goto('/reset-password?token=valid-token&email=ada%40example.com')
  await expect(page.getByLabel('Email')).toHaveValue('ada@example.com')
  await page.getByLabel('New password', { exact: true }).fill('new-password123')
  await page.getByLabel('Confirm new password').fill('new-password123')
  await page.getByRole('button', { name: 'Reset password' }).click()

  await expect(page).toHaveURL('/login?reset=1')
  await expect(page.getByText('Your password has been reset.')).toBeVisible()
  expect(api.state.resetRequests[0]).toMatchObject({ token: 'valid-token', email: 'ada@example.com' })
})

test('shows an invalid reset token as a form-level error', async ({ page, context }) => {
  await fakeApi(page, context)

  await page.goto('/reset-password?token=expired&email=ada%40example.com')
  await page.getByLabel('New password', { exact: true }).fill('new-password123')
  await page.getByLabel('Confirm new password').fill('new-password123')
  await page.getByRole('button', { name: 'Reset password' }).click()

  await expect(page.getByText('This password reset token is invalid.')).toBeVisible()
})
