<?php

// Regras dos códigos de verificação de 6 dígitos enviados por e-mail.

require_once __DIR__ . '/mailer.php';

define('CODIGO_VALIDADE_MINUTOS', 15);   // tempo de vida do código
define('CODIGO_MAX_TENTATIVAS', 5);      // erros permitidos antes de invalidar
define('CODIGO_ESPERA_REENVIO', 60);     // segundos entre um envio e outro

/**
 * Gera um código novo, invalida os anteriores do mesmo tipo e devolve o código em texto puro.
 * No banco fica só o hash — nem quem tem acesso ao banco consegue ler o código.
 */
function criarCodigoVerificacao($pdo, $usuarioId, $tipo) {
    $stmt = $pdo->prepare('UPDATE codigos_verificacao SET usado = 1 WHERE usuario_id = ? AND tipo = ? AND usado = 0');
    $stmt->execute([$usuarioId, $tipo]);

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare(
        'INSERT INTO codigos_verificacao (usuario_id, tipo, codigo_hash, expira_em)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $stmt->execute([$usuarioId, $tipo, password_hash($codigo, PASSWORD_DEFAULT), CODIGO_VALIDADE_MINUTOS]);

    return $codigo;
}

/**
 * Quantos segundos ainda faltam pra poder pedir um código novo (0 = pode reenviar agora).
 */
function segundosParaReenvio($pdo, $usuarioId, $tipo) {
    $stmt = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, criado_em, NOW()) AS segundos
         FROM codigos_verificacao
         WHERE usuario_id = ? AND tipo = ?
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$usuarioId, $tipo]);
    $ultimo = $stmt->fetch();

    if (!$ultimo) {
        return 0;
    }

    $falta = CODIGO_ESPERA_REENVIO - (int) $ultimo['segundos'];
    return $falta > 0 ? $falta : 0;
}

/**
 * Confere o código digitado. Retorna ['ok' => bool, 'erro' => string].
 */
function validarCodigoVerificacao($pdo, $usuarioId, $tipo, $codigoDigitado) {
    $codigoDigitado = preg_replace('/\D/', '', $codigoDigitado);

    if (strlen($codigoDigitado) != 6) {
        return ['ok' => false, 'erro' => 'Digite os 6 números do código.'];
    }

    $stmt = $pdo->prepare(
        'SELECT id, codigo_hash, tentativas, expira_em < NOW() AS expirou
         FROM codigos_verificacao
         WHERE usuario_id = ? AND tipo = ? AND usado = 0
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$usuarioId, $tipo]);
    $registro = $stmt->fetch();

    if (!$registro) {
        return ['ok' => false, 'erro' => 'Nenhum código ativo. Clique em "Reenviar código".'];
    }

    if ($registro['expirou']) {
        return ['ok' => false, 'erro' => 'Esse código expirou. Clique em "Reenviar código".'];
    }

    if ($registro['tentativas'] >= CODIGO_MAX_TENTATIVAS) {
        $pdo->prepare('UPDATE codigos_verificacao SET usado = 1 WHERE id = ?')->execute([$registro['id']]);
        return ['ok' => false, 'erro' => 'Muitas tentativas erradas. Peça um código novo.'];
    }

    if (!password_verify($codigoDigitado, $registro['codigo_hash'])) {
        $stmt = $pdo->prepare('UPDATE codigos_verificacao SET tentativas = tentativas + 1 WHERE id = ?');
        $stmt->execute([$registro['id']]);

        $restantes = CODIGO_MAX_TENTATIVAS - ($registro['tentativas'] + 1);

        if ($restantes <= 0) {
            $pdo->prepare('UPDATE codigos_verificacao SET usado = 1 WHERE id = ?')->execute([$registro['id']]);
            return ['ok' => false, 'erro' => 'Muitas tentativas erradas. Peça um código novo.'];
        }

        return ['ok' => false, 'erro' => "Código incorreto. Você ainda tem $restantes tentativa(s)."];
    }

    $pdo->prepare('UPDATE codigos_verificacao SET usado = 1 WHERE id = ?')->execute([$registro['id']]);

    return ['ok' => true, 'erro' => ''];
}

