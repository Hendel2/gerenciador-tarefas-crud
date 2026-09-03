<?php

$mailConfig = [
    'metodo' => 'log',

    'remetente_email' => 'nao-responda@taskflow.local',
    'remetente_nome'  => 'TaskFlow',

    'smtp_host'      => '',
    'smtp_porta'     => 587,
    'smtp_usuario'   => '',
    'smtp_senha'     => '',
    'smtp_seguranca' => 'tls',
    'smtp_timeout'   => 15,

    'log_arquivo' => __DIR__ . '/../logs/emails.log',

    'mostrar_codigo_na_tela' => true,
];

if (file_exists(__DIR__ . '/mail.local.php')) {
    $mailLocal = require __DIR__ . '/mail.local.php';
    if (is_array($mailLocal)) {
        $mailConfig = array_merge($mailConfig, $mailLocal);
    }
}
