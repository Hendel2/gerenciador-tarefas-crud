# TaskFlow — Gerenciador de Tarefas

Aplicação web de gerenciamento de tarefas com autenticação de usuários e confirmação de e-mail por código. Construída com **PHP + MySQL** no backend e **HTML, CSS e JavaScript puro** no frontend, consumindo uma API REST própria via `fetch` — sem frameworks e sem dependências externas.

## Funcionalidades

**Tarefas**
- Criar, listar, editar e excluir tarefas (CRUD completo via API REST)
- Organização por categoria, prioridade (alta/média/baixa) e status (pendente/em andamento/concluída)
- Data de vencimento
- Filtro por status e prioridade, e busca por texto no título e na descrição
- Painel com contadores de total, pendentes, em andamento e concluídas
- Interface responsiva, com modal de criação e edição

**Contas**
- Cadastro e login com sessões PHP e senhas protegidas por `password_hash`
- Confirmação de e-mail no cadastro por código de 6 dígitos
- Recuperação de senha ("Esqueci minha senha") pelo mesmo mecanismo
- Cada usuário vê e gerencia apenas as próprias tarefas

## Tecnologias

| Camada | Stack |
| --- | --- |
| Backend | PHP 8+, PDO com prepared statements, sessões |
| Banco | MySQL 5.7+ / MariaDB |
| Frontend | HTML5, CSS3, JavaScript (Fetch API) |
| E-mail | Cliente SMTP próprio (STARTTLS/SSL + AUTH LOGIN), sem Composer ou PHPMailer |

## Estrutura

```
gerenciador-tarefas-crud/
├── api/
│   └── tarefas.php        # API REST (GET, POST, PUT, DELETE), protegida por login
├── config/
│   ├── database.php       # Conexão PDO com o MySQL
│   ├── auth.php           # Sessão e helpers de autenticação
│   ├── mail.php           # Configuração do envio de e-mails
│   ├── mailer.php         # Cliente SMTP próprio
│   └── verificacao.php    # Geração e validação dos códigos
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── img/logo.svg
├── schemas/
│   ├── database.sql                 # Criação do banco + dados de exemplo
│   └── migration_verificacao.sql    # Migração da verificação por e-mail
├── index.php              # Página principal (exige login)
├── login.php
├── cadastro.php
├── verificar-email.php    # Confirmação do cadastro pelo código
├── esqueci-senha.php
├── redefinir-senha.php    # Código + nova senha
└── logout.php
```

## Como rodar

### 1. Pré-requisitos

Um ambiente com **PHP 8+ e MySQL**. No Windows, o caminho mais simples é o [XAMPP](https://www.apachefriends.org/), que já traz os dois.

### 2. Clonar o projeto

```bash
git clone https://github.com/Hendel2/gerenciador-tarefas-crud.git
cd gerenciador-tarefas-crud
```

No XAMPP, clone dentro da pasta `htdocs`.

### 3. Criar o banco de dados

Importe `schemas/database.sql` pelo phpMyAdmin (aba **Importar**) ou pelo terminal:

```bash
mysql -u root -p < schemas/database.sql
```

Isso cria o banco `crud_tarefas` com as tabelas `usuarios`, `tarefas` e `codigos_verificacao`, além de uma conta de demonstração e algumas tarefas de exemplo.

### 4. Configurar a conexão

O padrão em `config/database.php` é o do XAMPP: usuário `root`, sem senha. **Se o seu MySQL usar outras credenciais**, não edite esse arquivo — copie o exemplo e preencha a cópia:

```bash
cp config/database.local.php.example config/database.local.php
```

```php
<?php
$host = 'localhost';
$dbname = 'crud_tarefas';
$user = 'seu_usuario';
$pass = 'sua_senha';
```

`config/database.php` carrega esse arquivo automaticamente quando ele existe e sobrescreve os valores padrão. Ele está no `.gitignore`, então suas credenciais nunca vão para o repositório.

### 5. Iniciar o servidor

**Com XAMPP:** inicie Apache e MySQL no painel e acesse `http://localhost/gerenciador-tarefas-crud`

**Com o servidor embutido do PHP**, na pasta do projeto:

```bash
php -S localhost:8000
```

E acesse `http://localhost:8000`.

### 6. Entrar

Use a conta de demonstração ou crie a sua pela tela de cadastro:

```
E-mail: demo@taskflow.com
Senha:  demo123
```

## Verificação de e-mail

Ao criar uma conta, o sistema envia um código de 6 dígitos por e-mail e só libera o acesso depois que ele é digitado. O mesmo fluxo é usado no "Esqueci minha senha".

Como funciona:

- O código vale **15 minutos**, aceita no máximo **5 tentativas** e só pode ser reenviado a cada **60 segundos**
- No banco fica apenas o **hash** do código (`password_hash`), nunca o número em si
- Cada código novo invalida o anterior, e um código já usado não funciona de novo
- Na tela de "Esqueci minha senha", a resposta é idêntica para e-mail cadastrado ou não, para não revelar quem tem conta no sistema

### Modos de envio

O envio é controlado por `config/mail.php` e aceita três modos:

| Modo | O que faz |
| --- | --- |
| `log` *(padrão)* | Não envia nada: grava o e-mail em `logs/emails.log` e mostra o código na própria tela |
| `smtp` | Envia de verdade por um servidor SMTP (Gmail, Brevo, Mailtrap...) |
| `mail` | Usa a função `mail()` do PHP |

**Para testar localmente não é preciso configurar nada.** O XAMPP não vem com servidor de e-mail, então o modo `log` já resolve: você cria a conta e o código aparece na tela.

Para enviar e-mails de verdade, copie o exemplo e preencha:

```bash
cp config/mail.local.php.example config/mail.local.php
```

```php
return [
    'metodo' => 'smtp',
    'remetente_email' => 'seu-email@gmail.com',
    'smtp_host'      => 'smtp.gmail.com',
    'smtp_porta'     => 587,
    'smtp_usuario'   => 'seu-email@gmail.com',
    'smtp_senha'     => 'sua-senha-de-app',
    'smtp_seguranca' => 'tls',
    'mostrar_codigo_na_tela' => false,
];
```

Com o Gmail, é preciso usar uma **Senha de app** (gerada em [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords), com verificação em duas etapas ativa) — a senha normal da conta não funciona. Assim como o `database.local.php`, esse arquivo fica fora do Git.

## Segurança

- Todas as queries usam **prepared statements** via PDO, com `ATTR_EMULATE_PREPARES` desligado — proteção contra SQL Injection
- Senhas e códigos de verificação são armazenados apenas como hash (`password_hash` / `password_verify`)
- Toda saída de dados do usuário passa por `htmlspecialchars` no PHP e por escape no JavaScript — proteção contra XSS
- Cada query de tarefa é filtrada por `usuario_id` da sessão, impedindo acesso às tarefas de outro usuário
- Credenciais ficam em arquivos `*.local.php` fora do controle de versão
- A pasta `logs/` é bloqueada por `.htaccess`

## Licença

Distribuído sob a licença MIT. Veja [LICENSE](LICENSE) para mais detalhes.
