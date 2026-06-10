// @ts-check
const { expect } = require('@playwright/test');

const USER = process.env.TEST_USER || '';
const PASS = process.env.TEST_PASS || '';

/**
 * Faz login pelo formulário e espera cair no painel.
 * @param {import('@playwright/test').Page} page
 */
async function login(page) {
  await page.goto('login.php');
  await page.fill('input[name="email"]', USER);
  await page.fill('input[name="senha"]', PASS);
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/index\.php|diag-seo\/?$/, { timeout: 15000 });
}

module.exports = { login, USER, PASS };
