-- =============================================================================
-- indexes.sql — Índices para acelerar as queries mais usadas
-- =============================================================================
--
-- O que é um índice?
--   É como o índice de um livro: em vez de o MySQL ler toda a tabela para
--   encontrar um registo, consulta o índice e vai diretamente ao sítio certo.
--
-- Como executar:
--   1. Abre o phpMyAdmin (ou MySQL Workbench)
--   2. Seleciona a base de dados "sylviartes"
--   3. Corre este ficheiro SQL (Import → selecionar ficheiro)
--
-- Nota: IF NOT EXISTS evita erro se o índice já existir.
-- =============================================================================


-- produto.categoria_id
-- Usado em: SELECT * FROM produto WHERE categoria_id = ? (filtro do catálogo)
CREATE INDEX IF NOT EXISTS idx_produto_categoria
    ON produto (categoria_id);


-- produto.visivel_catalogo
-- Usado em: SELECT * FROM produto WHERE visivel_catalogo = 1
CREATE INDEX IF NOT EXISTS idx_produto_visivel
    ON produto (visivel_catalogo);


-- produto_imagem.(produto_id, ordem)
-- Usado em: SELECT * FROM produto_imagem WHERE produto_id = ? ORDER BY ordem
-- Índice composto: cobre o WHERE e o ORDER BY ao mesmo tempo
CREATE INDEX IF NOT EXISTS idx_prod_img_produto_ordem
    ON produto_imagem (produto_id, ordem);


-- avaliacao.(produto_id, aprovado)
-- Usado em: SELECT * FROM avaliacao WHERE produto_id = ? AND aprovado = 1
CREATE INDEX IF NOT EXISTS idx_avaliacao_produto_aprovado
    ON avaliacao (produto_id, aprovado);


-- pedido.utilizador_id + pedido.estado
-- Usado em: SELECT * FROM pedido WHERE utilizador_id = ? (área de cliente)
CREATE INDEX IF NOT EXISTS idx_pedido_utilizador
    ON pedido (utilizador_id);

CREATE INDEX IF NOT EXISTS idx_pedido_estado
    ON pedido (estado);
