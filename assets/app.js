/* ============================================================
   Rajo Diagnóstico — Wizard & Integração API JS
   ============================================================ */

let currentStep = 1;
const TOTAL_STEPS = 7;

// ─── Inicialização ────────────────────────────────────────────
function initWizard(step) {
    currentStep = step;
    renderStep();
    updateProgress();
}

// ─── Navegação ────────────────────────────────────────────────
function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep < TOTAL_STEPS) {
        currentStep++;
        renderStep();
        updateProgress();
        scrollToTop();
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        renderStep();
        updateProgress();
        scrollToTop();
    }
}

function goStep(n) {
    if (n < currentStep || n === currentStep) {
        currentStep = n;
        renderStep();
        updateProgress();
        scrollToTop();
    }
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─── Renderiza step ───────────────────────────────────────────
function renderStep() {
    document.querySelectorAll('.step-panel').forEach((el, i) => {
        el.classList.toggle('d-none', i + 1 !== currentStep);
    });

    document.querySelectorAll('.rajo-step').forEach((el, i) => {
        const n = i + 1;
        el.classList.remove('active', 'done');
        if (n === currentStep) el.classList.add('active');
        else if (n < currentStep) el.classList.add('done');
    });

    if (currentStep === TOTAL_STEPS) buildReview();
}

// ─── Progress bar ─────────────────────────────────────────────
function updateProgress() {
    const pct = ((currentStep - 1) / (TOTAL_STEPS - 1)) * 100;
    document.getElementById('progressFill').style.width = pct + '%';
}

// ─── Validação simples ────────────────────────────────────────
function validateStep(step) {
    if (step === 1) {
        const cliente = document.querySelector('[name="cliente"]').value.trim();
        const dominio = document.querySelector('[name="dominio"]').value.trim();
        const data    = document.querySelector('[name="data_relatorio"]').value.trim();
        if (!cliente || !dominio || !data) {
            showToast('Preencha os campos obrigatórios: Cliente, Domínio e Data.', 'warning');
            return false;
        }
    }
    return true;
}

// ─── Revisão ─────────────────────────────────────────────────
function buildReview() {
    const f  = new FormData(document.getElementById('formRelatorio'));
    const el = document.getElementById('resumoRevisao');

    const row = (label, val) => val
        ? `<div class="resumo-item"><span class="resumo-label">${label}:</span> <span>${val}</span></div>`
        : '';

    const scoreRow = (label, desk, mob) => {
        const d = desk || '–';
        const m = mob  || '–';
        return row(label, `Desktop: <strong>${d}</strong> &nbsp;|&nbsp; Mobile: <strong>${m}</strong>`);
    };

    let html = '';

    // Bloco 1: Cliente
    html += `<div class="resumo-bloco">
        <div class="resumo-titulo"><i class="bi bi-person-badge me-2"></i>Dados do Cliente</div>
        ${row('Cliente',          f.get('cliente'))}
        ${row('Domínio',          f.get('dominio'))}
        ${row('Data',             formatDate(f.get('data_relatorio')))}
        ${row('Analista',         f.get('analista'))}
        ${row('Resultado Geral',  f.get('resultado_geral'))}
    </div>`;

    // Bloco 2: Scores
    html += `<div class="resumo-bloco">
        <div class="resumo-titulo"><i class="bi bi-speedometer2 me-2"></i>Pontuações</div>
        ${scoreRow('Performance',    f.get('ps_performance_desktop'),    f.get('ps_performance_mobile'))}
        ${scoreRow('SEO',            f.get('ps_seo_desktop'),            f.get('ps_seo_mobile'))}
        ${scoreRow('Acessibilidade', f.get('ps_acessibilidade_desktop'), f.get('ps_acessibilidade_mobile'))}
        ${scoreRow('Boas Práticas',  f.get('ps_boaspraticas_desktop'),   f.get('ps_boaspraticas_mobile'))}
        ${row('GTmetrix',           f.get('gtm_nota'))}
        ${row('Ad Experience',      f.get('ad_experience_status'))}
    </div>`;

    // Bloco 3: CWV
    html += `<div class="resumo-bloco">
        <div class="resumo-titulo"><i class="bi bi-bar-chart-line me-2"></i>Core Web Vitals</div>
        ${scoreRow('LCP',   f.get('cwv_lcp_desktop'),   f.get('cwv_lcp_mobile'))}
        ${scoreRow('INP',   f.get('cwv_inp_desktop'),   f.get('cwv_inp_mobile'))}
        ${scoreRow('CLS',   f.get('cwv_cls_desktop'),   f.get('cwv_cls_mobile'))}
        ${scoreRow('FCP',   f.get('cwv_fcp_desktop'),   f.get('cwv_fcp_mobile'))}
        ${scoreRow('TTFB',  f.get('cwv_ttfb_desktop'),  f.get('cwv_ttfb_mobile'))}
        ${scoreRow('Speed', f.get('cwv_speed_desktop'), f.get('cwv_speed_mobile'))}
    </div>`;

    // Bloco 4: Problemas
    const problemas = getArrayItems('problemas');
    html += `<div class="resumo-bloco">
        <div class="resumo-titulo"><i class="bi bi-exclamation-triangle me-2"></i>Problemas (${problemas.length})</div>
        ${problemas.map((p, i) => row(`${i+1}. ${p.prioridade}`, p.problema)).join('')}
    </div>`;

    // Bloco 5: Ações
    const acoes = getArrayItems('acoes');
    html += `<div class="resumo-bloco">
        <div class="resumo-titulo"><i class="bi bi-list-check me-2"></i>Plano de Ação (${acoes.length} ações)</div>
        ${acoes.map((a, i) => row(`${i+1}. ${a.responsavel}`, a.acao + (a.prazo ? ` <em class="text-muted">(${a.prazo})</em>` : ''))).join('')}
    </div>`;

    el.innerHTML = html;
}

// ─── Coletar arrays dinâmicos ─────────────────────────────────
function getArrayItems(type) {
    const items = [];
    const container = document.getElementById(type === 'problemas' ? 'listaProblemas' : 'listaAcoes');
    if (!container) return items;
    container.querySelectorAll('.rajo-row-dinamica').forEach(row => {
        const inputs = {};
        row.querySelectorAll('[name]').forEach(input => {
            const match = input.name.match(/\[([^\]]+)\]$/);
            if (match) inputs[match[1]] = input.value.trim();
        });
        if (Object.values(inputs).some(v => v)) items.push(inputs);
    });
    return items;
}

