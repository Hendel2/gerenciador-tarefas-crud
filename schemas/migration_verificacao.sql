-- Migração: verificação de e-mail por código (cadastro e recuperação de senha)
-- Rode este script se você já tinha o banco criado antes dessa funcionalidade.
-- Numa hospedagem (InfinityFree, etc), o banco tem outro nome: importe pelo phpMyAdmin
-- já com o banco selecionado e apague a linha USE abaixo.
USE crud_tarefas;

-- Marca se o e-mail do usuário já foi confirmado pelo código enviado
ALTER TABLE usuarios
    ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 0 AFTER senha_hash;

-- Contas que já existiam continuam funcionando normalmente
UPDATE usuarios SET email_verificado = 1;

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
