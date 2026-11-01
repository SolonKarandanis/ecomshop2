import { expect, test } from '@playwright/test'

test('the language switcher keeps the page and moves to its prefixed URL', async ({ page }) => {
  await page.goto('/el/products')
  await expect(page.locator('html')).toHaveAttribute('lang', 'el-GR')
  // The page is server-rendered: wait for hydration before using the switcher.
  await page.waitForLoadState('networkidle')

  // The switcher's own label is translated too: "Language" in Greek.
  await page.getByRole('combobox', { name: 'Γλώσσα' }).click()
  await page.getByRole('option', { name: 'Türkçe' }).click()

  await expect(page).toHaveURL(/\/tr\/products$/)
  await expect(page.locator('html')).toHaveAttribute('lang', 'tr-TR')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Ürünler')

  // Links on the Turkish page stay in Turkish.
  await page.getByRole('link', { name: 'ecomshop' }).click()
  await expect(page).toHaveURL(/\/tr$/)
})

test('English pages have no prefix and point to their translations', async ({ page }) => {
  await page.goto('/products')

  await expect(page.locator('html')).toHaveAttribute('lang', 'en-IE')
  await expect(page.locator('link[hreflang="el"]')).toHaveAttribute('href', /\/el\/products$/)
  await expect(page.locator('link[hreflang="tr"]')).toHaveAttribute('href', /\/tr\/products$/)
})