// ─── Salvar relatório ─────────────────────────────────────────
async function salvarRelatorio() {
    const btn = document.getElementById('btnSalvar');
    const alerta = document.getElementById('alertaSalvar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando…';

    try {
        const formData = new FormData(document.getElementById('formRelatorio'));
        const resp = await fetch('salvar.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            alerta.className = 'alert alert-success d-flex align-items-center gap-2 mt-3';
            alerta.innerHTML = `<i class="bi bi-check-circle-fill text-success fs-5"></i>
                <div><strong>Relatório salvo com sucesso!</strong> ID #${data.id}</div>`;
            alerta.classList.remove('d-none');

            // Atualiza hidden id
            document.querySelector('[name="id"]').value = data.id;

            // Mostra botão PDF e Ver Online
            const btnPdf = document.getElementById('btnGerarPdf');
            if (btnPdf) {
                btnPdf.href = data.pdf_url;
                btnPdf.classList.remove('d-none');
            }

            const btnOnline = document.getElementById('btnVerOnline');
            if (btnOnline) {
                btnOnline.href = `visualizar.php?id=${data.id}`;
                btnOnline.classList.remove('d-none');
            }

            btn.innerHTML = '<i class="bi bi-floppy me-1"></i> Salvo!';
            btn.classList.replace('btn-success', 'btn-outline-success');

            showToast('Relatório salvo! Clique em "Gerar PDF" para baixar.', 'success');
        } else {
            throw new Error(data.msg || 'Erro desconhecido');
        }
    } catch (err) {
        alerta.className = 'alert alert-danger d-flex align-items-center gap-2 mt-3';
        alerta.innerHTML = `<i class="bi bi-x-circle-fill text-danger fs-5"></i>
            <div><strong>Erro ao salvar:</strong> ${err.message}</div>`;
        alerta.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-floppy me-1"></i> Salvar Relatório';
        btn.disabled = false;
        showToast('Erro ao salvar relatório.', 'danger');
    }
}

// ─── Linhas dinâmicas — Problemas ────────────────────────────
function adicionarProblema(texto = '', impacto = '', prioridade = 'Alta') {
    const lista = document.getElementById('listaProblemas');
    const idx   = lista.querySelectorAll('.rajo-row-dinamica').length;
    const div   = document.createElement('div');
    div.className = 'rajo-row-dinamica mb-2';
    div.dataset.index = idx;
    div.innerHTML = `
    <div class="row g-2 align-items-center">
      <div class="col-12 col-md-5">
        <input type="text" class="form-control form-control-sm"
               name="problemas[${idx}][problema]" value="${texto}" placeholder="Descreva o problema…">
      </div>
      <div class="col-12 col-md-4">
        <input type="text" class="form-control form-control-sm"
               name="problemas[${idx}][impacto]" value="${impacto}" placeholder="Ex.: Ads + SEO + UX">
      </div>
      <div class="col-8 col-md-2">
        <select class="form-select form-select-sm" name="problemas[${idx}][prioridade]">
          <option ${prioridade === 'Alta' ? 'selected' : ''}>Alta</option>
          <option ${prioridade === 'Média' ? 'selected' : ''}>Média</option>
          <option ${prioridade === 'Baixa' ? 'selected' : ''}>Baixa</option>
        </select>
      </div>
      <div class="col-4 col-md-1 text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerLinha(this)">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>`;
    lista.appendChild(div);
}

// ─── Linhas dinâmicas — Ações ─────────────────────────────────
function adicionarAcao(texto = '', responsavel = 'Desenvolvedor', prazo = '') {
    const lista = document.getElementById('listaAcoes');
    const idx   = lista.querySelectorAll('.rajo-row-dinamica').length;
    const div   = document.createElement('div');
    div.className = 'rajo-row-dinamica mb-2';
    div.dataset.index = idx;
    div.innerHTML = `
    <div class="row g-2 align-items-center">
      <div class="col-12 col-md-5">
        <input type="text" class="form-control form-control-sm"
               name="acoes[${idx}][acao]" value="${texto}" placeholder="Descreva a ação…">
      </div>
      <div class="col-12 col-md-3">
        <input type="text" class="form-control form-control-sm"
               name="acoes[${idx}][responsavel]" value="${responsavel}" placeholder="Ex.: Desenvolvedor">
      </div>
      <div class="col-8 col-md-3">
        <input type="text" class="form-control form-control-sm"
               name="acoes[${idx}][prazo]" value="${prazo}" placeholder="Ex.: 1–3 dias">
      </div>
      <div class="col-4 col-md-1 text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerLinha(this)">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>`;
    lista.appendChild(div);
}

function removerLinha(btn) {
    const row = btn.closest('.rajo-row-dinamica');
    row.style.transition = 'opacity .2s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 200);
}

// ─── Toast Premium ────────────────────────────────────────────
function showToast(msg, type = 'info') {
    const old = document.querySelector('.rajo-toast');
    if (old) old.remove();

    const icons = { success:'check-circle-fill', danger:'x-circle-fill', warning:'exclamation-triangle-fill', info:'info-circle-fill' };
    const toast = document.createElement('div');
    toast.className = `rajo-toast alert alert-${type} d-flex align-items-center gap-2 py-3 px-4`;
    toast.innerHTML = `<i class="bi bi-${icons[type] || 'info-circle-fill'}"></i> <span>${msg}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity .4s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}

// ─── Utilitários ──────────────────────────────────────────────
function formatDate(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

// ─── SISTEMA DE PRESETS RÁPIDOS (Modelos de 1-Clique) ──────────
const PRESETS = {
    timeout: {
        gtm_nota: 'Erro (Lighthouse Timeout)',
        resultado_geral: 'CRÍTICO',
        problemas: [
            { problema: 'Bloqueio crítico de carregamento da página por excesso de recursos de terceiros (Timeout no Lighthouse/GTmetrix).', impacto: 'Performance + Ads', prioridade: 'Alta' },
            { problema: 'Ausência de compressão ou otimização de imagens, resultando em consumo excessivo de banda de rede.', impacto: 'Performance + UX', prioridade: 'Alta' },
            { problema: 'Presença de scripts e estilos bloqueadores de renderização no topo do HTML.', impacto: 'Performance + SEO', prioridade: 'Média' },
            { problema: 'Time to First Byte (TTFB) extremamente elevado por falta de cache e otimização do servidor.', impacto: 'Performance + Ads', prioridade: 'Alta' }
        ],
        acoes: [
            { acao: 'Remover ou carregar de forma assíncrona (defer/async) todos os scripts não essenciais de terceiros.', responsavel: 'Desenvolvedor', prazo: '1–3 dias' },
            { acao: 'Implementar otimização automatizada de imagens e conversão para o formato moderno WebP.', responsavel: 'Desenvolvedor', prazo: '1–2 dias' },
            { acao: 'Instalar e configurar plugin de cache de página completo e integração com rede CDN (Cloudflare).', responsavel: 'Desenvolvedor', prazo: '1 dia' },
            { acao: 'Revisar queries e processamento PHP do servidor para otimizar o tempo de resposta inicial (TTFB).', responsavel: 'Desenvolvedor', prazo: '2–4 dias' }
        ]
    },
    seo: {
        gtm_nota: 'B',
        resultado_geral: 'MÉDIO',
        problemas: [
            { problema: 'Ausência ou má configuração das meta tags cruciais de SEO (Title e Meta Description) nas páginas prioritárias.', impacto: 'SEO Técnico', prioridade: 'Alta' },
            { problema: 'Arquitetura de cabeçalhos sem hierarquia adequada (múltiplas tags H1 ou cabeçalhos desorganizados).', impacto: 'SEO Técnico', prioridade: 'Média' },
            { problema: 'Falta de sitemap.xml cadastrado e ausência do arquivo robots.txt no diretório raiz.', impacto: 'SEO Técnico + Indexação', prioridade: 'Alta' },
            { problema: 'Imagens no site sem a propriedade ALT preenchida, dificultando o SEO de imagens do Google.', impacto: 'SEO On-Page', prioridade: 'Baixa' }
        ],
        acoes: [
            { acao: 'Mapear e configurar meta tags de SEO otimizadas e exclusivas para cada página chave do site.', responsavel: 'SEO Especialista', prazo: '2–4 dias' },
            { acao: 'Ajustar a ordem de tags H1, H2 e H3 do site para garantir uma hierarquia semântica perfeita.', responsavel: 'Desenvolvedor', prazo: '1–2 dias' },
            { acao: 'Gerar o arquivo robots.txt e cadastrar o sitemap.xml atualizado diretamente no Google Search Console.', responsavel: 'SEO Especialista', prazo: '1 dia' },
            { acao: 'Auditar a biblioteca de mídia e preencher o texto descritivo ALT de todas as imagens críticas do site.', responsavel: 'SEO Especialista', prazo: '2–3 dias' }
        ]
    },
    ads: {
        gtm_nota: 'C',
        resultado_geral: 'RUIM',
        problemas: [
            { problema: 'Ausência da tag global do Google (gtag.js) ou do Google Tag Manager (GTM) para monitoramento.', impacto: 'Google Ads + Conversão', prioridade: 'Alta' },
            { problema: 'Falta de configuração de eventos de conversão críticos (cliques em botões de WhatsApp e envios de formulário).', impacto: 'Google Ads + ROI', prioridade: 'Alta' },
            { problema: 'Domínio sem verificação no Google Search Console e Ad Experience Report inacessível.', impacto: 'Políticas Google Ads', prioridade: 'Média' }
        ],
        acoes: [
            { acao: 'Instalar e configurar a tag do Google Tag Manager (GTM) no cabeçalho de todo o site.', responsavel: 'Desenvolvedor', prazo: '1 dia' },
            { acao: 'Instalar e mapear os pixels e disparadores de conversão do Google Ads para cliques em botões de contato.', responsavel: 'Analista de Tráfego', prazo: '1–2 dias' },
            { acao: 'Realizar a verificação do domínio do cliente no Google Search Console via registro DNS.', responsavel: 'Desenvolvedor', prazo: '1–2 dias' }
        ]
    }
};

function aplicarPreset(key) {
    const preset = PRESETS[key];
    if (!preset) return;

    // 1. Configura a nota do GTmetrix
    const gtmSelect = document.querySelector('[name="gtm_nota"]');
    if (gtmSelect) gtmSelect.value = preset.gtm_nota;

    // 2. Configura o Resultado Geral
    const resSelect = document.querySelector('[name="resultado_geral"]');
    if (resSelect) resSelect.value = preset.resultado_geral;

    // 3. Insere problemas
    const listaProblemas = document.getElementById('listaProblemas');
    listaProblemas.innerHTML = ''; // Limpa os atuais
    preset.problemas.forEach(p => {
        adicionarProblema(p.problema, p.impacto, p.prioridade);
    });

    // 4. Insere ações
    const listaAcoes = document.getElementById('listaAcoes');
    listaAcoes.innerHTML = ''; // Limpa os atuais
    preset.acoes.forEach(a => {
        adicionarAcao(a.acao, a.responsavel, a.prazo);
    });

    showToast(`Modelo rápido "${key.toUpperCase()}" aplicado com sucesso! Problemas e Plano de Ação pré-preenchidos.`, 'success');
}


// ─── INTEGRAÇÃO INTELIGENTE COM GOOGLE PAGESPEED API ─────────
async function analisarPageSpeed() {
    let domain = document.querySelector('[name="dominio"]').value.trim();
    if (!domain) {
        showToast('Insira o domínio do site antes de executar a análise automática!', 'warning');
        return;
    }

    // Verifica se é domínio local que a API externa não alcança
    if (domain.includes('localhost') || domain.includes('127.0.0.1') || domain.includes('.local') || domain.includes('.test')) {
        showToast('Domínios locais (como localhost) não são alcançáveis pela API do Google. Use um domínio público na internet!', 'warning');
        return;
    }

    // Garante que o domínio comece com protocolo para a API funcionar
    let urlParaApi = domain;
    if (!/^https?:\/\//i.test(domain)) {
        urlParaApi = 'https://' + domain;
    }

    // Ativa overlay de loading
    const overlay = document.getElementById('apiLoadingOverlay');
    const loadingText = document.getElementById('apiLoadingText');
    overlay.classList.add('active');

    // Funções de atualização de progresso
    const updateProgressStep = (id, status) => {
        const stepEl = document.getElementById(id);
        if (stepEl) {
            stepEl.className = `api-progress-step ${status}`;
            if (status === 'active') {
                stepEl.innerHTML = `<span class="spinner-border spinner-border-sm text-primary"></span> ${stepEl.dataset.text}`;
            } else if (status === 'done') {
                stepEl.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> ${stepEl.dataset.text}`;
            }
        }
    };

    try {
        // Dispara crawler de tags em paralelo para não adicionar latência
        const tagsPromise = fetch(`tags_crawler.php?url=${encodeURIComponent(urlParaApi)}`)
            .then(r => r.json())
            .catch(() => null);

        // --- ETAPA 1: Mobile ---
        updateProgressStep('stepMobile', 'active');
        loadingText.textContent = "Analisando performance Mobile na API do Google...";
        const mobileData = await fetchPageSpeedData(urlParaApi, 'mobile');
        updateProgressStep('stepMobile', 'done');

        // --- ETAPA 2: Desktop ---
        updateProgressStep('stepDesktop', 'active');
        loadingText.textContent = "Analisando performance Desktop na API do Google...";
        const desktopData = await fetchPageSpeedData(urlParaApi, 'desktop');
        updateProgressStep('stepDesktop', 'done');

        // --- ETAPA 3: Processando dados ---
        updateProgressStep('stepProcessing', 'active');
        loadingText.textContent = "Processando métricas e preenchendo formulário...";

        const tagsData = await tagsPromise;
        preencherDadosPageSpeed(mobileData, desktopData, tagsData);
        
        updateProgressStep('stepProcessing', 'done');
        showToast('Dados do PageSpeed e Crawler de Tags importados com sucesso!', 'success');
        
        // Direciona o usuário para o Passo 2 para ver as notas!
        setTimeout(() => {
            overlay.classList.remove('active');
            goStep(2);
        }, 1200);

    } catch (err) {
        console.error(err);
        overlay.classList.remove('active');
        showToast(`Erro na análise: ${err.message || 'Falha ao consultar a API.'}`, 'danger');
    }
}

