-- =============================================================================
-- alter_avaliacoes.sql
-- Permite que cada avaliação esteja associada a um produto específico.
-- Corre este ficheiro uma só vez no phpMyAdmin (BD sylviartes → SQL).
-- =============================================================================

-- Adiciona a coluna produto_id à tabela avaliacao
ALTER TABLE avaliacao
  ADD COLUMN produto_id INT NULL AFTER utilizador_id,
  ADD INDEX idx_aval_produto (produto_id);

-- Garante que cada utilizador só pode avaliar cada produto uma vez
ALTER TABLE avaliacao
  ADD UNIQUE KEY uniq_aval_user_produto (utilizador_id, produto_id);
