// @ts-check
/**
 * Critérios de aceite de SEGURANÇA.
 * Estes testes validam as correções de hardening (commits cbd4750..21a3cdd).
 * FALHAS aqui em produção significam que o deploy ainda não foi feito —
 * são o checklist do que o deploy precisa corrigir.
 */
const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');

test.describe('Segurança — controle de acesso a relatórios', () => {

  test('pdf.php?id= sem sessão NÃO entrega o PDF (corrige IDOR)', async ({ request }) => {
    const resp = await request.get('pdf.php?id=1', { maxRedirects: 0 });
    // Esperado: redirect para login (302) ou página de erro — nunca um PDF
    const tipo = resp.headers()['content-type'] || '';
    expect(tipo).not.toContain('pdf');
    expect([301, 302, 400, 401, 403, 404]).toContain(resp.status());
  });

  test('visualizar.php?id= sem sessão NÃO exibe o relatório', async ({ request }) => {
    const resp = await request.get('visualizar.php?id=1', { maxRedirects: 0 });
    const corpo = resp.status() === 200 ? await resp.text() : '';
    expect([301, 302, 400, 401, 403, 404]).toContain(resp.status());
    expect(corpo).not.toContain('Core Web Vitals');
  });

  test('?plano=liberado não desbloqueia conteúdo em acesso anônimo', async ({ request }) => {
    const resp = await request.get('visualizar.php?id=1&plano=liberado', { maxRedirects: 0 });
    expect([301, 302, 400, 401, 403, 404]).toContain(resp.status());
  });
});

test.describe('Segurança — superfície de ataque', () => {

  test('debug_post.php bloqueado para anônimos', async ({ request }) => {
    const resp = await request.get('debug_post.php', { maxRedirects: 0 });
    const corpo = resp.status() === 200 ? await resp.text() : '';
    const bloqueado = [301, 302, 401, 403, 404].includes(resp.status())
      || /acesso negado/i.test(corpo);
    expect(bloqueado).toBeTruthy();
  });

  test('tags_crawler.php exige autenticação', async ({ request }) => {
    const resp = await request.get('tags_crawler.php?url=example.com', { maxRedirects: 0 });
    expect([301, 302, 401, 403]).toContain(resp.status());
  });

  test('excluir.php via GET é rejeitado (exige POST+CSRF)', async ({ page }) => {
    await login(page);
    const resp = await page.request.get('excluir.php?id=999999', { maxRedirects: 0 });
    // 405 (método não permitido) é o esperado pós-deploy; 302 sem exclusão era o antigo
    expect(resp.status()).toBe(405);
  });

  test('logs não são acessíveis via HTTP', async ({ request }) => {
    const resp = await request.get('logs/sistema.log', { maxRedirects: 0 });
    expect([401, 403, 404]).toContain(resp.status());
  });

  test('config-local.php não vaza conteúdo', async ({ request }) => {
    const resp = await request.get('config-local.php');
    if (resp.status() === 200) {
      const corpo = await resp.text();
      expect(corpo).not.toContain('DB_PASS'); // PHP executado = corpo vazio, ok
      expect(corpo.trim().length).toBeLessThan(10);
    }
  });

  test('cabeçalhos de segurança presentes', async ({ request }) => {
    const resp = await request.get('login.php');
    const h = resp.headers();
    expect(h['x-content-type-options']).toBe('nosniff');
    expect(h['x-frame-options']).toBe('SAMEORIGIN');
    expect(h['referrer-policy']).toBeTruthy();
  });

  test('erros de login não vazam detalhes internos', async ({ page }) => {
    await page.goto('login.php');
    await page.fill('input[name="email"]', "teste'--@x.com");
    await page.fill('input[name="senha"]', 'x');
    await page.click('button[type="submit"]');
    await expect(page.locator('body')).not.toContainText(/SQLSTATE|PDOException|xampp|htdocs/i);
  });
});

test.describe('Segurança — link público de compartilhamento', () => {

  test('token inválido mostra página de erro amigável (não dados)', async ({ request }) => {
    const resp = await request.get('visualizar.php?t=token_invalido_12345');
    expect([200, 404]).toContain(resp.status());
    const corpo = await resp.text();
    expect(corpo).not.toContain('Core Web Vitals');
    expect(corpo).toMatch(/não encontrado|inválido/i);
  });
});
