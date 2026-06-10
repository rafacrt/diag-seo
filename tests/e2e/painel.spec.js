// @ts-check
const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');

test.describe('Painel autenticado (somente leitura)', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('painel lista relatórios ou estado vazio', async ({ page }) => {
    // Ou existem cards de relatório, ou a mensagem de painel vazio
    const temCards = await page.locator('.card').count();
    const corpo = await page.locator('body').textContent();
    expect(temCards > 0 || /nenhum|vazio|criar/i.test(corpo || '')).toBeTruthy();
  });

  test('busca por termo inexistente não quebra a página', async ({ page }) => {
    await page.goto('index.php?q=zzz_termo_inexistente_zzz');
    await expect(page.locator('body')).not.toContainText(/fatal|exception|sqlstate/i);
  });

  test('formulário de novo diagnóstico abre', async ({ page }) => {
    await page.goto('form.php');
    await expect(page).not.toHaveURL(/login\.php/);
    await expect(page.locator('body')).toContainText(/cliente|domínio|diagnóstico/i);
  });

  test('visualizar relatório existente renderiza (se houver)', async ({ page }) => {
    // Captura o primeiro link de visualização do painel, se existir
    const link = page.locator('a[href*="visualizar.php?id="]').first();
    if (await link.count() === 0) test.skip(true, 'Sem relatórios no painel para visualizar');
    const href = await link.getAttribute('href');
    await page.goto('' + href);
    await expect(page.locator('body')).toContainText(/diagnóstico/i);
    await expect(page.locator('body')).not.toContainText(/fatal error|warning:/i);
  });

  test('download de PDF responde com application/pdf (se houver relatório)', async ({ page, request }) => {
    const link = page.locator('a[href*="pdf.php?id="]').first();
    if (await link.count() === 0) test.skip(true, 'Sem relatórios no painel para gerar PDF');
    const href = await link.getAttribute('href');
    // Usa o contexto autenticado da página (cookies compartilhados)
    const resp = await page.request.get('' + href);
    expect(resp.status()).toBe(200);
    expect(resp.headers()['content-type'] || '').toContain('pdf');
  });

  test('página financeiro carrega para o usuário', async ({ page }) => {
    await page.goto('financeiro.php');
    await expect(page).not.toHaveURL(/login\.php/);
    await expect(page.locator('body')).toContainText(/saldo|recarga|financeiro/i);
  });

  test('painel admin acessível para usuário master', async ({ page }) => {
    await page.goto('admin.php');
    // admin@rajo.com.br deve ser master; se não for, redireciona ao index
    await expect(page.locator('body')).toContainText(/usuários|administração|master/i);
  });
});
