<?php
require 'config/auth.php';
exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaskFlow — Gerenciador de Tarefas</title>
<link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar__brand">
        <img src="assets/img/logo.svg" alt="TaskFlow" class="topbar__logo">
        <h1>TaskFlow</h1>
    </div>
    <div class="topbar__acoes">
        <span class="topbar__usuario">Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?></span>
        <button class="btn btn--primary" id="btnNovaTarefa">+ Nova tarefa</button>
        <a class="btn btn--secundario" href="logout.php">Sair</a>
    </div>
</header>

<main class="container">

    <section class="stats" id="stats">
        <div class="stat-card">
            <span class="stat-card__valor" id="statTotal">0</span>
            <span class="stat-card__label">Total</span>
        </div>
        <div class="stat-card stat-card--pendente">
            <span class="stat-card__valor" id="statPendente">0</span>
            <span class="stat-card__label">Pendentes</span>
        </div>
        <div class="stat-card stat-card--andamento">
            <span class="stat-card__valor" id="statAndamento">0</span>
            <span class="stat-card__label">Em andamento</span>
        </div>
        <div class="stat-card stat-card--concluida">
            <span class="stat-card__valor" id="statConcluida">0</span>
            <span class="stat-card__label">Concluídas</span>
        </div>
    </section>

    <section class="filtros">
        <input type="search" id="filtroBusca" placeholder="Buscar por título ou descrição...">

        <select id="filtroStatus">
            <option value="">Todos os status</option>
            <option value="pendente">Pendente</option>
            <option value="em_andamento">Em andamento</option>
            <option value="concluida">Concluída</option>
        </select>

        <select id="filtroPrioridade">
            <option value="">Todas as prioridades</option>
            <option value="alta">Alta</option>
            <option value="media">Média</option>
            <option value="baixa">Baixa</option>
        </select>
    </section>

    <section class="lista-tarefas" id="listaTarefas">
    </section>

    <p class="vazio" id="mensagemVazia" hidden>Nenhuma tarefa encontrada. Crie a primeira clicando em "Nova tarefa".</p>

</main>

<div class="modal-overlay" id="modalOverlay" hidden>
    <div class="modal">
        <div class="modal__header">
            <h2 id="modalTitulo">Nova tarefa</h2>
            <button class="modal__fechar" id="btnFecharModal" type="button">&times;</button>
        </div>

        <form id="formTarefa">
            <input type="hidden" id="tarefaId">

            <label>
                Título *
                <input type="text" id="campoTitulo" maxlength="150" required>
            </label>

            <label>
                Descrição
                <textarea id="campoDescricao" rows="3"></textarea>
            </label>

            <div class="form-row">
                <label>
                    Categoria
                    <input type="text" id="campoCategoria" maxlength="50" placeholder="Geral">
                </label>

                <label>
                    Data de vencimento
                    <input type="date" id="campoData">
                </label>
            </div>

            <div class="form-row">
                <label>
                    Prioridade
                    <select id="campoPrioridade">
                        <option value="baixa">Baixa</option>
                        <option value="media" selected>Média</option>
                        <option value="alta">Alta</option>
                    </select>
                </label>

                <label>
                    Status
                    <select id="campoStatus">
                        <option value="pendente" selected>Pendente</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="concluida">Concluída</option>
                    </select>
                </label>
            </div>

            <div class="modal__acoes">
                <button type="button" class="btn btn--secundario" id="btnCancelar">Cancelar</button>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
