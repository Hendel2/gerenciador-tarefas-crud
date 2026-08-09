<?php

// Envio de e-mails sem biblioteca externa: um cliente SMTP simples,
// com fallback pra função mail() do PHP e pro modo de log (desenvolvimento).

require_once __DIR__ . '/mail.php';

/**
 * Envia um e-mail em HTML.
 * Retorna ['ok' => bool, 'erro' => string].
 */
function enviarEmail($paraEmail, $paraNome, $assunto, $corpoHtml, $corpoTexto) {
    global $mailConfig;

    $metodo = $mailConfig['metodo'];

    if ($metodo == 'smtp') {
        return enviarEmailSmtp($paraEmail, $paraNome, $assunto, $corpoHtml, $corpoTexto);
    }

    if ($metodo == 'mail') {
        return enviarEmailFuncaoMail($paraEmail, $paraNome, $assunto, $corpoHtml);
    }

    return registrarEmailNoLog($paraEmail, $assunto, $corpoTexto);
}

/**
 * Modo desenvolvimento: grava o e-mail em logs/emails.log em vez de enviar.
 */
function registrarEmailNoLog($paraEmail, $assunto, $corpoTexto) {
    global $mailConfig;

    $arquivo = $mailConfig['log_arquivo'];
    $pasta = dirname($arquivo);

    if (!is_dir($pasta)) {
        @mkdir($pasta, 0777, true);
    }

    $linha = str_repeat('=', 60) . "\n"
        . '[' . date('d/m/Y H:i:s') . "] Para: $paraEmail\n"
        . "Assunto: $assunto\n"
        . str_repeat('-', 60) . "\n"
        . $corpoTexto . "\n\n";

    if (@file_put_contents($arquivo, $linha, FILE_APPEND) === false) {
        return ['ok' => false, 'erro' => 'Não foi possível gravar o log de e-mails.'];
    }

    return ['ok' => true, 'erro' => ''];
}

/**
 * Envio pela função mail() do PHP (depende do servidor estar configurado).
 */
function enviarEmailFuncaoMail($paraEmail, $paraNome, $assunto, $corpoHtml) {
    global $mailConfig;

    $de = $mailConfig['remetente_nome'] . ' <' . $mailConfig['remetente_email'] . '>';

    $cabecalhos = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "From: $de\r\n";

    $assuntoCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

    if (@mail($paraEmail, $assuntoCodificado, $corpoHtml, $cabecalhos)) {
        return ['ok' => true, 'erro' => ''];
    }

    return ['ok' => false, 'erro' => 'A função mail() do PHP não conseguiu enviar o e-mail.'];
}

/**
 * Envio por SMTP (com STARTTLS ou SSL) e autenticação AUTH LOGIN.
 */
