import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: 'tests/e2e',
  use: {
    baseURL: 'http://localhost:3000',
  },
  webServer: {
    // The production build, as Hostinger serves it (ADR-0001). The dev server
    // optimizes deps on first request and can break the first page load.
    command: 'npm run build && node .output/server/index.mjs',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
    timeout: 300_000,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
})
