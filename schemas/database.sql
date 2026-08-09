-- Banco de dados do Gerenciador de Tarefas
CREATE DATABASE IF NOT EXISTS crud_tarefas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crud_tarefas;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Códigos de 6 dígitos enviados por e-mail (confirmação de cadastro e recuperação de senha)
CREATE TABLE IF NOT EXISTS codigos_verificacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('cadastro', 'recuperacao') NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    tentativas TINYINT NOT NULL DEFAULT 0,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    expira_em DATETIME NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_codigos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_codigos_usuario_tipo (usuario_id, tipo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    categoria VARCHAR(50) NOT NULL DEFAULT 'Geral',
    prioridade ENUM('baixa', 'media', 'alta') NOT NULL DEFAULT 'media',
    status ENUM('pendente', 'em_andamento', 'concluida') NOT NULL DEFAULT 'pendente',
    data_vencimento DATE NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tarefas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Usuário de demonstração (email: demo@taskflow.com / senha: demo123)
INSERT INTO usuarios (nome, email, senha_hash, email_verificado) VALUES
('Usuário Demo', 'demo@taskflow.com', '$2y$12$kppB8H3Dn6DrgGPGqBPTl.SibFJHDYTlSvoZS8Enf1u8qtY8AVXeK', 1);

-- Dados de exemplo (vinculados ao usuário demo)
INSERT INTO tarefas (usuario_id, titulo, descricao, categoria, prioridade, status, data_vencimento) VALUES
(1, 'Estudar PHP com PDO', 'Revisar prepared statements e conexão com MySQL', 'Estudos', 'alta', 'em_andamento', CURDATE() + INTERVAL 2 DAY),
(1, 'Montar portfólio', 'Adicionar este projeto de CRUD no GitHub', 'Carreira', 'alta', 'pendente', CURDATE() + INTERVAL 5 DAY),
(1, 'Revisar layout responsivo', 'Testar o sistema em telas pequenas', 'Front-end', 'media', 'pendente', CURDATE() + INTERVAL 7 DAY),
(1, 'Configurar ambiente local', 'Instalar XAMPP e criar o banco de dados', 'Infraestrutura', 'baixa', 'concluida', CURDATE() - INTERVAL 1 DAY);
