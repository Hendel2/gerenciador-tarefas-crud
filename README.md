# TaskFlow — Gerenciador de Tarefas (CRUD)

Projeto de CRUD (Create, Read, Update, Delete) desenvolvido com **PHP + MySQL** no backend e **HTML, CSS e JavaScript puro** no frontend, consumindo uma API REST via `fetch`.

## Funcionalidades

- Cadastro e login de usuários (sessão PHP, senhas com `password_hash`/`password_verify`)
- Confirmação de e-mail no cadastro por código de 6 dígitos
- Recuperação de conta ("Esqueci minha senha") também por código enviado no e-mail
- Cada usuário só vê e gerencia as próprias tarefas
- Criar, listar, editar e excluir tarefas
- Filtro por status, prioridade e busca por texto (título/descrição)
- Painel com estatísticas (total, pendentes, em andamento, concluídas)
- Interface responsiva com modal de cadastro/edição

## Tecnologias

- PHP 8+ (PDO, prepared statements — proteção contra SQL Injection; sessões para autenticação)
- MySQL
- HTML5, CSS3, JavaScript (Fetch API, sem frameworks)

## Estrutura do projeto

```
gerenciador-tarefas-crud/
├── api/
│   └── tarefas.php        # API REST (GET, POST, PUT, DELETE) — protegida por login
├── config/
│   ├── database.php       # Conexão PDO com o MySQL
│   ├── auth.php           # Sessão e helpers de autenticação
│   ├── mail.php           # Configuração do envio de e-mails
│   ├── mailer.php         # Cliente SMTP próprio (sem biblioteca externa)
│   └── verificacao.php    # Regras dos códigos de verificação
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── schemas/
│   ├── database.sql                 # Criação do banco + dados de exemplo
│   └── migration_verificacao.sql    # Migração pra quem já tinha o banco criado
├── index.php                # Página principal (exige login)
├── login.php                # Página de login
├── cadastro.php             # Página de cadastro
├── verificar-email.php      # Confirmação do cadastro pelo código
├── esqueci-senha.php        # Pede o e-mail pra recuperar a conta
├── redefinir-senha.php      # Código + nova senha
├── logout.php               # Encerra a sessão
└── README.md
```

## Como rodar localmente

### 1. Pré-requisitos

