-- =============================================================================
-- setup_completo.sql  —  SylviArtes
-- =============================================================================
-- Corre ESTE ficheiro para configurar tudo de uma vez.
-- phpMyAdmin → BD sylviartes → aba SQL → colar → Executar
--
-- É seguro correr mais do que uma vez (IF NOT EXISTS / IF NOT EXISTS).
-- =============================================================================


-- -----------------------------------------------------------------------------
-- FIX CRÍTICO: Adiciona 'aguarda_orcamento' ao ENUM do pedido
-- Sem isto os pedidos feitos pelo formulário público NÃO são guardados.
-- -----------------------------------------------------------------------------
ALTER TABLE pedido
  MODIFY COLUMN estado ENUM(
    'aguarda_orcamento',
    'em_analise',
    'aguarda_pagamento',
    'em_producao',
    'concluido',
    'entregue',
    'cancelado'
  ) NOT NULL DEFAULT 'aguarda_orcamento';


-- -----------------------------------------------------------------------------
-- Avaliações por produto (alter_avaliacoes.sql)
-- Permite que cada avaliação esteja ligada a um produto específico.
-- -----------------------------------------------------------------------------
ALTER TABLE avaliacao
  ADD COLUMN IF NOT EXISTS produto_id INT NULL AFTER utilizador_id;

-- Índice para pesquisas rápidas por produto
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'avaliacao'
    AND INDEX_NAME = 'idx_aval_produto'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE avaliacao ADD INDEX idx_aval_produto (produto_id)',
  'SELECT ''idx_aval_produto ja existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Constraint: um utilizador só avalia cada produto uma vez
SET @uniq := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'avaliacao'
    AND CONSTRAINT_NAME = 'uniq_aval_user_produto'
);
SET @sql2 := IF(@uniq = 0,
  'ALTER TABLE avaliacao ADD UNIQUE KEY uniq_aval_user_produto (utilizador_id, produto_id)',
  'SELECT ''uniq_aval_user_produto ja existe'''
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;


-- -----------------------------------------------------------------------------
-- Fotos de inspiração dos clientes (alter_portfolio.sql)
-- Tabela que guarda até 3 imagens enviadas no formulário de orçamento.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedido_inspiracao (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id  INT NOT NULL,
    imagem     MEDIUMBLOB NOT NULL,
    ordem      TINYINT NOT NULL DEFAULT 1,
    CONSTRAINT fk_pi_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedido(id) ON DELETE CASCADE
);

ALTER TABLE pedido
  ADD COLUMN IF NOT EXISTS portfolio_inspiracao_id INT NULL;


-- -----------------------------------------------------------------------------
-- URL do link de pagamento Stripe (alter_orcamento.sql)
-- Guarda o URL para poder reenviar ao cliente sem gerar um novo link.
-- -----------------------------------------------------------------------------
ALTER TABLE pagamento
  ADD COLUMN IF NOT EXISTS stripe_payment_link_url VARCHAR(500) NULL;


-- -----------------------------------------------------------------------------
-- Índices de performance (indexes.sql)
-- Tornam as pesquisas mais rápidas quando há muitos pedidos/produtos.
-- -----------------------------------------------------------------------------
SET @i1 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='produto' AND INDEX_NAME='idx_produto_categoria');
SET @s1 := IF(@i1=0,'ALTER TABLE produto ADD INDEX idx_produto_categoria (categoria_id)','SELECT 1');
PREPARE p1 FROM @s1; EXECUTE p1; DEALLOCATE PREPARE p1;

SET @i2 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='produto_imagem' AND INDEX_NAME='idx_prodimg_produto_ordem');
SET @s2 := IF(@i2=0,'ALTER TABLE produto_imagem ADD INDEX idx_prodimg_produto_ordem (produto_id, ordem)','SELECT 1');
PREPARE p2 FROM @s2; EXECUTE p2; DEALLOCATE PREPARE p2;

SET @i3 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedido' AND INDEX_NAME='idx_pedido_utilizador');
SET @s3 := IF(@i3=0,'ALTER TABLE pedido ADD INDEX idx_pedido_utilizador (utilizador_id, estado)','SELECT 1');
PREPARE p3 FROM @s3; EXECUTE p3; DEALLOCATE PREPARE p3;