/**
 * Gera o código e manda por e-mail. Retorna ['ok' => bool, 'erro' => string, 'codigo' => string].
 * O 'codigo' só volta preenchido no modo de desenvolvimento (mostrar_codigo_na_tela).
 */
function enviarCodigoVerificacao($pdo, $usuario, $tipo) {
    global $mailConfig;

    $codigo = criarCodigoVerificacao($pdo, $usuario['id'], $tipo);

    if ($tipo == 'cadastro') {
        $assunto = 'Seu código de confirmação — TaskFlow';
        $titulo = 'Confirme seu e-mail';
        $texto = 'Use o código abaixo para confirmar seu cadastro no TaskFlow.';
    } else {
        $assunto = 'Seu código para redefinir a senha — TaskFlow';
        $titulo = 'Redefinição de senha';
        $texto = 'Use o código abaixo para criar uma nova senha no TaskFlow.';
    }

    $nome = htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8');
    $minutos = CODIGO_VALIDADE_MINUTOS;

    $corpoHtml = "<!DOCTYPE html>
<html lang=\"pt-br\">
<body style=\"margin:0;padding:24px;background:#f4f6fb;font-family:'Segoe UI',Arial,sans-serif;color:#1f2433;\">
    <div style=\"max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;\">
        <h1 style=\"margin:0 0 4px;font-size:20px;color:#4f46e5;\">TaskFlow</h1>
        <h2 style=\"margin:0 0 16px;font-size:17px;\">$titulo</h2>
        <p style=\"margin:0 0 8px;font-size:15px;\">Olá, $nome!</p>
        <p style=\"margin:0 0 20px;font-size:15px;color:#6b7280;\">$texto</p>
        <div style=\"background:#f4f6fb;border-radius:12px;padding:18px;text-align:center;\">
            <span style=\"font-size:32px;font-weight:700;letter-spacing:8px;color:#1f2433;\">$codigo</span>
        </div>
        <p style=\"margin:20px 0 0;font-size:13px;color:#6b7280;\">O código vale por $minutos minutos. Se não foi você que pediu, é só ignorar este e-mail.</p>
    </div>
</body>
</html>";

    $corpoTexto = "TaskFlow — $titulo\n\n"
        . "Olá, {$usuario['nome']}!\n"
        . "$texto\n\n"
        . "Código: $codigo\n\n"
        . "O código vale por $minutos minutos. Se não foi você que pediu, ignore este e-mail.";

    $resultado = enviarEmail($usuario['email'], $usuario['nome'], $assunto, $corpoHtml, $corpoTexto);

    $mostrar = $mailConfig['metodo'] == 'log' && $mailConfig['mostrar_codigo_na_tela'];
    $resultado['codigo'] = $mostrar ? $codigo : '';

    return $resultado;
}

/**
 * Guarda na sessão quem está no meio de um fluxo de verificação.
 */
function iniciarVerificacaoPendente($usuario, $tipo) {
    $_SESSION['verificacao'] = [
        'id'          => $usuario['id'],
        'nome'        => $usuario['nome'],
        'email'       => $usuario['email'],
        'tipo'        => $tipo,
        'codigo_dev'  => '',
        'erro_envio'  => '',
    ];
}

function verificacaoPendente($tipo) {
    if (empty($_SESSION['verificacao']) || $_SESSION['verificacao']['tipo'] != $tipo) {
        return null;
    }

    // Garante as chaves opcionais pra não dar aviso de índice indefinido
    return array_merge(['codigo_dev' => '', 'erro_envio' => ''], $_SESSION['verificacao']);
}

function limparVerificacaoPendente() {
    unset($_SESSION['verificacao']);
}

/**
 * Esconde parte do e-mail na tela (jo***@gmail.com).
 */
function mascararEmail($email) {
    $partes = explode('@', $email);

    if (count($partes) != 2) {
        return $email;
    }

    $inicio = $partes[0];
    $visivel = mb_substr($inicio, 0, 2);

    if (mb_strlen($inicio) <= 2) {
        $visivel = mb_substr($inicio, 0, 1);
    }

    return $visivel . str_repeat('*', 3) . '@' . $partes[1];
}
