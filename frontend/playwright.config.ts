import { defineConfig, devices } from '@playwright/test'

const ci = !!process.env.CI

export default defineConfig({
  testDir: 'tests/e2e',
  forbidOnly: ci,
  // CI retries a failure twice and records a trace of the first retry, so a
  // flaky run still passes and a real failure comes with something to open.
  retries: ci ? 2 : 0,
  reporter: ci ? [['github'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: 'http://localhost:3000',
    trace: ci ? 'on-first-retry' : 'off',
  },
  webServer: {
    // The production build, as Hostinger serves it (ADR-0001). The dev server
    // optimizes deps on first request and can break the first page load.
    command: 'npm run build && node .output/server/index.mjs',
    url: 'http://localhost:3000',
    reuseExistingServer: !ci,
    timeout: 300_000,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
})
