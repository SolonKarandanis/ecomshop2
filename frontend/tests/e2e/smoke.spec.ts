import { expect, test } from '@playwright/test'

test('home page renders the Nuxt UI scaffold', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { name: 'ecomshop' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Nuxt UI is wired up' })).toBeVisible()
})