// Faz requisição para a API oficial do Google PageSpeed com tratamento de erro robusto
async function fetchPageSpeedData(url, strategy) {
    const categories = ['performance', 'seo', 'accessibility', 'best-practices'];
    const catQuery = categories.map(c => `category=${c}`).join('&');
    let apiUrl = `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=${encodeURIComponent(url)}&strategy=${strategy}&${catQuery}`;
    
    // Anexa a chave de API se estiver configurada globalmente no config.php
    if (typeof PAGESPEED_API_KEY !== 'undefined' && PAGESPEED_API_KEY.trim() !== '') {
        apiUrl += `&key=${PAGESPEED_API_KEY.trim()}`;
    }
    
    try {
        const resp = await fetch(apiUrl);
        if (!resp.ok) {
            let errorMsg = `HTTP ${resp.status}`;
            try {
                const errJson = await resp.json();
                if (errJson?.error?.message) {
                    errorMsg = errJson.error.message;
                }
            } catch(e) {}
            throw new Error(`Google API (${strategy}): ${errorMsg}`);
        }
        return await resp.json();
    } catch (fetchErr) {
        throw new Error(fetchErr.message || 'Falha de rede ou timeout na API.');
    }
}

// Preenche os campos do formulário de forma automatizada com seletores seguros
function preencherDadosPageSpeed(mobileJson, desktopJson, tagsData = null) {
    const setField = (name, val) => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) el.value = val !== undefined && val !== null ? val : '';
    };

    const getScore = (json, cat) => {
        try {
            return Math.round(json.lighthouseResult.categories[cat].score * 100);
        } catch(e) {
            return '';
        }
    };

    // 1. Injeta notas de PageSpeed Mobile
    setField('ps_performance_mobile',    getScore(mobileJson, 'performance'));
    setField('ps_seo_mobile',            getScore(mobileJson, 'seo'));
    setField('ps_acessibilidade_mobile', getScore(mobileJson, 'accessibility'));
    setField('ps_boaspraticas_mobile',   getScore(mobileJson, 'best-practices'));

    // 2. Injeta notas de PageSpeed Desktop
    setField('ps_performance_desktop',    getScore(desktopJson, 'performance'));
    setField('ps_seo_desktop',            getScore(desktopJson, 'seo'));
    setField('ps_acessibilidade_desktop', getScore(desktopJson, 'accessibility'));
    setField('ps_boaspraticas_desktop',   getScore(desktopJson, 'best-practices'));

    // Auto-calcula o Resultado Geral sugerido com base nas notas do PageSpeed Mobile
    let perfScore = getScore(mobileJson, 'performance');
    let seoScore = getScore(mobileJson, 'seo');
    let resultadoSugerido = 'CRÍTICO';
    if (perfScore !== '') {
        if (perfScore >= 90 && seoScore >= 90) {
            resultadoSugerido = 'BOM';
        } else if (perfScore >= 50) {
            resultadoSugerido = 'MÉDIO';
        } else if (perfScore >= 30) {
            resultadoSugerido = 'RUIM';
        } else {
            resultadoSugerido = 'CRÍTICO';
        }
    }
    setField('resultado_geral', resultadoSugerido);

    // 3. Extrai Core Web Vitals (Lab Data do Lighthouse)
    const extractCWV = (json) => {
        const audits = json?.lighthouseResult?.audits || {};
        return {
            fcp: audits['first-contentful-paint']?.displayValue || '',
            lcp: audits['largest-contentful-paint']?.displayValue || '',
            cls: audits['cumulative-layout-shift']?.displayValue || '',
            ttfb: audits['server-response-time']?.displayValue || (audits['server-response-time']?.numericValue ? Math.round(audits['server-response-time'].numericValue) + ' ms' : ''),
            speed: audits['speed-index']?.displayValue || ''
        };
    };

    const cwvM = extractCWV(mobileJson);
    const cwvD = extractCWV(desktopJson);

    // 4. Injeta valores nos inputs de Core Web Vitals
    setField('cwv_fcp_desktop',   cwvD.fcp);
    setField('cwv_fcp_mobile',    cwvM.fcp);
    setField('cwv_lcp_desktop',   cwvD.lcp);
    setField('cwv_lcp_mobile',    cwvM.lcp);
    setField('cwv_cls_desktop',   cwvD.cls);
    setField('cwv_cls_mobile',    cwvM.cls);
    setField('cwv_ttfb_desktop',  cwvD.ttfb);
    setField('cwv_ttfb_mobile',   cwvM.ttfb);
    setField('cwv_speed_desktop', cwvD.speed);
    setField('cwv_speed_mobile',  cwvM.speed);

    // Tenta obter dados reais de CrUX para o INP
    const getCrUX = (json, metric) => {
        try {
            return json.loadingExperience.metrics[metric].percentiles[0];
        } catch(e) {
            return null;
        }
    };

    const inpD = getCrUX(desktopJson, 'INTERACTION_TO_NEXT_PAINT');
    const inpM = getCrUX(mobileJson, 'INTERACTION_TO_NEXT_PAINT');
    setField('cwv_inp_desktop', inpD ? inpD + ' ms' : '');
    setField('cwv_inp_mobile',  inpM ? inpM + ' ms' : '');

    // 5. Calcula automaticamente os status baseados nas métricas Mobile prioritariamente
    const cwvKeys = ['fcp', 'lcp', 'cls', 'inp', 'ttfb', 'speed'];
    cwvKeys.forEach(k => {
        const elMob = document.querySelector(`[name="cwv_${k}_mobile"]`);
        const elDesk = document.querySelector(`[name="cwv_${k}_desktop"]`);
        const valMobile = elMob ? elMob.value : '';
        const valDesktop = elDesk ? elDesk.value : '';
        const baseVal = valMobile || valDesktop;
        
        let status = 'Ruim';
        if (baseVal) {
            const num = parseFloat(baseVal);
            const isMs = baseVal.includes('ms');

            if (k === 'lcp') {
                status = num < 2.5 ? 'Bom' : (num <= 4.0 ? 'Médio' : 'Ruim');
            } else if (k === 'cls') {
                status = num < 0.1 ? 'Bom' : (num <= 0.25 ? 'Médio' : 'Ruim');
            } else if (k === 'inp') {
                status = num < 200 ? 'Bom' : (num <= 500 ? 'Médio' : 'Ruim');
            } else if (k === 'fcp') {
                status = num < 1.8 ? 'Bom' : (num <= 3.0 ? 'Médio' : 'Ruim');
            } else if (k === 'ttfb') {
                const ttfbNum = isMs ? num : num * 1000;
                status = ttfbNum < 600 ? 'Bom' : (ttfbNum <= 1800 ? 'Médio' : 'Ruim');
            } else if (k === 'speed') {
                status = num < 3.4 ? 'Bom' : (num <= 5.8 ? 'Médio' : 'Ruim');
            }
        }
        setField(`cwv_${k}_status`, status);
    });

    // --- NOVO: AUTO-POPULAÇÃO DE PROBLEMAS E PLANOS DE AÇÃO BASEADO NO LIGHTHOUSE ---
    const listaProblemas = document.getElementById('listaProblemas');
    const listaAcoes = document.getElementById('listaAcoes');
    
    // Armazena as falhas detectadas para evitar duplicatas
    const problemasAdicionados = new Set();
    const acoesAdicionadas = new Set();

    // Seletor dinâmico de falhas Lighthouse
    const mobileAudits = mobileJson?.lighthouseResult?.audits || {};
    const desktopAudits = desktopJson?.lighthouseResult?.audits || {};

    const analisarFalhaLighthouse = (auditKey, conditionFn, problemaTxt, impactoTxt, prioridadeVal, acaoTxt, responsavelVal, prazoVal) => {
        const auditM = mobileAudits[auditKey];
        const auditD = desktopAudits[auditKey];
        
        // Verifica se falhou no Mobile ou Desktop
        const falhouM = auditM !== undefined && conditionFn(auditM);
        const falhouD = auditD !== undefined && conditionFn(auditD);

        if ((falhouM || falhouD) && !problemasAdicionados.has(problemaTxt)) {
            // Se for o primeiro problema inserido, limpamos a lista padrão/vazia
            if (problemasAdicionados.size === 0) {
                listaProblemas.innerHTML = '';
                listaAcoes.innerHTML = '';
            }

            problemasAdicionados.add(problemaTxt);
            adicionarProblema(problemaTxt, impactoTxt, prioridadeVal);

            if (!acoesAdicionadas.has(acaoTxt)) {
                acoesAdicionadas.add(acaoTxt);
                adicionarAcao(acaoTxt, responsavelVal, prazoVal);
            }
        }
    };

    // Definição das condições de falha
    const scoreBaixo = (a) => a.score !== null && a.score < 0.9;
    const numericAlto = (limit) => (a) => a.numericValue !== undefined && a.numericValue > limit;

    // 1. Recursos Bloqueadores de Renderização (Render-Blocking)
    analisarFalhaLighthouse(
        'render-blocking-resources', 
        scoreBaixo, 
        'Recursos bloqueadores de renderização (CSS/JS) no topo do HTML, atrasando a exibição da página.',
        'Performance + UX', 
        'Alta', 
        'Otimizar a entrega de recursos críticos (adicionar defer/async nos scripts e inline no CSS crítico).',
        'Desenvolvedor', 
        '1–2 dias'
    );

    // 2. Imagens não otimizadas ou em formatos antigos
    analisarFalhaLighthouse(
        'uses-optimized-images', 
        scoreBaixo, 
        'Ausência de otimização de imagens, resultando em carregamento extremamente lento em redes móveis.',
        'Performance + UX', 
        'Alta', 
        'Comprimir imagens pesadas do site e converter mídias para formatos modernos (como WebP/AVIF).',
        'Desenvolvedor', 
        '1–2 dias'
    );

    // 3. Tamanho dos elementos de toque (Acessibilidade/UX)
    analisarFalhaLighthouse(
        'tap-targets', 
        scoreBaixo, 
        'Elementos de clique (links, botões, fechar de pop-ups) muito pequenos ou próximos no celular, prejudicando usabilidade.',
        'UX + Ads', 
        'Média', 
        'Ajustar o tamanho mínimo de toque de todos os botões e links móveis para pelo menos 48px x 48px.',
        'Desenvolvedor', 
        '1–2 dias'
    );

    // 4. Servidor Lento (TTFB alto)
    analisarFalhaLighthouse(
        'server-response-time', 
        numericAlto(600), 
        'Tempo de resposta inicial do servidor (TTFB) muito elevado, atrasando toda a experiência do usuário.',
        'Performance + Ads', 
        'Alta', 
        'Implementar cache de página robusto no servidor e utilizar uma rede CDN (como Cloudflare) para entrega veloz.',
        'Desenvolvedor', 
        '1 dia'
    );

    // 5. Layout instável (CLS alto)
    analisarFalhaLighthouse(
        'cumulative-layout-shift', 
        (a) => a.numericValue !== undefined && a.numericValue > 0.1, 
        'Instabilidade visual de layout severa (Layout Shift), deslocando textos e gerando cliques acidentais no celular.',
        'UX + SEO', 
        'Alta', 
        'Definir dimensões de largura e altura (width/height) explícitas nas imagens e blocos dinâmicos do site.',
        'Desenvolvedor', 
        '1–2 dias'
    );

    // 6. Thread principal bloqueada (TBT alto)
    analisarFalhaLighthouse(
        'total-blocking-time', 
        numericAlto(300), 
        'Excesso de processamento da linha de execução principal por códigos JavaScript de terceiros.',
        'Performance + Ads', 
        'Média', 
        'Adiar o carregamento de scripts de terceiros não essenciais e otimizar códigos JS próprios redundantes.',
        'Desenvolvedor', 
        '2–3 dias'
    );

    // 7. Falta de Meta Description
    analisarFalhaLighthouse(
        'meta-description', 
        scoreBaixo, 
        'Ausência ou configuração inadequada da meta tag "description" de SEO nas páginas principais.',
        'SEO Técnico', 
        'Alta', 
        'Escrever e configurar meta descriptions otimizadas com palavras-chave estratégicas para as principais landing pages.',
        'SEO Especialista', 
        '2–3 dias'
    );

    // --- NOVO: AUTO-POPULAÇÃO DE PROBLEMAS DE TAGS DE CONVERSÃO BASEADO NO CRAWLER ---
    if (tagsData && tagsData.ok) {
        const testarTag = (tagKey, tagNome, problemaTxt, acaoTxt, responsavelVal, prazoVal) => {
            const instalada = tagsData[tagKey];
            if (!instalada && !problemasAdicionados.has(problemaTxt)) {
                if (problemasAdicionados.size === 0) {
                    listaProblemas.innerHTML = '';
                    listaAcoes.innerHTML = '';
                }

                problemasAdicionados.add(problemaTxt);
                adicionarProblema(problemaTxt, 'Google Ads + Conversões', 'Alta');

                if (!acoesAdicionadas.has(acaoTxt)) {
                    acoesAdicionadas.add(acaoTxt);
                    adicionarAcao(acaoTxt, responsavelVal, prazoVal);
                }
            }
        };

        // 1. Google Tag Manager
        testarTag(
            'gtm', 
            'Google Tag Manager', 
            'Ausência da tag do Google Tag Manager (GTM), impedindo a gestão ágil de scripts.',
            'Instalar a tag do Google Tag Manager (GTM) no cabeçalho e corpo de todas as páginas.',
            'Desenvolvedor', 
            '1 dia'
        );

        // 2. Google Analytics (GA4)
        testarTag(
            'ga4', 
            'Google Analytics 4', 
            'Ausência do pixel do Google Analytics (GA4), impossibilitando mensurar o tráfego orgânico e pago.',
            'Criar fluxo de dados do Google Analytics (GA4) e configurar acionadores no GTM.',
            'Analista de Mídia', 
            '1–2 dias'
        );

        // 3. Pixel do Facebook (Meta)
        testarTag(
            'facebook', 
            'Pixel do Meta (Facebook)', 
            'Ausência do Pixel do Facebook/Meta, impedindo campanhas de remarketing de alta performance.',
            'Mapear e instalar o pixel do Meta Ads e seus eventos de conversão de leads e contatos via GTM.',
            'Analista de Mídia', 
            '1–2 dias'
        );

        // --- NOVO: AUDITORIA DE SEO PROFUNDO DO CRAWLER MULTIPÁGINAS ---
        const audit = tagsData.audit_summary;
        if (audit) {
            // A. Alt de Imagens Ausente
            if (audit.missing_alt_images > 0) {
                const prob = `Imagens sem atributo descritivo ALT. Identificamos que ${audit.missing_alt_images} de um total de ${audit.total_images} imagens auditadas em ${audit.pages_crawled} páginas internas do site não possuem descrição alternativa, o que prejudica a acessibilidade e o SEO.`;
                const acao = `Auditar a biblioteca de mídias e preencher o atributo descritivo ALT de todas as ${audit.missing_alt_images} imagens incompletas nas páginas listadas.`;
                if (!problemasAdicionados.has(prob)) {
                    if (problemasAdicionados.size === 0) { listaProblemas.innerHTML = ''; listaAcoes.innerHTML = ''; }
                    problemasAdicionados.add(prob);
                    adicionarProblema(prob, 'SEO de Imagens + Acessibilidade', 'Média');
                    adicionarAcao(acao, 'SEO Especialista', '2–3 dias');
                }
            }

            // B. URLs não amigáveis
            if (audit.unfriendly_urls > 0) {
                const prob = `Uso de URLs internas não amigáveis. Detectamos que ${audit.unfriendly_urls} links de páginas internas consultadas utilizam parâmetros dinâmicos de query strings em vez de estruturas semânticas limpas.`;
                const acao = `Implementar diretivas de reescrita de URL (URL rewriting) no servidor para manter todos os links internos legíveis e amigáveis para indexação.`;
                if (!problemasAdicionados.has(prob)) {
                    if (problemasAdicionados.size === 0) { listaProblemas.innerHTML = ''; listaAcoes.innerHTML = ''; }
                    problemasAdicionados.add(prob);
                    adicionarProblema(prob, 'SEO Técnico', 'Média');
                    adicionarAcao(acao, 'Desenvolvedor', '1–2 dias');
                }
            }

            // C. Viewport/Mobile
            if (audit.non_mobile_friendly > 0) {
                const prob = `Ausência de meta tag viewport para dispositivos móveis. Encontramos ${audit.non_mobile_friendly} páginas sem declaração apropriada de escala e largura para visualização em celulares.`;
                const acao = `Inserir a tag <meta name="viewport" content="width=device-width, initial-scale=1"> no cabeçalho <head> de todas as páginas internas identificadas.`;
                if (!problemasAdicionados.has(prob)) {
                    if (problemasAdicionados.size === 0) { listaProblemas.innerHTML = ''; listaAcoes.innerHTML = ''; }
                    problemasAdicionados.add(prob);
                    adicionarProblema(prob, 'UX + Compatibilidade Celular', 'Alta');
                    adicionarAcao(acao, 'Desenvolvedor', '1 dia');
                }
            }

            // D. Title tags ausentes/curtas/longas
            if (audit.missing_titles > 0) {
                const prob = `Títulos (Title Tags) inadequados ou ausentes detectados em ${audit.missing_titles} páginas internas, limitando o potencial de classificação orgânica.`;
                const acao = `Mapear e redigir títulos otimizados contendo palavras-chaves de 50 a 60 caracteres para as ${audit.missing_titles} páginas internas.`;
                if (!problemasAdicionados.has(prob)) {
                    if (problemasAdicionados.size === 0) { listaProblemas.innerHTML = ''; listaAcoes.innerHTML = ''; }
                    problemasAdicionados.add(prob);
                    adicionarProblema(prob, 'SEO Técnico', 'Alta');
                    adicionarAcao(acao, 'SEO Especialista', '2–3 dias');
                }
            }

            // E. Meta descriptions ausentes/curtas/longas
            if (audit.missing_descriptions > 0) {
                const prob = `Meta descrições (Meta Descriptions) ausentes ou fora do limite recomendado do Google em ${audit.missing_descriptions} páginas internas auditadas.`;
                const acao = `Escrever meta descriptions exclusivas e persuasivas de 120 a 155 caracteres para as ${audit.missing_descriptions} páginas identificadas.`;
                if (!problemasAdicionados.has(prob)) {
                    if (problemasAdicionados.size === 0) { listaProblemas.innerHTML = ''; listaAcoes.innerHTML = ''; }
                    problemasAdicionados.add(prob);
                    adicionarProblema(prob, 'SEO On-Page', 'Alta');
                    adicionarAcao(acao, 'SEO Especialista', '2–3 dias');
                }
            }

            // --- RENDERIZAR PAINEL DO CRAWLER EM form.php ---
            const panel = document.getElementById('crawlerResultsPanel');
            const statsGrid = document.getElementById('crawlerStatsGrid');
            const tableBody = document.getElementById('crawlerPagesTableBody');

            if (panel && statsGrid && tableBody) {
                // Injeta estatísticas
                statsGrid.innerHTML = `
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded bg-white text-center">
                      <strong class="text-primary fs-5 d-block">${audit.pages_crawled}</strong>
                      <span class="text-muted" style="font-size:0.75rem">Páginas Lidas</span>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded bg-white text-center">
                      <strong class="text-danger fs-5 d-block">${audit.missing_alt_images}</strong>
                      <span class="text-muted" style="font-size:0.75rem">Imagens s/ ALT</span>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded bg-white text-center">
                      <strong class="text-warning fs-5 d-block">${audit.unfriendly_urls}</strong>
                      <span class="text-muted" style="font-size:0.75rem">URLs s/ Amig.</span>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded bg-white text-center">
                      <strong class="text-danger fs-5 d-block">${audit.missing_titles + audit.missing_descriptions}</strong>
                      <span class="text-muted" style="font-size:0.75rem">Meta Tags c/ Erro</span>
                    </div>
                  </div>
                `;

                // Injeta linhas de detalhes das páginas
                tableBody.innerHTML = tagsData.pages.map(p => {
                    const badg = (val) => val ? '<span class="text-success fw-bold">Sim</span>' : '<span class="text-danger fw-bold">Não</span>';
                    const titleBadge = p.title_status === 'OK' ? '<span class="text-success fw-semibold">OK</span>' : `<span class="text-danger fw-semibold" title="${p.title}">${p.title_status}</span>`;
                    const descBadge = p.desc_status === 'OK' ? '<span class="text-success fw-semibold">OK</span>' : `<span class="text-danger fw-semibold" title="${p.description}">${p.desc_status}</span>`;
                    
                    return `
                      <tr>
                        <td class="text-truncate fw-semibold" style="max-width:140px" title="${p.url}">${p.url}</td>
                        <td>${titleBadge}</td>
                        <td>${descBadge}</td>
                        <td class="text-center">${p.missing_alt > 0 ? `<strong class="text-danger">${p.missing_alt}</strong>` : '0'}</td>
                        <td class="text-center">${badg(p.friendly_url)}</td>
                        <td class="text-center">${badg(p.mobile_friendly)}</td>
                      </tr>
                    `;
                }).join('');

                panel.classList.remove('d-none');
            }
        }
    }

    // Se adicionou algum problema automaticamente, mostra um aviso
    if (problemasAdicionados.size > 0) {
        showToast(`✨ Auditoria e Crawler concluídos! ${problemasAdicionados.size} problemas e planos de ação preenchidos automaticamente.`, 'success');
    }
}

