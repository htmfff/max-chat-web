import { Page, expect, test } from '@playwright/test';
import path from 'node:path';

const demoEmail = 'admin@example.com';
const demoPassword = 'admin123';
const sampleFile = path.resolve(__dirname, '../../samples/import_example.xlsx');

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await expect(page.getByText('Вход в каталог')).toBeVisible();

  await page.getByLabel('Email').fill(demoEmail);
  await page.getByLabel('Пароль').fill(demoPassword);
  await page.getByRole('button', { name: 'Войти' }).click();

  await expect(page).toHaveURL(/\/products$/);
  await expect(page.getByRole('heading', { name: 'Товары' })).toBeVisible();
}

test.describe('Product catalog', () => {
  test('rejects an invalid password', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(demoEmail);
    await page.getByLabel('Пароль').fill('wrong-password');
    await page.getByRole('button', { name: 'Войти' }).click();

    await expect(page.locator('.error-text')).toContainText('Неверный');
  });

  test('logs in and shows the products page', async ({ page }) => {
    await login(page);

    await expect(page.getByLabel('Поиск по названию')).toBeVisible();
    await expect(page.getByLabel('Цена от')).toBeVisible();
    await expect(page.getByLabel('Цена до')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Импорт .xlsx' })).toBeVisible();
  });

  test('imports the sample xlsx and opens a product card', async ({ page }) => {
    await login(page);

    await page.getByRole('link', { name: 'Импорт .xlsx' }).click();
    await expect(page.getByRole('heading', { name: 'Импорт товаров' })).toBeVisible();

    await page.locator('input[type="file"]').setInputFiles(sampleFile);
    await expect(page.getByText('import_example.xlsx')).toBeVisible();

    await page.getByRole('button', { name: 'Загрузить и импортировать' }).click();

    await expect(page.getByText(/Задача #\d+/)).toBeVisible();
    await expect(page.getByText('Импорт завершён. Товары доступны в каталоге.')).toBeVisible({
      timeout: 120_000,
    });

    await page.getByRole('link', { name: 'Открыть список товаров' }).click();
    await expect(page).toHaveURL(/\/products$/);

    const firstCardLink = page.getByRole('link', { name: 'Карточка' }).first();
    await expect(firstCardLink).toBeVisible();
    await firstCardLink.click();

    await expect(page.getByText('Дополнительные атрибуты')).toBeVisible();
    await expect(page.getByText('Изображения')).toBeVisible();
    await expect(page.getByRole('link', { name: '← Назад к списку' })).toBeVisible();
  });
});
