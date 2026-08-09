<?php
require 'config/database.php';
require 'config/auth.php';
require 'config/verificacao.php';

if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$erro = '';

$sucesso = '';
if (!empty($_SESSION['flash_sucesso'])) {
    $sucesso = $_SESSION['flash_sucesso'];
    unset($_SESSION['flash_sucesso']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if ($email == '' || $senha == '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash, email_verificado FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash']) && !$usuario['email_verificado']) {
            // Conta criada mas nunca confirmada: manda um código novo e volta pra tela de verificação
            $envio = enviarCodigoVerificacao($pdo, $usuario, 'cadastro');

            iniciarVerificacaoPendente($usuario, 'cadastro');
            $_SESSION['verificacao']['codigo_dev'] = $envio['codigo'];
            $_SESSION['verificacao']['erro_envio'] = $envio['ok'] ? '' : $envio['erro'];

            header('Location: verificar-email.php');
            exit;
        }

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            header('Location: index.php');
            exit;
        } else {
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — TaskFlow</title>
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
        <p class="auth-card__subtitulo">Entre para gerenciar suas tarefas</p>

        <?php if ($sucesso != '') { ?>
            <p class="auth-aviso"><?= htmlspecialchars($sucesso) ?></p>
        <?php } ?>

        <?php if ($erro != '') { ?>
            <p class="auth-erro"><?= htmlspecialchars($erro) ?></p>
        <?php } ?>

        <form method="post" class="auth-form">
            <label>
                E-mail
                <input type="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required autofocus>
            </label>

            <label>
                Senha
                <input type="password" name="senha" required>
            </label>

            <button type="submit" class="btn btn--primary">Entrar</button>
        </form>

        <p class="auth-card__link-secundario"><a href="esqueci-senha.php">Esqueci minha senha</a></p>

        <p class="auth-card__rodape">Ainda não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
    </div>
</main>

</body>
</html>
