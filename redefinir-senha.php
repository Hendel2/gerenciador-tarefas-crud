<?php
require 'config/database.php';
require 'config/auth.php';
require 'config/verificacao.php';

if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$pendente = verificacaoPendente('recuperacao');

if (!$pendente) {
    header('Location: esqueci-senha.php');
    exit;
}

$erro = $pendente['erro_envio'] != '' ? 'Não foi possível enviar o e-mail: ' . $pendente['erro_envio'] : '';
$aviso = '';
$codigoDev = $pendente['codigo_dev'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $acao = isset($_POST['acao']) ? $_POST['acao'] : 'redefinir';

    if ($acao == 'reenviar') {

        $espera = $pendente['id'] > 0 ? segundosParaReenvio($pdo, $pendente['id'], 'recuperacao') : 0;

        if ($espera > 0) {
            $erro = "Aguarde $espera segundos para pedir um novo código.";
        } else {
            $aviso = 'Enviamos um código novo para o seu e-mail.';
            $erro = '';
            $codigoDev = '';

            if ($pendente['id'] > 0) {
                $envio = enviarCodigoVerificacao($pdo, $pendente, 'recuperacao');

                if (!$envio['ok']) {
                    $aviso = '';
                    $erro = 'Não foi possível enviar o e-mail: ' . $envio['erro'];
                }

                $codigoDev = $envio['codigo'];
            }

            $_SESSION['verificacao']['codigo_dev'] = $codigoDev;
            $_SESSION['verificacao']['erro_envio'] = '';
        }

    } else {

        $senha = $_POST['senha'];
        $confirmarSenha = $_POST['confirmar_senha'];

        if ($senha == '' || $confirmarSenha == '') {
            $erro = 'Preencha a nova senha e a confirmação.';
        } else if (strlen($senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
        } else if ($senha != $confirmarSenha) {
            $erro = 'As senhas não coincidem.';
        } else if ($pendente['id'] == 0) {
            // E-mail que não existe no banco — mensagem igual à de código errado
            $erro = 'Código incorreto ou expirado. Peça um novo código.';
        } else {

            $resultado = validarCodigoVerificacao($pdo, $pendente['id'], 'recuperacao', $_POST['codigo']);

            if ($resultado['ok']) {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                // Quem provou ter acesso ao e-mail também confirma a conta
                $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = ?, email_verificado = 1 WHERE id = ?');
                $stmt->execute([$senhaHash, $pendente['id']]);

                // Derruba qualquer outro código que ainda estivesse valendo
                $stmt = $pdo->prepare('UPDATE codigos_verificacao SET usado = 1 WHERE usuario_id = ? AND usado = 0');
                $stmt->execute([$pendente['id']]);

                limparVerificacaoPendente();

                $_SESSION['flash_sucesso'] = 'Senha alterada com sucesso. Faça login com a nova senha.';
                header('Location: login.php');
                exit;
            }

            $erro = $resultado['erro'];
        }
    }
}

$_SESSION['verificacao']['erro_envio'] = '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nova senha — TaskFlow</title>
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
            Se existir uma conta com o e-mail
            <strong><?= htmlspecialchars(mascararEmail($pendente['email'])) ?></strong>,
            o código de 6 dígitos já está a caminho. Digite ele e escolha a nova senha.
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

            <label>
                Nova senha
                <input type="password" name="senha" minlength="6" required>
            </label>

            <label>
                Confirmar nova senha
                <input type="password" name="confirmar_senha" minlength="6" required>
            </label>

            <button type="submit" class="btn btn--primary">Salvar nova senha</button>
        </form>

        <form method="post" class="auth-form-secundario">
            <input type="hidden" name="acao" value="reenviar">
            <button type="submit" class="auth-link-btn">Reenviar código</button>
        </form>

        <p class="auth-card__rodape">Errou o e-mail? <a href="esqueci-senha.php">Tentar outro</a></p>
    </div>
</main>

</body>
</html>
