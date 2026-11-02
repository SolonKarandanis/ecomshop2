import { expect, test } from '@playwright/test'

// No backend in e2e, and page.route() only reaches the browser's requests, not
// the Nuxt server's: a catalogue page like /products can't render here, since
// its server-side read fails. /login renders without the API, so the language
// behaviour is checked there. The browser's own session check gets a 401.
test.beforeEach(async ({ page }) => {
  await page.route('http://localhost:8000/**', route => route.fulfill({
    status: 401,
    headers: { 'Access-Control-Allow-Origin': 'http://localhost:3000', 'Access-Control-Allow-Credentials': 'true' },
    json: { message: 'Unauthenticated.' },
  }))
})

test('the language switcher keeps the page and moves to its prefixed URL', async ({ page }) => {
  await page.goto('/el/login')
  await expect(page.locator('html')).toHaveAttribute('lang', 'el-GR')
  // The page is server-rendered: wait for hydration before using the switcher.
  await page.waitForLoadState('networkidle')

  // The switcher's own label is translated too: "Language" in Greek.
  await page.getByRole('combobox', { name: 'Γλώσσα' }).click()
  await page.getByRole('option', { name: 'Türkçe' }).click()

  await expect(page).toHaveURL(/\/tr\/login$/)
  await expect(page.locator('html')).toHaveAttribute('lang', 'tr-TR')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Giriş yap')

  // Links on the Turkish page stay in Turkish.
  await page.getByRole('link', { name: 'ecomshop' }).click()
  await expect(page).toHaveURL(/\/tr$/)
})

test('English pages have no prefix and point to their translations', async ({ page }) => {
  await page.goto('/login')

  await expect(page.locator('html')).toHaveAttribute('lang', 'en-IE')
  await expect(page.locator('link[hreflang="el"]')).toHaveAttribute('href', /\/el\/login$/)
  await expect(page.locator('link[hreflang="tr"]')).toHaveAttribute('href', /\/tr\/login$/)
})