// ─── GERADOR DE CONCLUSÃO TÉCNICO-COMERCIAL INTELIGENTE ────────
function gerarConclusaoInteligente() {
    const f = new FormData(document.getElementById('formRelatorio'));
    const cliente = f.get('cliente').trim() || '[Nome do Cliente]';
    const dominio = f.get('dominio').trim() || '[URL do Site]';
    const resultado = f.get('resultado_geral');
    
    // Coleta os problemas
    const problemas = getArrayItems('problemas');
    
    if (problemas.length === 0) {
        showToast('Adicione alguns problemas ao diagnóstico antes de gerar a conclusão!', 'warning');
        return;
    }

    const resUpper = resultado ? resultado.toUpperCase().trim() : 'MÉDIO';
    let texto = '';
    const listagemProblemas = problemas.map(p => p.problema.replace(/\.$/, '')).join('; ').toLowerCase();

    if (resUpper === 'BOM') {
        texto = `A análise técnica detalhada no site da empresa ${cliente} (${dominio}) revelou que o domínio encontra-se em um excelente estado de desempenho geral, muito bem alinhado com as boas práticas de otimização de velocidade e SEO técnico recomendadas pelo Google. Os dados coletados demonstram de forma objetiva que a página de destino oferece uma base sólida de estabilidade e tempo de resposta rápido, proporcionando uma excelente experiência inicial de navegação ao usuário.

Embora o site já ofereça uma excelente base de navegação e experiência para o usuário (UX), a auditoria identificou pequenos pontos de atenção e oportunidades de ajuste fino para consolidar sua excelência técnica: ${listagemProblemas}. A otimização contínua desses detalhes impedirá qualquer perda de performance futura e manterá o site em nível máximo.

Sob a ótica de Mídia Paga (Google Ads), essa excelente qualidade técnica é um valioso diferencial competitivo. Um site rápido e estável maximiza a pontuação do Índice de Qualidade do Google, reduzindo o Custo por Clique (CPC) e potencializando o retorno sobre o investimento (ROI) das campanhas. Implementar os pequenos ajustes descritos no plano de ação é altamente recomendado para blindar seu tráfego e garantir resultados comerciais excepcionais e de alta performance. A equipe da Rajo está inteiramente preparada para homologar esses ajustes finos de forma rápida e eficiente.`;
    } else if (resUpper === 'MÉDIO') {
        texto = `A análise técnica detalhada no site da empresa ${cliente} (${dominio}) revelou que o domínio encontra-se em um estado de desempenho médio para os padrões modernos exigidos pelo Google. Os dados coletados demonstram que, embora a página atenda a requisitos básicos de funcionamento, ela apresenta gargalos intermediários de carregamento e usabilidade que impedem o site de alcançar seu verdadeiro potencial comercial.

Dentre os principais pontos de atenção e melhorias técnicas mapeadas na auditoria, destacam-se: ${listagemProblemas}. Essas anomalias provocam oscilações perceptíveis de carregamento e pequenas frustrações na experiência de navegação (UX), gerando uma perda silenciosa, porém constante, de potenciais clientes e leads.

Sob a ótica de Mídia Paga (Google Ads), este desempenho intermediário impede a eficiência máxima das suas campanhas. O algoritmo do Google penaliza landing pages com pontuações medianas de velocidade e estabilidade visual, inflando desnecessariamente o Custo por Clique (CPC) e reduzindo a taxa de conversão final. Executar o plano de ação sugerido neste relatório é fundamental para otimizar os investimentos de publicidade online, reduzindo desperdícios e pavimentando o caminho para conversões de alto retorno. A equipe da Rajo está inteiramente de prontidão para executar e certificar todas as melhorias sugeridas.`;
    } else if (resUpper === 'RUIM') {
        texto = `A análise técnica detalhada no site da empresa ${cliente} (${dominio}) revelou que o domínio encontra-se em um estado de desempenho ruim e grave frente aos parâmetros técnicos atuais estabelecidos pelo Google. Os dados mostram de forma inequívoca que a página apresenta lentidão severa e problemas acentuados de carregamento, representando uma barreira severa na atração e retenção de usuários.

Os principais problemas técnicos e gargalos de performance identificados na auditoria são: ${listagemProblemas}. Essas deficiências estruturais provocam uma navegação frustrante e arrastada, elevando significativamente a taxa de rejeição, onde a maior parte dos visitantes abandona o site logo nos primeiros segundos, antes mesmo da exibição total do conteúdo.

Sob a ótica de Mídia Paga (Google Ads), este cenário é altamente prejudicial e insustentável. Anunciar com um site lento e problemático resulta em sérias punições no Índice de Qualidade, disparando o Custo por Clique (CPC) e destruindo a rentabilidade (ROI) dos seus anúncios. Corrigir com urgência as falhas apontadas no plano de ação é uma medida imediata e obrigatória para estancar a perda desnecessária de verba de marketing e viabilizar o retorno real das campanhas de tráfego. A equipe da Rajo está preparada para atuar de forma ágil na eliminação desses gargalos graves.`;
    } else { // CRÍTICO
        texto = `A análise técnica detalhada no site da empresa ${cliente} (${dominio}) revelou que o domínio encontra-se em um estado de desempenho extremamente crítico e alarmante. As medições oficiais via ferramentas do próprio Google comprovam de forma categórica que a landing page atual falha de maneira grave nos requisitos essenciais de velocidade, estabilidade visual e compatibilidade técnica, operando em nível de emergência.

Dentre os principais bloqueadores absolutos de renderização e graves anomalias estruturais detectadas na auditoria, destacam-se: ${listagemProblemas}. Essas graves falhas de infraestrutura tornam a navegação móvel e desktop praticamente inviável para o usuário final, gerando um abandono maciço do tráfego e impactando de forma destrutiva a imagem digital da empresa.

Sob a ótica de Mídia Paga (Google Ads), anunciar direcionando os usuários para este site em estado crítico significa desperdiçar completamente o seu orçamento de publicidade. O Google pune de forma extrema páginas lentas e instáveis, cobrando CPCs (Custos por Clique) abusivos e reduzindo drasticamente a veiculação das suas peças publicitárias. A reestruturação e correção imediata dos itens apontados no plano de ação técnica é de altíssima prioridade comercial para salvar suas campanhas ativas e estancar o desperdício financeiro de mídia. A Rajo já está inteiramente mobilizada para intervir de forma imediata neste cenário crítico, devolvendo a saúde e a velocidade necessárias para converter tráfego em vendas de alta performance.`;
    }

    // Injeta na Textarea
    const textarea = document.querySelector('[name="conclusao"]');
    if (textarea) {
        textarea.value = texto;
        // Rolagem suave até a textarea
        textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        textarea.focus();
        showToast('✨ Conclusão comercial inteligente redigida com sucesso!', 'success');
    }
}

