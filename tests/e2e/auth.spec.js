// @ts-check
const { test, expect } = require('@playwright/test');
const { login, USER } = require('./helpers');

test.describe('Autenticação', () => {

  test('página de login carrega com formulário completo', async ({ page }) => {
    await page.goto('login.php');
    await expect(page).toHaveTitle(/Entrar/);
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="senha"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('senha incorreta mostra erro e permanece no login', async ({ page }) => {
    await page.goto('login.php');
    await page.fill('input[name="email"]', USER);
    await page.fill('input[name="senha"]', 'senha-errada-' + Date.now());
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/login\.php/);
    // Mensagem genérica de credencial inválida (ou bloqueio por rate limit)
    await expect(page.locator('body')).toContainText(/incorretos|tentativas/i);
  });

  test('credenciais válidas autenticam e redirecionam ao painel', async ({ page }) => {
    await login(page);
    await expect(page.locator('body')).toContainText(/diagnóstico|relatório/i);
  });

  test('rota protegida sem sessão redireciona ao login', async ({ page }) => {
    await page.goto('index.php');
    await expect(page).toHaveURL(/login\.php/);
  });

  test('logout encerra a sessão', async ({ page }) => {
    await login(page);
    await page.goto('logout.php');
    await page.goto('index.php');
    await expect(page).toHaveURL(/login\.php/);
  });

  test('página de recuperação de senha carrega', async ({ page }) => {
    await page.goto('recuperar.php');
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('body')).toContainText(/recupera/i);
  });

  test('página de cadastro carrega com todos os campos', async ({ page }) => {
    await page.goto('cadastro.php');
    await expect(page.locator('input[name="nome"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="senha"]')).toBeVisible();
    await expect(page.locator('input[name="confirmar_senha"]')).toBeVisible();
  });
});
