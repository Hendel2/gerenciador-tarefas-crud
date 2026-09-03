<?php
require 'config/database.php';
require 'config/auth.php';
require 'config/verificacao.php';

if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = trim($_POST['email']);

    if ($email == '') {
        $erro = 'Informe o seu e-mail.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {

        $stmt = $pdo->prepare('SELECT id, nome, email FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $envio = enviarCodigoVerificacao($pdo, $usuario, 'recuperacao');

            iniciarVerificacaoPendente($usuario, 'recuperacao');
            $_SESSION['verificacao']['codigo_dev'] = $envio['codigo'];
            $_SESSION['verificacao']['erro_envio'] = $envio['ok'] ? '' : $envio['erro'];
        } else {
            iniciarVerificacaoPendente(['id' => 0, 'nome' => '', 'email' => $email], 'recuperacao');
        }

        header('Location: redefinir-senha.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar conta — TaskFlow</title>
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
            Informe o e-mail da sua conta. Vamos enviar um código de 6 dígitos para você criar uma nova senha.
        </p>

        <?php if ($erro != '') { ?>
            <p class="auth-erro"><?= htmlspecialchars($erro) ?></p>
        <?php } ?>

        <form method="post" class="auth-form">
            <label>
                E-mail
                <input type="email" name="email" maxlength="150" value="<?= htmlspecialchars($email) ?>" required autofocus>
            </label>

            <button type="submit" class="btn btn--primary">Enviar código</button>
        </form>

        <p class="auth-card__rodape">Lembrou a senha? <a href="login.php">Entrar</a></p>
    </div>
</main>

</body>
</html>
