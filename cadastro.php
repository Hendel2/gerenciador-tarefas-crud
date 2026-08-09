<?php
require 'config/database.php';
require 'config/auth.php';
require 'config/verificacao.php';

if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$nome = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmarSenha = $_POST['confirmar_senha'];

    if ($nome == '' || $email == '' || $senha == '') {
        $erro = 'Preencha todos os campos.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else if (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } else if ($senha != $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {

        $stmt = $pdo->prepare('SELECT id, email_verificado FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $existe = $stmt->fetch();

        if ($existe && $existe['email_verificado']) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            if ($existe) {
                // Cadastro que ficou pela metade (e-mail nunca confirmado): reaproveita o registro
                $usuarioId = $existe['id'];
                $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, senha_hash = ? WHERE id = ?');
                $stmt->execute([$nome, $senhaHash, $usuarioId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, email_verificado) VALUES (?, ?, ?, 0)');
                $stmt->execute([$nome, $email, $senhaHash]);
                $usuarioId = $pdo->lastInsertId();
            }

            $usuario = ['id' => $usuarioId, 'nome' => $nome, 'email' => $email];

            $envio = enviarCodigoVerificacao($pdo, $usuario, 'cadastro');

            iniciarVerificacaoPendente($usuario, 'cadastro');
            $_SESSION['verificacao']['codigo_dev'] = $envio['codigo'];
            $_SESSION['verificacao']['erro_envio'] = $envio['ok'] ? '' : $envio['erro'];

            header('Location: verificar-email.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar conta — TaskFlow</title>
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
        <p class="auth-card__subtitulo">Crie sua conta para começar</p>

        <?php if ($erro != '') { ?>
            <p class="auth-erro"><?= htmlspecialchars($erro) ?></p>
        <?php } ?>

        <form method="post" class="auth-form">
            <label>
                Nome
                <input type="text" name="nome" maxlength="100" value="<?= htmlspecialchars($nome) ?>" required autofocus>
            </label>

            <label>
                E-mail
                <input type="email" name="email" maxlength="150" value="<?= htmlspecialchars($email) ?>" required>
            </label>

            <label>
                Senha
                <input type="password" name="senha" minlength="6" required>
            </label>

            <label>
                Confirmar senha
                <input type="password" name="confirmar_senha" minlength="6" required>
            </label>

            <button type="submit" class="btn btn--primary">Cadastrar</button>
        </form>

        <p class="auth-card__rodape">Já tem conta? <a href="login.php">Entrar</a></p>
    </div>
</main>

</body>
</html>
