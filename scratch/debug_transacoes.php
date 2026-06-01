<?php
require_once dirname(__DIR__) . '/config.php';

try {
    echo "=== TESTE DE DEBBUG FINANCEIRO E AVISOS ===\n\n";

    // 1. Cria usuário de teste se não existir
    $email = 'analista_teste@rajo.com.br';
    $stmt = db()->prepare("SELECT id, saldo FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt_i = db()->prepare("INSERT INTO usuarios (nome, email, senha, confirmado, tipo, saldo) VALUES ('Analista Teste', ?, 'senha123', 1, 'comum', 0.00)");
        $stmt_i->execute([$email]);
        $user_id = db()->lastInsertId();
        $saldo_inicial = 0.00;
        echo "[+] Criado usuário de teste ID: {$user_id}\n";
    } else {
        $user_id = (int)$user['id'];
        $saldo_inicial = (float)$user['saldo'];
        echo "[+] Usuário de teste já existe ID: {$user_id} | Saldo Atual: R$ {$saldo_inicial}\n";
    }

    // 2. Cria transação pendente de teste
    $stmt_t = db()->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, descricao, status) VALUES (?, 'recarga', 150.00, 'Recarga teste pendente', 'pendente')");
    $stmt_t->execute([$user_id]);
    $trans_id = db()->lastInsertId();
    echo "[+] Criada transação pendente de teste ID: {$trans_id} | Valor: R$ 150.00\n";

    // 3. Simula a lógica de APROVAR RECARGA exata de admin.php
    echo "[*] Simulando aprovação da transação {$trans_id}...\n";
    
    $stmt_sel = db()->prepare("SELECT usuario_id, valor, status FROM transacoes WHERE id = ?");
    $stmt_sel->execute([$trans_id]);
    $trans = $stmt_sel->fetch();

    if (!$trans) {
        echo "[-] Erro: transação não encontrada.\n";
    } elseif ($trans['status'] !== 'pendente') {
        echo "[-] Erro: transação já processada.\n";
    } else {
        db()->beginTransaction();
        try {
            $upd_t = db()->prepare("UPDATE transacoes SET status = 'concluido' WHERE id = ?");
            $upd_t->execute([$trans_id]);
            
            $valor_recarga = (float)$trans['valor'];
            $upd_u = db()->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
            $upd_u->execute([$valor_recarga, $trans['usuario_id']]);
            
            db()->commit();
            echo "[+] Sucesso: Transação aprovada e saldo creditado!\n";
        } catch (Throwable $ex) {
            db()->rollBack();
            echo "[-] Erro na transação SQL: " . $ex->getMessage() . "\n";
        }
    }

    // Verifica se o saldo aumentou
    $stmt_v = db()->prepare("SELECT saldo FROM usuarios WHERE id = ?");
    $stmt_v->execute([$user_id]);
    $saldo_final = (float)$stmt_v->fetchColumn();
    echo "[+] Saldo Inicial: R$ {$saldo_inicial} | Saldo Final: R$ {$saldo_final}\n";
    if (abs($saldo_final - ($saldo_inicial + 150.00)) < 0.01) {
        echo "[SUCCESS] APROVAÇÃO FUNCIONA 100% LOCALMENTE!\n\n";
    } else {
        echo "[ERROR] Falha na validação do saldo!\n\n";
    }

    // 4. Cria aviso de teste
    $stmt_a = db()->prepare("INSERT INTO avisos (titulo, mensagem, tipo, ativo) VALUES ('Aviso Teste', 'Mensagem teste', 'info', 1)");
    $stmt_a->execute();
    $aviso_id = db()->lastInsertId();
    echo "[+] Criado aviso de teste ID: {$aviso_id}\n";

    // Simula lógica de exclusão
    echo "[*] Simulando exclusão do aviso {$aviso_id}...\n";
    $stmt_d = db()->prepare("DELETE FROM avisos WHERE id = ?");
    $stmt_d->execute([$aviso_id]);
    
    // Verifica se foi excluído
    $stmt_c = db()->prepare("SELECT COUNT(*) FROM avisos WHERE id = ?");
    $stmt_c->execute([$aviso_id]);
    $count = (int)$stmt_c->fetchColumn();
    if ($count === 0) {
        echo "[SUCCESS] EXCLUSÃO DE AVISO FUNCIONA 100% LOCALMENTE!\n\n";
    } else {
        echo "[ERROR] Falha na exclusão do aviso!\n\n";
    }

} catch (Throwable $e) {
    echo "[-] Erro geral: " . $e->getMessage() . "\n";
}
