import { expect, test } from '@playwright/test'

test('home page renders inside the default layout', async ({ page }) => {
  // No backend in e2e: the header's session check gets a 401.
  await page.route('http://localhost:8000/**', route => route.fulfill({
    status: 401,
    headers: { 'Access-Control-Allow-Origin': 'http://localhost:3000', 'Access-Control-Allow-Credentials': 'true' },
    json: { message: 'Unauthenticated.' },
  }))

  await page.goto('/')

  await expect(page.getByRole('heading', { name: 'Welcome to ecomshop' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Log in' })).toBeVisible()
})
