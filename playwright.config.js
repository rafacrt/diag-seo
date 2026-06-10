// @ts-check
const { defineConfig } = require('@playwright/test');

/**
 * Suíte E2E do Rajo Diagnóstico.
 * Roda contra produção por padrão (BASE_URL para sobrescrever).
 * Credenciais via .env / variáveis de ambiente — nunca commitadas.
 */
require('dotenv').config();

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30000,
  retries: 1,
  workers: 2, // produção: evita martelar o servidor
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    // Barra final obrigatória: paths relativos resolvem dentro de /diag-seo/
    baseURL: process.env.BASE_URL || 'https://rajo.com.br/diag-seo/',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { browserName: 'chromium' } },
  ],
});
