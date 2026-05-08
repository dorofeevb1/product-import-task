import { defineConfig, devices } from '@playwright/test';

const useDockerTarget = process.env.PW_DOCKER === '1';

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:4200',
    trace: 'on-first-retry',
  },
  webServer: useDockerTarget
    ? undefined
    : {
        command: 'npm run start',
        url: 'http://127.0.0.1:4200',
        reuseExistingServer: true,
        timeout: 120_000,
      },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