// ─── Salvar Rascunho sem Validações Estritas ─────────────────
async function salvarRascunho() {
    const cliente = document.querySelector('[name="cliente"]').value.trim();
    if (!cliente) {
        showToast('Para salvar um rascunho, preencha pelo menos o Nome do Cliente no Passo 1!', 'warning');
        goStep(1);
        return;
    }

    showToast('Salvando rascunho...', 'info');

    try {
        const formData = new FormData(document.getElementById('formRelatorio'));
        const resp = await fetch('salvar.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            // Atualiza hidden id
            document.querySelector('[name="id"]').value = data.id;

            // Mostra os botões ocultos
            const btnOnline = document.getElementById('btnVerOnline');
            if (btnOnline) {
                btnOnline.href = `visualizar.php?id=${data.id}`;
                btnOnline.classList.remove('d-none');
            }
            const btnPdf = document.getElementById('btnGerarPdf');
            if (btnPdf) {
                btnPdf.href = data.pdf_url;
                btnPdf.classList.remove('d-none');
            }

            showToast('Rascunho de relatório salvo com sucesso!', 'success');
        } else {
            throw new Error(data.msg || 'Erro desconhecido');
        }
    } catch (err) {
        showToast(`Erro ao salvar rascunho: ${err.message}`, 'danger');
    }
}