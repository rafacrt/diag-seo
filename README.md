# Rajo Diagnóstico de Site

Sistema para gerar relatórios profissionais de diagnóstico técnico de sites com exportação em PDF.

---

## Requisitos

- PHP >= 8.0
- MySQL >= 5.7 ou MariaDB >= 10.3
- Composer
- Extensões PHP: `pdo_mysql`, `mbstring`, `gd`

---

## Instalação

### 1. Copiar arquivos

Envie a pasta `rajo-diagnostico/` para o diretório do seu servidor (ex: `public_html/diagnostico/`).

### 2. Instalar dependências PHP (mPDF)

```bash
cd rajo-diagnostico/
composer install
```

### 3. Criar o banco de dados

Acesse o phpMyAdmin ou o terminal MySQL e execute:

```bash
mysql -u root -p < install.sql
```

Ou cole o conteúdo de `install.sql` no phpMyAdmin.

### 4. Configurar a conexão

Edite o arquivo `config.php` com seus dados:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rajo_diagnostico');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('APP_URL',   'https://seudominio.com/diagnostico');
```

### 5. Permissões

Garanta que o servidor possa escrever na pasta temporária do mPDF:

```bash
chmod 755 /tmp
# ou em hosts compartilhados, crie uma pasta writeable:
mkdir -p rajo-diagnostico/tmp
chmod 755 rajo-diagnostico/tmp
```

Se precisar de pasta tmp customizada, edite `pdf.php`:
```php
'tempDir' => __DIR__ . '/tmp',
```

---

## Como usar

1. Acesse `https://seudominio.com/diagnostico/`
2. Clique em **Novo Relatório**
3. Preencha os 7 passos do wizard:
   - **Passo 1:** Nome do cliente, domínio, data, analista
   - **Passo 2:** Notas do PageSpeed Insights (copie de pagespeed.web.dev), GTmetrix, Ad Experience
   - **Passo 3:** Core Web Vitals (LCP, INP, CLS, FCP, TTFB, Speed Index)
   - **Passo 4:** Problemas identificados (adicione quantos quiser)
   - **Passo 5:** Plano de ação (ações + responsável + prazo)
   - **Passo 6:** Conclusão e observações
   - **Passo 7:** Revisão final + botão **Gerar PDF**
4. O PDF é gerado automaticamente com o estilo do relatório profissional Rajo

---

## Estrutura de arquivos

```
rajo-diagnostico/
├── config.php          ← Configuração do banco e constantes
├── index.php           ← Dashboard com lista de relatórios
├── form.php            ← Wizard multi-etapas
├── salvar.php          ← Backend AJAX (salva no MySQL)
├── pdf.php             ← Geração do PDF via mPDF
├── excluir.php         ← Exclusão de relatório
├── install.sql         ← Schema do banco de dados
├── composer.json       ← Dependência: mpdf/mpdf
├── vendor/             ← Criado pelo Composer
└── assets/
    ├── style.css       ← Estilos do sistema
    └── app.js          ← Lógica do wizard
```

---

## Personalização

### Alterar dados da empresa no PDF

Em `pdf.php`, localize e edite:
```php
// Header/footer do PDF
'Diagnóstico Técnico — Rajo Desenvolvimento'
'rajo.com.br • contato@rajo.com.br'
```

E em `config.php`:
```php
define('ANALISTA_PADRAO', 'Seu Nome – Sua Empresa');
```

### Adicionar logo no PDF

No `pdf.php`, antes do `$mpdf->WriteHTML($html)`:
```php
// Insira o base64 da sua logo
$logoBase64 = base64_encode(file_get_contents(__DIR__ . '/assets/logo.png'));
// Use no HTML: <img src="data:image/png;base64,{$logoBase64}" height="30">
```

---

## Segurança

Para uso em produção:
1. Adicione autenticação (htpasswd ou sistema de login)
2. Mova `config.php` para fora do webroot
3. Restrinja acesso por IP se for uso interno
4. Use HTTPS

---

## Suporte

Desenvolvido por **Rafael Jocasta — Rajo Desenvolvimento**  
rajo.com.br
