-- ============================================================
--  Rajo Diagnóstico de Site — Schema do Banco de Dados
-- ============================================================

CREATE DATABASE IF NOT EXISTS rajo_diagnostico
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rajo_diagnostico;

CREATE TABLE IF NOT EXISTS relatorios (
  id                        INT AUTO_INCREMENT PRIMARY KEY,

  -- Dados do cliente
  cliente                   VARCHAR(255)  NOT NULL,
  dominio                   VARCHAR(255)  NOT NULL,
  data_relatorio            DATE          NOT NULL,
  analista                  VARCHAR(255)  NOT NULL DEFAULT 'Rafael Medeiros – Rajo Desenvolvimento',
  versao                    VARCHAR(20)   NOT NULL DEFAULT '1.0',
  resultado_geral           VARCHAR(20)   NOT NULL DEFAULT 'CRÍTICO',

  -- PageSpeed Insights
  ps_performance_desktop    TINYINT UNSIGNED,
  ps_performance_mobile     TINYINT UNSIGNED,
  ps_seo_desktop            TINYINT UNSIGNED,
  ps_seo_mobile             TINYINT UNSIGNED,
  ps_acessibilidade_desktop TINYINT UNSIGNED,
  ps_acessibilidade_mobile  TINYINT UNSIGNED,
  ps_boaspraticas_desktop   TINYINT UNSIGNED,
  ps_boaspraticas_mobile    TINYINT UNSIGNED,

  -- GTmetrix, Ad Experience e Segurança
  gtm_nota                  VARCHAR(50),
  ad_experience_status      VARCHAR(50),
  safe_browsing_status      VARCHAR(100)  DEFAULT NULL,
  ads_policy_status         VARCHAR(100)  DEFAULT NULL,

  -- Core Web Vitals Desktop
  cwv_lcp_desktop           VARCHAR(20),
  cwv_inp_desktop           VARCHAR(20),
  cwv_cls_desktop           VARCHAR(20),
  cwv_fcp_desktop           VARCHAR(20),
  cwv_ttfb_desktop          VARCHAR(20),
  cwv_speed_desktop         VARCHAR(20),

  -- Core Web Vitals Mobile
  cwv_lcp_mobile            VARCHAR(20),
  cwv_inp_mobile            VARCHAR(20),
  cwv_cls_mobile            VARCHAR(20),
  cwv_fcp_mobile            VARCHAR(20),
  cwv_ttfb_mobile           VARCHAR(20),
  cwv_speed_mobile          VARCHAR(20),

  -- Status CWV
  cwv_lcp_status            VARCHAR(20) DEFAULT 'Ruim',
  cwv_inp_status            VARCHAR(20) DEFAULT 'Ruim',
  cwv_cls_status            VARCHAR(20) DEFAULT 'Ruim',
  cwv_fcp_status            VARCHAR(20) DEFAULT 'Ruim',
  cwv_ttfb_status           VARCHAR(20) DEFAULT 'Ruim',
  cwv_speed_status          VARCHAR(20) DEFAULT 'Ruim',

  -- Dados dinâmicos JSON
  problemas                 JSON,
  acoes                     JSON,

  -- Textos
  conclusao                 TEXT,
  obs_pagespeed             TEXT,

  -- Customizações e plano comercial
  pdf_cor_tema              VARCHAR(7)    DEFAULT '#1A4FBB',
  logo_cliente              VARCHAR(255)  DEFAULT NULL,
  bloquear_plano            TINYINT(1)    DEFAULT 0,

  -- Controle
  criado_em                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_cliente  (cliente),
  INDEX idx_dominio  (dominio),
  INDEX idx_criado   (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- Migração: adiciona campos de verificações alternativas
-- (Nota: O arquivo index.php realiza esta migração de forma automática.
-- Caso precise executar manualmente em MySQL/MariaDB, use as instruções abaixo)
-- ──────────────────────────────────────────────────────────────
-- ALTER TABLE relatorios ADD COLUMN safe_browsing_status VARCHAR(100) DEFAULT NULL AFTER ad_experience_status;
-- ALTER TABLE relatorios ADD COLUMN ads_policy_status    VARCHAR(100) DEFAULT NULL AFTER safe_browsing_status;
-- ALTER TABLE relatorios ADD COLUMN pdf_cor_tema         VARCHAR(7)   DEFAULT '#1A4FBB' AFTER obs_pagespeed;
-- ALTER TABLE relatorios ADD COLUMN logo_cliente         VARCHAR(255) DEFAULT NULL AFTER pdf_cor_tema;
-- ALTER TABLE relatorios ADD COLUMN bloquear_plano      TINYINT(1)   DEFAULT 0 AFTER logo_cliente;
