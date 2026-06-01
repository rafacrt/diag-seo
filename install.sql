-- ============================================================
--  Rajo Diagnóstico de Site — Schema do Banco de Dados Atualizado
-- ============================================================

CREATE DATABASE IF NOT EXISTS `rafacrt_diagseo`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `rafacrt_diagseo`;

-- 1. TABELA DE USUÁRIOS (SaaS)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `nome`                VARCHAR(100) NOT NULL,
  `email`               VARCHAR(100) NOT NULL UNIQUE,
  `senha`               VARCHAR(255) NOT NULL,
  `confirmado`          TINYINT(1) NOT NULL DEFAULT 0,
  `tipo`                VARCHAR(20) NOT NULL DEFAULT 'comum',
  `token_confirmacao`   VARCHAR(100) DEFAULT NULL,
  `token_expira`        DATETIME DEFAULT NULL,
  `criado_em`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABELA DE RELATÓRIOS (Com Multitenancy)
CREATE TABLE IF NOT EXISTS `relatorios` (
  `id`                        INT AUTO_INCREMENT PRIMARY KEY,
  
  -- Dados do cliente
  `cliente`                   VARCHAR(255)  NOT NULL,
  `dominio`                   VARCHAR(255)  NOT NULL,
  `data_relatorio`            DATE          NOT NULL,
  `analista`                  VARCHAR(255)  NOT NULL DEFAULT 'Rafael Medeiros – Rajo Desenvolvimento',
  `versao`                    VARCHAR(20)   NOT NULL DEFAULT '1.0',
  `resultado_geral`           VARCHAR(20)   NOT NULL DEFAULT 'CRÍTICO',

  -- PageSpeed Insights
  `ps_performance_desktop`    TINYINT UNSIGNED,
  `ps_performance_mobile`     TINYINT UNSIGNED,
  `ps_seo_desktop`            TINYINT UNSIGNED,
  `ps_seo_mobile`             TINYINT UNSIGNED,
  `ps_acessibilidade_desktop` TINYINT UNSIGNED,
  `ps_acessibilidade_mobile`  TINYINT UNSIGNED,
  `ps_boaspraticas_desktop`   TINYINT UNSIGNED,
  `ps_boaspraticas_mobile`    TINYINT UNSIGNED,

  -- GTmetrix, Ad Experience e Segurança
  `gtm_nota`                  VARCHAR(50),
  `ad_experience_status`      VARCHAR(50),
  `safe_browsing_status`      VARCHAR(100)  DEFAULT NULL,
  `ads_policy_status`         VARCHAR(100)  DEFAULT NULL,

  -- Core Web Vitals Desktop
  `cwv_lcp_desktop`           VARCHAR(20),
  `cwv_inp_desktop`           VARCHAR(20),
  `cwv_cls_desktop`           VARCHAR(20),
  `cwv_fcp_desktop`           VARCHAR(20),
  `cwv_ttfb_desktop`          VARCHAR(20),
  `cwv_speed_desktop`         VARCHAR(20),

  -- Core Web Vitals Mobile
  `cwv_lcp_mobile`            VARCHAR(20),
  `cwv_inp_mobile`            VARCHAR(20),
  `cwv_cls_mobile`            VARCHAR(20),
  `cwv_fcp_mobile`            VARCHAR(20),
  `cwv_ttfb_mobile`           VARCHAR(20),
  `cwv_speed_mobile`          VARCHAR(20),

  -- Status CWV
  `cwv_lcp_status`            VARCHAR(20) DEFAULT 'Ruim',
  `cwv_inp_status`            VARCHAR(20) DEFAULT 'Ruim',
  `cwv_cls_status`            VARCHAR(20) DEFAULT 'Ruim',
  `cwv_fcp_status`            VARCHAR(20) DEFAULT 'Ruim',
  `cwv_ttfb_status`           VARCHAR(20) DEFAULT 'Ruim',
  `cwv_speed_status`          VARCHAR(20) DEFAULT 'Ruim',

  -- Dados dinâmicos JSON
  `problemas`                 JSON,
  `acoes`                     JSON,

  -- Textos
  `conclusao`                 TEXT,
  `obs_pagespeed`             TEXT,

  -- Customizações e plano comercial
  `pdf_cor_tema`              VARCHAR(7)    DEFAULT '#1A4FBB',
  `logo_cliente`              VARCHAR(255)  DEFAULT NULL,
  `bloquear_plano`            TINYINT(1)    DEFAULT 0,
  `auditoria_cms`             VARCHAR(100)  DEFAULT NULL,
  `auditoria_hospedagem`      TEXT          DEFAULT NULL,
  `auditoria_seguranca`       TEXT          DEFAULT NULL,
  `auditoria_dns`             TEXT          DEFAULT NULL,
  `tipo_relatorio`            VARCHAR(20)   DEFAULT 'completo',

  -- Multitenancy (Chave estrangeira SaaS)
  `usuario_id`                INT DEFAULT NULL,

  -- Controle
  `criado_em`                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_cliente  (`cliente`),
  INDEX idx_dominio  (`dominio`),
  INDEX idx_criado   (`criado_em`),
  CONSTRAINT `fk_relatorios_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABELA DE AVISOS GLOBAIS NO PAINEL
CREATE TABLE IF NOT EXISTS `avisos` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `titulo`              VARCHAR(255) NOT NULL,
  `mensagem`            TEXT NOT NULL,
  `tipo`                VARCHAR(20) NOT NULL DEFAULT 'info',
  `ativo`               TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. INSERÇÃO DO USUÁRIO ADMINISTRADOR MASTER PADRÃO
-- Credenciais Iniciais:
-- Usuário: admin@rajo.com.br
-- Senha: Password123!
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `confirmado`, `tipo`) 
VALUES (
  'Administrador Rajo', 
  'admin@rajo.com.br', 
  '$2y$10$gZK97jKTJ5xeLA22TIGCOe92eRac.Gi.8HujPVtRbayEF1M2k/Xvi', 
  1, 
  'master'
) ON DUPLICATE KEY UPDATE `tipo` = 'master', `confirmado` = 1;
