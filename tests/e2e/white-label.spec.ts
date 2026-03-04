import { test, expect } from '@playwright/test';
import { login } from './helpers/auth';

test.describe('White Label Settings', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('can fetch white-label settings via API', async ({ page }) => {
    const response = await page.request.get('/admin/api/white-label');
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data).toBeTruthy();
    expect(json.data.app_name).toBeTruthy();
    expect(json.data.primary_color).toBeTruthy();
  });

  test('can update app name via API', async ({ page }) => {
    const response = await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        app_name: 'Test CRM Brand',
      },
    });
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.app_name).toBe('Test CRM Brand');

    // Reset back
    await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: { app_name: 'CRM' },
    });
  });

  test('can update brand colors via API', async ({ page }) => {
    const response = await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        primary_color: '#FF5733',
        secondary_color: '#33FF57',
        accent_color: '#3357FF',
      },
    });
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.primary_color).toBe('#FF5733');
    expect(json.data.secondary_color).toBe('#33FF57');
    expect(json.data.accent_color).toBe('#3357FF');

    // Reset
    await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        primary_color: '#1E40AF',
        secondary_color: '#7C3AED',
        accent_color: '#F59E0B',
      },
    });
  });

  test('dynamic CSS endpoint returns custom properties', async ({ page }) => {
    const response = await page.request.get('/white-label/css');
    expect(response.ok()).toBeTruthy();
    const css = await response.text();
    expect(css).toContain('--wl-primary-color');
    expect(css).toContain('--wl-secondary-color');
    expect(css).toContain('--wl-accent-color');
  });

  test('rejects invalid color format', async ({ page }) => {
    const response = await page.request.post('/admin/api/white-label', {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      data: {
        primary_color: 'not-a-color-this-is-way-too-long',
      },
    });
    // Should fail validation (max:7)
    expect(response.status()).toBe(422);
  });
});
