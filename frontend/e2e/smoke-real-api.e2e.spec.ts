import { test, expect } from '@playwright/test';

test('real stack smoke: login and open products page', async ({ page }) => {
  test.skip(process.env.PW_DOCKER !== '1', 'Runs only against docker stack with real backend API');

  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Вход' })).toBeVisible();

  await page.getByPlaceholder('Электронная почта').fill('demo@example.com');
  await page.getByPlaceholder('Пароль').fill('demo-password');
  await page.getByRole('button', { name: 'Войти' }).click();

  await expect(page).toHaveURL(/\/import$/);
  await page.goto('/products');
  await expect(page.getByRole('heading', { name: 'Товары' })).toBeVisible();
});
