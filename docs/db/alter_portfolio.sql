-- =============================================================================
-- alter_portfolio.sql
-- Permite que clientes enviem fotos de inspiração ao fazer um pedido.
-- Corre este ficheiro uma só vez no phpMyAdmin (BD sylviartes → SQL).
-- =============================================================================

-- Tabela que guarda as fotos de inspiração enviadas pelos clientes
-- (até 3 por pedido, guardadas como BLOB na base de dados)
CREATE TABLE IF NOT EXISTS pedido_inspiracao (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id  INT NOT NULL,
    imagem     MEDIUMBLOB NOT NULL,         -- foto em binário (max ~16MB)
    ordem      TINYINT NOT NULL DEFAULT 1,  -- ordem de apresentação (1, 2, 3)
    CONSTRAINT fk_pi_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedido(id) ON DELETE CASCADE
);

-- Referência opcional ao produto do portfólio que serviu de inspiração
ALTER TABLE pedido
  ADD COLUMN IF NOT EXISTS portfolio_inspiracao_id INT NULL;
