import { test, expect } from '@playwright/test';

test('guest navigation and register flow', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.clear();
  });
  await page.goto('/login');
  await expect(page.locator('app-root')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Создать аккаунт' })).toBeVisible();

  await page.goto('/register');
  await page.getByPlaceholder('Имя').fill('QA User');
  await page.getByPlaceholder('Электронная почта').fill('qa-user@example.com');
  await page.getByPlaceholder('Пароль (минимум 6 символов)').fill('strong123');
  await page.getByPlaceholder('Подтвердите пароль').fill('strong123');
  await page.getByRole('button', { name: 'Создать аккаунт' }).click();

  await expect(page).toHaveURL(/\/import$/);
  await expect(page.getByRole('button', { name: 'Выйти' })).toBeVisible();
});

test('import flow and product pages with mocked api', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('access_token', 'demo-jwt-token');
    localStorage.setItem('refresh_token', 'demo-refresh-token');
    localStorage.setItem('last_import_task_id', 'task-1');
  });

  await page.route('**/api/products?page=1&limit=20&name=&priceMin=&priceMax=', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        items: [{ id: 1, externalCode: 'SKU-1', name: 'Chair', description: 'Desc', price: 120, discount: 20 }],
        page: 1,
        limit: 20,
        total: 1,
        totalPages: 1,
      }),
    });
  });

  await page.route('**/api/products?*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        items: [{ id: 1, externalCode: 'SKU-1', name: 'Chair', description: 'Desc', price: 120, discount: 20 }],
        page: 1,
        limit: 20,
        total: 1,
        totalPages: 1,
      }),
    });
  });

  await page.route('**/api/products/1', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        id: 1,
        externalCode: 'SKU-1',
        name: 'Chair',
        description: 'Desc',
        price: 120,
        discount: 20,
        attributes: [{ key: 'Color', value: 'Black' }],
        images: [{ url: 'https://example.com/i.jpg', path: 'storage/i.jpg' }],
      }),
    });
  });

  await page.goto('/products');
  await expect(page.getByText('Chair')).toBeVisible();
  await page.getByRole('link', { name: 'Chair' }).click();

  await expect(page).toHaveURL(/\/products\/1$/);
  await expect(page.getByText('Атрибуты')).toBeVisible();
  await expect(page.getByText('Color: Black')).toBeVisible();
});
