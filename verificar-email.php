<?php
require 'config/database.php';
require 'config/auth.php';
require 'config/verificacao.php';

if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$pendente = verificacaoPendente('cadastro');

if (!$pendente) {
    header('Location: cadastro.php');
    exit;
}

$erro = $pendente['erro_envio'] != '' ? 'Não foi possível enviar o e-mail: ' . $pendente['erro_envio'] : '';
$aviso = '';
$codigoDev = $pendente['codigo_dev'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $acao = isset($_POST['acao']) ? $_POST['acao'] : 'verificar';

    if ($acao == 'reenviar') {

        $espera = segundosParaReenvio($pdo, $pendente['id'], 'cadastro');

        if ($espera > 0) {
            $erro = "Aguarde $espera segundos para pedir um novo código.";
        } else {
            $envio = enviarCodigoVerificacao($pdo, $pendente, 'cadastro');

            if ($envio['ok']) {
                $aviso = 'Enviamos um código novo para o seu e-mail.';
                $erro = '';
            } else {
                $erro = 'Não foi possível enviar o e-mail: ' . $envio['erro'];
            }

            $codigoDev = $envio['codigo'];
            $_SESSION['verificacao']['codigo_dev'] = $envio['codigo'];
            $_SESSION['verificacao']['erro_envio'] = '';
        }

    } else {

        $resultado = validarCodigoVerificacao($pdo, $pendente['id'], 'cadastro', $_POST['codigo']);

        if ($resultado['ok']) {
            $stmt = $pdo->prepare('UPDATE usuarios SET email_verificado = 1 WHERE id = ?');
            $stmt->execute([$pendente['id']]);

            limparVerificacaoPendente();

            $_SESSION['usuario_id'] = $pendente['id'];
            $_SESSION['usuario_nome'] = $pendente['nome'];
            header('Location: index.php');
            exit;
        }

        $erro = $resultado['erro'];
    }
}

$_SESSION['verificacao']['erro_envio'] = '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmar e-mail — TaskFlow</title>
<link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<main class="auth-page">
    <div class="auth-card">
        <div class="auth-card__brand">
            <img src="assets/img/logo.svg" alt="TaskFlow" class="topbar__logo">
            <h1>TaskFlow</h1>
        </div>
        <p class="auth-card__subtitulo">
            Enviamos um código de 6 dígitos para
            <strong><?= htmlspecialchars(mascararEmail($pendente['email'])) ?></strong>.
            Digite ele abaixo para confirmar sua conta.
        </p>

        <?php if ($erro != '') { ?>
            <p class="auth-erro"><?= htmlspecialchars($erro) ?></p>
        <?php } ?>

        <?php if ($aviso != '') { ?>
            <p class="auth-aviso"><?= htmlspecialchars($aviso) ?></p>
        <?php } ?>

        <?php if ($codigoDev != '') { ?>
            <p class="auth-dev">
                Modo de desenvolvimento (sem envio real de e-mail).<br>
                Seu código é <strong><?= htmlspecialchars($codigoDev) ?></strong>.
            </p>
        <?php } ?>

        <form method="post" class="auth-form">
            <label>
                Código de verificação
                <input type="text" name="codigo" class="auth-codigo" inputmode="numeric" pattern="[0-9]*"
                       maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
            </label>

            <button type="submit" class="btn btn--primary">Confirmar</button>
        </form>

        <form method="post" class="auth-form-secundario">
            <input type="hidden" name="acao" value="reenviar">
            <button type="submit" class="auth-link-btn">Reenviar código</button>
        </form>

        <p class="auth-card__rodape">Errou o e-mail? <a href="cadastro.php">Voltar ao cadastro</a></p>
    </div>
</main>

</body>
</html>
