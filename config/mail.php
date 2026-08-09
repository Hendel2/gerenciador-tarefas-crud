<?php

// Configuração do envio de e-mails (códigos de verificação).
//
// metodo:
//   'log'  -> não envia nada de verdade, só grava o e-mail em logs/emails.log.
//             É o padrão, porque o XAMPP não vem com servidor de e-mail configurado.
//   'smtp' -> envia de verdade por um servidor SMTP (Gmail, Brevo, Mailtrap, etc).
//   'mail' -> usa a função mail() do PHP (funciona em algumas hospedagens).
//
// Para enviar de verdade, copie config/mail.local.php.example para
// config/mail.local.php e preencha lá — esse arquivo fica fora do Git.

$mailConfig = [
    'metodo' => 'log',

    'remetente_email' => 'nao-responda@taskflow.local',
    'remetente_nome'  => 'TaskFlow',

    'smtp_host'      => '',
    'smtp_porta'     => 587,
    'smtp_usuario'   => '',
    'smtp_senha'     => '',
    'smtp_seguranca' => 'tls', // 'tls' (porta 587), 'ssl' (porta 465) ou '' (sem criptografia)
    'smtp_timeout'   => 15,

    'log_arquivo' => __DIR__ . '/../logs/emails.log',

    // No metodo 'log', mostra o código na própria tela pra dar pra testar o fluxo
    // sem servidor de e-mail. Deixe false em produção.
    'mostrar_codigo_na_tela' => true,
];

if (file_exists(__DIR__ . '/mail.local.php')) {
    $mailLocal = require __DIR__ . '/mail.local.php';
    if (is_array($mailLocal)) {
        $mailConfig = array_merge($mailConfig, $mailLocal);
    }
}
