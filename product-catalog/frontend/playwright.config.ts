import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests run against an already started stack (docker compose up -d).
 * Override the target with E2E_BASE_URL, for example:
 *   E2E_BASE_URL=http://localhost:8080 npx playwright test
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env['CI'],
  retries: process.env['CI'] ? 2 : 0,
  workers: process.env['CI'] ? 1 : undefined,
  reporter: process.env['CI'] ? [['list'], ['html', { open: 'never' }]] : 'list',
  timeout: 120_000,
  expect: {
    timeout: 15_000,
  },
  use: {
    baseURL: process.env['E2E_BASE_URL'] ?? 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