Tenha um ambiente PHP + MySQL instalado, por exemplo o **[XAMPP](https://www.apachefriends.org/)** (mais simples no Windows) ou PHP + MySQL instalados separadamente.

### 2. Criar o banco de dados

Importe o arquivo `schemas/database.sql` no MySQL. Pode ser feito pelo phpMyAdmin (aba "Importar") ou via terminal:

```bash
mysql -u root -p < schemas/database.sql
```

Isso cria o banco `crud_tarefas`, as tabelas `usuarios`, `tarefas` e `codigos_verificacao`, um usuário de demonstração e algumas tarefas de exemplo.

> Se você **já tinha o banco criado** antes da verificação por e-mail, não recrie tudo: rode só a migração `schemas/migration_verificacao.sql` (ela adiciona a coluna `email_verificado`, cria a tabela `codigos_verificacao` e marca as contas antigas como já confirmadas).

Usuário de demonstração: `demo@taskflow.com` / senha `demo123` (ou crie sua própria conta pela tela de cadastro).

### 3. Configurar a conexão (se necessário)

Por padrão, `config/database.php` usa usuário `root` sem senha (padrão do XAMPP). Se seu MySQL tiver outro usuário/senha, edite:

```php
$user = 'root';
$pass = '';
```

### 4. Rodar o servidor

**Opção A — XAMPP:** copie a pasta do projeto para `htdocs`, inicie Apache e MySQL no painel do XAMPP, e acesse:

```
http://localhost/gerenciador-tarefas-crud
```

**Opção B — servidor embutido do PHP** (sem precisar de Apache), na pasta do projeto:

```bash
php -S localhost:8000
```

E acesse `http://localhost:8000`.

## Verificação por e-mail (cadastro e recuperação de senha)

Ao criar a conta, o sistema envia um código de 6 dígitos por e-mail e só libera o acesso depois que ele é digitado. O mesmo mecanismo é usado no "Esqueci minha senha".

Como funciona por dentro:

- O código vale **15 minutos**, aceita no máximo **5 tentativas** e só pode ser reenviado a cada **60 segundos**.
- No banco fica apenas o **hash** do código (`password_hash`), nunca o número em si.
- Cada código novo invalida o anterior, e um código usado não funciona de novo.
- Na tela de "Esqueci minha senha", o sistema responde igual para e-mail cadastrado ou não, pra não revelar quem tem conta no site.

### Configurando o envio de e-mail

O envio é controlado por `config/mail.php`, que aceita três modos:

| Modo | O que faz |
| --- | --- |
| `log` (padrão) | Não envia nada: grava o e-mail em `logs/emails.log` e mostra o código na própria tela. Serve pra testar tudo sem servidor de e-mail. |
| `smtp` | Envia de verdade por um servidor SMTP (Gmail, Brevo, Mailtrap...). |
| `mail` | Usa a função `mail()` do PHP (funciona em algumas hospedagens). |

O XAMPP não vem com servidor de e-mail, então localmente o padrão `log` já resolve: é só criar a conta e o código aparece na tela.

Pra enviar de verdade, copie `config/mail.local.php.example` para `config/mail.local.php` e preencha. Com Gmail, use uma **Senha de app** (gerada em https://myaccount.google.com/apppasswords, com verificação em duas etapas ligada) — a senha normal da conta não funciona:

```php
return [
    'metodo' => 'smtp',
    'remetente_email' => 'seu-email@gmail.com',
    'smtp_host'    => 'smtp.gmail.com',
    'smtp_porta'   => 587,
    'smtp_usuario' => 'seu-email@gmail.com',
    'smtp_senha'   => 'sua-senha-de-app',
    'smtp_seguranca' => 'tls',
    'mostrar_codigo_na_tela' => false,
];
```

Assim como o `database.local.php`, esse arquivo fica fora do Git pra senha não vazar no repositório.

Se o envio falhar, a mensagem de erro exata do servidor SMTP aparece na própria tela de verificação (ex.: senha recusada, host inacessível), o que ajuda a identificar rápido o que está faltando.

O envio SMTP é feito por um cliente próprio em `config/mailer.php` (com STARTTLS/SSL e `AUTH LOGIN`), sem Composer nem PHPMailer — o projeto continua sem dependências externas.

## Colocando no ar (hospedagem gratuita)

Pra acessar o sistema de qualquer lugar (inclusive pelo celular), dá pra usar uma hospedagem grátis com PHP + MySQL, como a [InfinityFree](https://infinityfree.net/).

1. Cria a conta e a hospedagem gratuita lá, e pega os dados de acesso **FTP** e **MySQL** que eles fornecem.
2. Sobe todos os arquivos do projeto via FTP.
3. Importa o `schemas/database.sql` pelo phpMyAdmin da hospedagem (se o banco de lá já existia, importa só o `schemas/migration_verificacao.sql`).
4. Copia `config/database.local.php.example` pra `config/database.local.php` e preenche com o host, banco, usuário e senha que a hospedagem te deu:

```php
<?php
$host = 'sqlXXX.infinityfree.com';
$dbname = 'epiz_XXXXXXXX_crud_tarefas';
$user = 'epiz_XXXXXXXX';
$pass = 'sua_senha_aqui';
```

O `config/database.php` carrega esse arquivo automaticamente se ele existir, sobrescrevendo os valores padrão (`root` sem senha) usados no XAMPP local. Esse arquivo fica de fora do Git (`.gitignore`) justamente pra não vazar a senha do banco de produção no repositório público — cada ambiente (seu PC e a hospedagem) tem o seu próprio.