function enviarEmailSmtp($paraEmail, $paraNome, $assunto, $corpoHtml, $corpoTexto) {
    global $mailConfig;

    $host = $mailConfig['smtp_host'];
    $porta = (int) $mailConfig['smtp_porta'];
    $seguranca = strtolower($mailConfig['smtp_seguranca']);
    $timeout = (int) $mailConfig['smtp_timeout'];

    if ($host == '') {
        return ['ok' => false, 'erro' => 'SMTP não configurado (smtp_host vazio).'];
    }

    $endereco = ($seguranca == 'ssl' ? 'ssl://' : '') . $host . ':' . $porta;

    $socket = @stream_socket_client($endereco, $codigoErro, $mensagemErro, $timeout);
    if (!$socket) {
        return ['ok' => false, 'erro' => "Não foi possível conectar em $host:$porta ($mensagemErro)."];
    }

    stream_set_timeout($socket, $timeout);

    $resposta = lerRespostaSmtp($socket);
    if (substr($resposta, 0, 1) != '2') {
        fclose($socket);
        return ['ok' => false, 'erro' => 'Servidor SMTP recusou a conexão: ' . trim($resposta)];
    }

    $dominio = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';

    $passo = enviarComandoSmtp($socket, "EHLO $dominio", '2');
    if (!$passo['ok']) {
        fclose($socket);
        return $passo;
    }

    if ($seguranca == 'tls') {
        $passo = enviarComandoSmtp($socket, 'STARTTLS', '2');
        if (!$passo['ok']) {
            fclose($socket);
            return $passo;
        }

        $cripto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cripto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cripto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        if (!@stream_socket_enable_crypto($socket, true, $cripto)) {
            fclose($socket);
            return ['ok' => false, 'erro' => 'Falha ao iniciar a criptografia TLS com o servidor SMTP.'];
        }

        // Depois do STARTTLS o EHLO precisa ser repetido
        $passo = enviarComandoSmtp($socket, "EHLO $dominio", '2');
        if (!$passo['ok']) {
            fclose($socket);
            return $passo;
        }
    }

    if ($mailConfig['smtp_usuario'] != '') {
        $passo = enviarComandoSmtp($socket, 'AUTH LOGIN', '3');
        if (!$passo['ok']) {
            fclose($socket);
            return $passo;
        }

        $passo = enviarComandoSmtp($socket, base64_encode($mailConfig['smtp_usuario']), '3');
        if (!$passo['ok']) {
            fclose($socket);
            return $passo;
        }

        $passo = enviarComandoSmtp($socket, base64_encode($mailConfig['smtp_senha']), '2');
        if (!$passo['ok']) {
            fclose($socket);
            return ['ok' => false, 'erro' => 'Usuário ou senha do SMTP recusados pelo servidor.'];
        }
    }

    $remetente = $mailConfig['remetente_email'];

    $passo = enviarComandoSmtp($socket, "MAIL FROM:<$remetente>", '2');
    if (!$passo['ok']) {
        fclose($socket);
        return $passo;
    }

    $passo = enviarComandoSmtp($socket, "RCPT TO:<$paraEmail>", '2');
    if (!$passo['ok']) {
        fclose($socket);
        return $passo;
    }

    $passo = enviarComandoSmtp($socket, 'DATA', '3');
    if (!$passo['ok']) {
        fclose($socket);
        return $passo;
    }

    $mensagem = montarMensagemSmtp($paraEmail, $paraNome, $assunto, $corpoHtml, $corpoTexto);

    fwrite($socket, $mensagem . "\r\n.\r\n");
    $resposta = lerRespostaSmtp($socket);

    if (substr($resposta, 0, 1) != '2') {
        fclose($socket);
        return ['ok' => false, 'erro' => 'O servidor SMTP recusou a mensagem: ' . trim($resposta)];
    }

    enviarComandoSmtp($socket, 'QUIT', '2');
    fclose($socket);

    return ['ok' => true, 'erro' => ''];
}

function enviarComandoSmtp($socket, $comando, $codigoEsperado) {
    fwrite($socket, $comando . "\r\n");
    $resposta = lerRespostaSmtp($socket);

    if (substr($resposta, 0, 1) != $codigoEsperado) {
        // Não expõe a linha do comando (pode conter credenciais em base64)
        return ['ok' => false, 'erro' => 'Erro na conversa com o servidor SMTP: ' . trim($resposta)];
    }

    return ['ok' => true, 'erro' => ''];
}

function lerRespostaSmtp($socket) {
    $resposta = '';

    while ($linha = fgets($socket, 515)) {
        $resposta .= $linha;
        // Em respostas de várias linhas o código vem com "-" (ex.: "250-"),
        // a última linha vem com espaço ("250 ").
        if (strlen($linha) < 4 || substr($linha, 3, 1) == ' ') {
            break;
        }
    }

    return $resposta;
}

function montarMensagemSmtp($paraEmail, $paraNome, $assunto, $corpoHtml, $corpoTexto) {
    global $mailConfig;

    $limite = '=_taskflow_' . bin2hex(random_bytes(8));

    $de = codificarCabecalhoEmail($mailConfig['remetente_nome']) . ' <' . $mailConfig['remetente_email'] . '>';
    $para = codificarCabecalhoEmail($paraNome) . " <$paraEmail>";

    $cabecalhos = [
        'Date: ' . date('r'),
        'From: ' . $de,
        'To: ' . $para,
        'Subject: ' . codificarCabecalhoEmail($assunto),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@taskflow>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $limite . '"',
    ];

    $corpo = "--$limite\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($corpoTexto)) . "\r\n"
        . "--$limite\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($corpoHtml)) . "\r\n"
        . "--$limite--";

    $mensagem = implode("\r\n", $cabecalhos) . "\r\n\r\n" . $corpo;

    // Protege contra linhas que comecem com "." (fim de dados no SMTP)
    return str_replace("\r\n.", "\r\n..", $mensagem);
}

function codificarCabecalhoEmail($texto) {
    if (preg_match('/^[\x20-\x7E]*$/', $texto)) {
        return '"' . str_replace('"', '', $texto) . '"';
    }

    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}
