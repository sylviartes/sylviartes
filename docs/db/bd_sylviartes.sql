-- =============================================================================
--  bd_sylviartes.sql  -  Base de Dados COMPLETA da SylviArtes
-- =============================================================================
--  Ficheiro unico que cria toda a base de dados de raiz:
--    - 12 tabelas (com as chaves estrangeiras)
--    - indices de performance
--    - 7 views (consultas para relatorios/dashboard)
--    - 4 stored procedures (logica de negocio)
--    - 16 triggers (validacoes automaticas)
--
--  COMO USAR:
--    phpMyAdmin -> separador SQL -> colar este ficheiro -> Executar
--    (ou no terminal:  mysql -u root < bd_sylviartes.sql )
--
--  Motor: InnoDB   |   Codificacao: utf8mb4
--  E seguro voltar a correr (apaga e recria tudo do inicio).
-- =============================================================================

CREATE DATABASE IF NOT EXISTS sylviartes
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sylviartes;


-- =============================================================================
--  LIMPEZA (para o script poder correr varias vezes)
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS view_dashboard_completo;
DROP VIEW IF EXISTS view_melhores_clientes;
DROP VIEW IF EXISTS view_produtos_mais_vendidos;
DROP VIEW IF EXISTS view_pedidos_pendentes_detalhado;
DROP VIEW IF EXISTS view_produtos_disponiveis;
DROP VIEW IF EXISTS view_ultimas_encomendas;
DROP VIEW IF EXISTS view_aviso_stock_baixo;

DROP TABLE IF EXISTS password_reset;
DROP TABLE IF EXISTS log_alteracoes_pedido;
DROP TABLE IF EXISTS mensagem_pedido;
DROP TABLE IF EXISTS pedido_inspiracao;
DROP TABLE IF EXISTS avaliacao;
DROP TABLE IF EXISTS pagamento;
DROP TABLE IF EXISTS detalhe_pedido;
DROP TABLE IF EXISTS produto_imagem;
DROP TABLE IF EXISTS produto;
DROP TABLE IF EXISTS pedido;
DROP TABLE IF EXISTS categoria;
DROP TABLE IF EXISTS utilizador;

SET FOREIGN_KEY_CHECKS = 1;

-- Procedures e triggers sao objetos de schema (NAO saem com DROP TABLE),
-- por isso removem-se explicitamente para o script poder correr varias vezes
-- sem dar erro "already exists".
DROP PROCEDURE IF EXISTS gerir_stock_produto;
DROP PROCEDURE IF EXISTS obter_totais_dashboard;
DROP PROCEDURE IF EXISTS processar_pedido_completo;
DROP PROCEDURE IF EXISTS relatorio_vendas_mensal;

DROP TRIGGER IF EXISTS trg_validar_utilizador_antes_insert;
DROP TRIGGER IF EXISTS trg_utilizador_antes_update;
DROP TRIGGER IF EXISTS trg_validar_avaliacao_antes_insert;
DROP TRIGGER IF EXISTS trg_avaliacao_antes_update;
DROP TRIGGER IF EXISTS trg_validar_categoria_antes_insert;
DROP TRIGGER IF EXISTS trg_categoria_antes_update;
DROP TRIGGER IF EXISTS trg_validar_pedido_antes_insert;
DROP TRIGGER IF EXISTS trg_pedido_antes_update;
DROP TRIGGER IF EXISTS trg_validar_produto_antes_insert;
DROP TRIGGER IF EXISTS trg_produto_antes_update;
DROP TRIGGER IF EXISTS trg_verificar_stock_antes_venda;
DROP TRIGGER IF EXISTS trg_verificar_stock_antes_update_detalhe;
DROP TRIGGER IF EXISTS trg_validar_mensagem_antes_insert;
DROP TRIGGER IF EXISTS trg_mensagem_antes_update;
DROP TRIGGER IF EXISTS trg_validar_pagamento_antes_insert;
DROP TRIGGER IF EXISTS trg_pagamento_antes_update;


-- =============================================================================
--  TABELAS
-- =============================================================================

-- utilizador : clientes e administradores (distinguidos por nivel_acesso)
CREATE TABLE utilizador (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(100) NOT NULL,
  email         VARCHAR(100) NOT NULL UNIQUE,                 -- usado no login (unico)
  password      VARCHAR(255) NOT NULL,                        -- guardada como hash bcrypt
  telefone      VARCHAR(20)  NOT NULL,
  morada        VARCHAR(100) NOT NULL,
  codigo_postal VARCHAR(20)  NOT NULL,
  localidade    VARCHAR(50)  NOT NULL,
  nivel_acesso  ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  data_criacao  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- categoria : tipos de peca do portfolio (Babetes, Toalhas, ...)
CREATE TABLE categoria (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  nome             VARCHAR(50) NOT NULL,
  descricao        TEXT NULL,
  preco_referencia DECIMAL(10,2) NULL                          -- preco indicativo "A partir de"
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- produto : cada trabalho/modelo do portfolio (pertence a uma categoria)
CREATE TABLE produto (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id     INT NOT NULL,
  nome             VARCHAR(100) NOT NULL,
  descricao        TEXT NULL,
  preco_base       DECIMAL(10,2) NOT NULL DEFAULT 0,           -- 0 = sem preco fixo (por orcamento)
  visivel_catalogo TINYINT NOT NULL DEFAULT 1,
  stock            INT NULL DEFAULT 100,
  CONSTRAINT fk_produto_categoria FOREIGN KEY (categoria_id) REFERENCES categoria(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- produto_imagem : varias fotografias por produto (ordem 1 = principal)
CREATE TABLE produto_imagem (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  imagem     VARCHAR(255) NOT NULL,
  ordem      INT NULL DEFAULT 1,
  CONSTRAINT fk_prodimg_produto FOREIGN KEY (produto_id) REFERENCES produto(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pedido : pedido de orcamento submetido por um cliente
CREATE TABLE pedido (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id           INT NOT NULL,
  data                    DATETIME DEFAULT CURRENT_TIMESTAMP,
  prazo_entrega_desejado  DATE NOT NULL,
  estado                  ENUM('aguarda_orcamento','em_analise','aguarda_pagamento',
                               'em_producao','concluido','entregue','cancelado')
                          NOT NULL DEFAULT 'aguarda_orcamento',
  valor_total             DECIMAL(10,2) NOT NULL DEFAULT 0,
  observacoes             TEXT NULL,
  tipo_entrega            ENUM('levantamento_atelier','domicilio') NOT NULL,
  morada_entrega          VARCHAR(100) NOT NULL,
  custo_envio             DECIMAL(10,2) NOT NULL DEFAULT 0,
  portfolio_inspiracao_id INT NULL,
  CONSTRAINT fk_pedido_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizador(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- detalhe_pedido : produtos que compoem cada pedido (ligacao N:N)
CREATE TABLE detalhe_pedido (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id      INT NOT NULL,
  produto_id     INT NOT NULL,
  quantidade     INT NOT NULL DEFAULT 1,
  preco_unitario DECIMAL(10,2) NULL,
  descricao      TEXT NOT NULL,
  CONSTRAINT fk_detped_pedido  FOREIGN KEY (pedido_id)  REFERENCES pedido(id) ON DELETE CASCADE,
  CONSTRAINT fk_detped_produto FOREIGN KEY (produto_id) REFERENCES produto(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pagamento : pagamento de cada pedido (guarda link/fatura Stripe)
CREATE TABLE pagamento (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id               INT NOT NULL,
  metodo                  ENUM('mbway','transferencia','dinheiro') NOT NULL,
  data                    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  valor                   DECIMAL(10,2) NOT NULL,
  estado_pagamento        ENUM('validado','recusado','analise_pagamento')
                          NOT NULL DEFAULT 'analise_pagamento',
  stripe_payment_link_url VARCHAR(500) NULL,
  CONSTRAINT fk_pagamento_pedido FOREIGN KEY (pedido_id) REFERENCES pedido(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- avaliacao : estrelas + comentario de um cliente sobre um produto (moderada)
CREATE TABLE avaliacao (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT NOT NULL,
  produto_id    INT NULL,                                      -- NULL = avaliacao geral a loja
  estrelas      INT NOT NULL,
  comentario    TEXT NULL,
  data          DATETIME DEFAULT CURRENT_TIMESTAMP,
  aprovado      TINYINT NOT NULL DEFAULT 0,                    -- 0 = por moderar, 1 = visivel
  CONSTRAINT fk_avaliacao_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizador(id),
  CONSTRAINT fk_avaliacao_produto    FOREIGN KEY (produto_id)    REFERENCES produto(id),
  CONSTRAINT uniq_aval_user_produto  UNIQUE (utilizador_id, produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pedido_inspiracao : fotos de inspiracao anexadas ao pedido (ate 3, em binario)
CREATE TABLE pedido_inspiracao (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  imagem    MEDIUMBLOB NOT NULL,
  ordem     TINYINT NOT NULL DEFAULT 1,
  CONSTRAINT fk_pi_pedido FOREIGN KEY (pedido_id) REFERENCES pedido(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- mensagem_pedido : conversa entre cliente e admin dentro de um pedido
CREATE TABLE mensagem_pedido (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id      INT NOT NULL,
  remetente_tipo ENUM('admin','cliente') NOT NULL,
  mensagem       TEXT NOT NULL,
  lida           TINYINT NULL DEFAULT 0,
  data_envio     DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_pedido FOREIGN KEY (pedido_id) REFERENCES pedido(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- log_alteracoes_pedido : historico de mudancas de estado (auditoria)
CREATE TABLE log_alteracoes_pedido (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id       INT NULL,
  estado_anterior ENUM('aguarda_orcamento','em_analise','aguarda_pagamento',
                       'em_producao','concluido','entregue','cancelado') NULL,
  estado_novo     ENUM('aguarda_orcamento','em_analise','aguarda_pagamento',
                       'em_producao','concluido','entregue','cancelado') NULL,
  alterado_por    VARCHAR(50) NULL,
  data            DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_pedido FOREIGN KEY (pedido_id) REFERENCES pedido(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password_reset : tokens temporarios de recuperacao de palavra-passe
CREATE TABLE password_reset (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  utilizador_id  INT NOT NULL,
  token          VARCHAR(64) NOT NULL,
  data_expiracao DATETIME NOT NULL,
  usado          TINYINT NOT NULL DEFAULT 0,
  INDEX idx_token (token),
  CONSTRAINT fk_pr_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizador(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================================
--  INDICES DE PERFORMANCE
-- =============================================================================
ALTER TABLE produto_imagem ADD INDEX idx_prodimg_produto_ordem (produto_id, ordem);
ALTER TABLE pedido         ADD INDEX idx_pedido_utilizador     (utilizador_id, estado);
ALTER TABLE avaliacao      ADD INDEX idx_aval_produto          (produto_id);


-- =============================================================================
--  VIEWS  (consultas guardadas para relatorios e dashboard)
-- =============================================================================

-- Produtos com stock abaixo de 10 (alerta de reposicao)
CREATE OR REPLACE VIEW view_aviso_stock_baixo AS
SELECT produto.id, produto.nome, produto.stock
FROM produto
WHERE produto.stock IS NOT NULL AND produto.stock < 10;

-- Indicadores globais do dashboard (uma so linha)
CREATE OR REPLACE VIEW view_dashboard_completo AS
SELECT
    (SELECT COUNT(*) FROM pedido WHERE pedido.estado <> 'cancelado') AS total_pedidos,
    (SELECT IFNULL(SUM(pedido.valor_total),0) FROM pedido WHERE pedido.estado <> 'cancelado') AS faturacao_total,
    (SELECT IFNULL(AVG(pedido.valor_total),0) FROM pedido WHERE pedido.estado <> 'cancelado') AS ticket_medio,
    (SELECT COUNT(*) FROM utilizador WHERE utilizador.nivel_acesso = 'cliente') AS total_clientes,
    (SELECT COUNT(*) FROM utilizador
       WHERE utilizador.nivel_acesso = 'cliente'
         AND utilizador.data_criacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS novos_clientes_30d,
    (SELECT COUNT(*) FROM produto WHERE produto.stock < 10) AS produtos_stock_baixo,
    (SELECT COUNT(*) FROM produto) AS total_produtos,
    (SELECT COUNT(*) FROM pedido WHERE pedido.estado = 'em_analise') AS pedidos_pendentes,
    (SELECT COUNT(*) FROM pedido WHERE pedido.estado = 'em_producao') AS pedidos_producao,
    (SELECT COUNT(*) FROM pedido
       WHERE pedido.estado = 'entregue'
         AND pedido.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS entregas_30d,
    (SELECT IFNULL(SUM(pedido.valor_total),0) FROM pedido
       WHERE pedido.estado <> 'cancelado'
         AND pedido.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS receita_30d,
    (SELECT IFNULL(SUM(pedido.valor_total),0) FROM pedido
       WHERE pedido.estado <> 'cancelado'
         AND YEAR(pedido.data) = YEAR(CURDATE())
         AND MONTH(pedido.data) = MONTH(CURDATE())) AS receita_mes_atual;

-- Clientes ordenados pelo total gasto
CREATE OR REPLACE VIEW view_melhores_clientes AS
SELECT utilizador.id, utilizador.nome, utilizador.email,
       COUNT(pedido.id) AS num_encomendas,
       IFNULL(SUM(pedido.valor_total),0) AS total_gasto
FROM utilizador
JOIN pedido ON pedido.utilizador_id = utilizador.id
WHERE pedido.estado <> 'cancelado'
GROUP BY utilizador.id, utilizador.nome, utilizador.email
ORDER BY total_gasto DESC;

-- Pedidos pendentes com dados do cliente e lista de produtos
CREATE OR REPLACE VIEW view_pedidos_pendentes_detalhado AS
SELECT
    pedido.id,
    utilizador.nome AS cliente_nome,
    utilizador.email AS cliente_email,
    utilizador.telefone AS cliente_telefone,
    pedido.data AS data_pedido,
    pedido.prazo_entrega_desejado,
    pedido.valor_total,
    pedido.observacoes,
    pedido.tipo_entrega,
    pedido.morada_entrega,
    COUNT(detalhe_pedido.id) AS num_itens,
    GROUP_CONCAT(CONCAT(produto.nome, ' (', detalhe_pedido.quantidade, 'x)')
                 ORDER BY detalhe_pedido.id SEPARATOR ', ') AS produtos
FROM pedido
JOIN utilizador ON utilizador.id = pedido.utilizador_id
LEFT JOIN detalhe_pedido ON detalhe_pedido.pedido_id = pedido.id
LEFT JOIN produto ON produto.id = detalhe_pedido.produto_id
WHERE pedido.estado IN ('em_analise','aguarda_pagamento')
GROUP BY pedido.id, utilizador.nome, utilizador.email, utilizador.telefone,
         pedido.data, pedido.prazo_entrega_desejado, pedido.valor_total,
         pedido.observacoes, pedido.tipo_entrega, pedido.morada_entrega;

-- Produtos visiveis no catalogo (com imagem principal e categoria)
CREATE OR REPLACE VIEW view_produtos_disponiveis AS
SELECT produto.id, produto.nome, produto.preco_base,
       produto_imagem.imagem, categoria.nome AS categoria
FROM produto
JOIN categoria ON categoria.id = produto.categoria_id
LEFT JOIN produto_imagem ON produto_imagem.produto_id = produto.id AND produto_imagem.ordem = 1
WHERE produto.visivel_catalogo = 1
  AND (produto.stock IS NULL OR produto.stock > 0);

-- Produtos mais vendidos
CREATE OR REPLACE VIEW view_produtos_mais_vendidos AS
SELECT produto.id, produto.nome, categoria.nome AS categoria,
       IFNULL(SUM(detalhe_pedido.quantidade),0) AS quantidade_vendida,
       IFNULL(SUM(detalhe_pedido.quantidade * detalhe_pedido.preco_unitario),0) AS receita_total,
       COUNT(DISTINCT detalhe_pedido.pedido_id) AS num_pedidos,
       IFNULL(AVG(detalhe_pedido.preco_unitario),0) AS preco_medio_venda
FROM produto
JOIN categoria ON categoria.id = produto.categoria_id
JOIN detalhe_pedido ON detalhe_pedido.produto_id = produto.id
JOIN pedido ON pedido.id = detalhe_pedido.pedido_id
WHERE pedido.estado <> 'cancelado'
GROUP BY produto.id, produto.nome, categoria.nome
ORDER BY quantidade_vendida DESC;

-- As 10 encomendas mais recentes
CREATE OR REPLACE VIEW view_ultimas_encomendas AS
SELECT pedido.id, utilizador.nome AS cliente, pedido.valor_total,
       pedido.estado, pedido.data AS data_pedido
FROM pedido
JOIN utilizador ON utilizador.id = pedido.utilizador_id
ORDER BY pedido.data DESC
LIMIT 10;


-- =============================================================================
--  STORED PROCEDURES  (logica de negocio no servidor)
-- =============================================================================
DELIMITER $$

-- Ajusta o stock de um produto (adicionar / remover / definir)
CREATE PROCEDURE gerir_stock_produto(
    IN p_produto_id INT,
    IN p_quantidade INT,
    IN p_operacao ENUM('adicionar','remover','definir'),
    IN p_motivo VARCHAR(100)
)
BEGIN
    DECLARE v_stock_atual INT;
    SELECT produto.stock INTO v_stock_atual FROM produto WHERE produto.id = p_produto_id;
    CASE p_operacao
        WHEN 'adicionar' THEN
            UPDATE produto SET produto.stock = produto.stock + p_quantidade WHERE produto.id = p_produto_id;
        WHEN 'remover' THEN
            IF v_stock_atual >= p_quantidade THEN
                UPDATE produto SET produto.stock = produto.stock - p_quantidade WHERE produto.id = p_produto_id;
            ELSE
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente para realizar a operacao';
            END IF;
        WHEN 'definir' THEN
            UPDATE produto SET produto.stock = p_quantidade WHERE produto.id = p_produto_id;
    END CASE;
END$$

-- Totais rapidos do dashboard
CREATE PROCEDURE obter_totais_dashboard()
BEGIN
    SELECT
        (SELECT IFNULL(SUM(pedido.valor_total), 0) FROM pedido WHERE pedido.estado != 'cancelado') AS faturacao_total,
        (SELECT COUNT(*) FROM pedido WHERE pedido.estado = 'em_analise') AS pedidos_pendentes,
        (SELECT COUNT(*) FROM utilizador WHERE utilizador.nivel_acesso = 'cliente') AS total_clientes,
        (SELECT COUNT(*) FROM produto WHERE produto.stock < 10) AS stock_alerta;
END$$

-- Cria um pedido completo, calculando o custo de envio
CREATE PROCEDURE processar_pedido_completo(
    IN p_utilizador_id INT,
    IN p_prazo_entrega DATE,
    IN p_tipo_entrega ENUM('levantamento_atelier','domicilio'),
    IN p_morada_entrega VARCHAR(100),
    IN p_observacoes TEXT,
    OUT p_pedido_id INT
)
BEGIN
    DECLARE v_custo_envio DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_valor_total DECIMAL(10,2) DEFAULT 0.00;
    IF p_tipo_entrega = 'domicilio' THEN
        SET v_custo_envio = 5.00;
    END IF;
    INSERT INTO pedido (utilizador_id, prazo_entrega_desejado, estado, valor_total,
                        observacoes, tipo_entrega, morada_entrega, custo_envio)
    VALUES (p_utilizador_id, p_prazo_entrega, 'em_analise', v_valor_total,
            p_observacoes, p_tipo_entrega, p_morada_entrega, v_custo_envio);
    SET p_pedido_id = LAST_INSERT_ID();
END$$

-- Relatorio de vendas de um mes
CREATE PROCEDURE relatorio_vendas_mensal(IN p_ano INT, IN p_mes INT)
BEGIN
    SELECT
        COUNT(*) AS total_pedidos,
        SUM(pedido.valor_total) AS faturacao_total,
        AVG(pedido.valor_total) AS ticket_medio,
        COUNT(CASE WHEN pedido.tipo_entrega = 'domicilio' THEN 1 END) AS entregas_domicilio,
        SUM(pedido.custo_envio) AS total_envios
    FROM pedido
    WHERE YEAR(pedido.data) = p_ano AND MONTH(pedido.data) = p_mes
      AND pedido.estado != 'cancelado';
END$$

DELIMITER ;


-- =============================================================================
--  TRIGGERS  (validacoes automaticas antes de inserir/atualizar)
-- =============================================================================
DELIMITER $$

-- utilizador: password >= 6 caracteres e email valido
CREATE TRIGGER trg_validar_utilizador_antes_insert
BEFORE INSERT ON utilizador FOR EACH ROW
BEGIN
    IF LENGTH(NEW.password) < 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Password deve ter pelo menos 6 caracteres.';
    END IF;
    IF NEW.email NOT REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email invalido.';
    END IF;
END$$

CREATE TRIGGER trg_utilizador_antes_update
BEFORE UPDATE ON utilizador FOR EACH ROW
BEGIN
    IF LENGTH(NEW.password) < 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Password deve ter pelo menos 6 caracteres.';
    END IF;
    IF NEW.email NOT REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email invalido.';
    END IF;
END$$

-- avaliacao: estrelas entre 1 e 5
CREATE TRIGGER trg_validar_avaliacao_antes_insert
BEFORE INSERT ON avaliacao FOR EACH ROW
BEGIN
    IF NEW.estrelas < 1 OR NEW.estrelas > 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estrelas devem ser entre 1 e 5.';
    END IF;
END$$

CREATE TRIGGER trg_avaliacao_antes_update
BEFORE UPDATE ON avaliacao FOR EACH ROW
BEGIN
    IF NEW.estrelas < 1 OR NEW.estrelas > 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estrelas devem ser entre 1 e 5.';
    END IF;
END$$

-- categoria: nome nao vazio
CREATE TRIGGER trg_validar_categoria_antes_insert
BEFORE INSERT ON categoria FOR EACH ROW
BEGIN
    IF LENGTH(TRIM(NEW.nome)) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nome da categoria nao pode ser vazio.';
    END IF;
END$$

CREATE TRIGGER trg_categoria_antes_update
BEFORE UPDATE ON categoria FOR EACH ROW
BEGIN
    IF LENGTH(TRIM(NEW.nome)) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nome da categoria nao pode ser vazio.';
    END IF;
END$$

-- pedido: prazo de entrega tem de ser data futura
CREATE TRIGGER trg_validar_pedido_antes_insert
BEFORE INSERT ON pedido FOR EACH ROW
BEGIN
    IF NEW.prazo_entrega_desejado <= CURDATE() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O prazo de entrega desejado deve ser uma data futura.';
    END IF;
END$$

CREATE TRIGGER trg_pedido_antes_update
BEFORE UPDATE ON pedido FOR EACH ROW
BEGIN
    IF NEW.prazo_entrega_desejado <= CURDATE() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O prazo de entrega desejado deve ser uma data futura.';
    END IF;
END$$

-- produto: preco e stock nao negativos (preco 0 e permitido - modelo por orcamento)
CREATE TRIGGER trg_validar_produto_antes_insert
BEFORE INSERT ON produto FOR EACH ROW
BEGIN
    IF NEW.preco_base < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O preco base nao pode ser negativo.';
    END IF;
    IF NEW.stock IS NOT NULL AND NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O stock nao pode ser negativo.';
    END IF;
END$$

CREATE TRIGGER trg_produto_antes_update
BEFORE UPDATE ON produto FOR EACH ROW
BEGIN
    IF NEW.preco_base < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O preco base nao pode ser negativo.';
    END IF;
    IF NEW.stock IS NOT NULL AND NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O stock nao pode ser negativo.';
    END IF;
END$$

-- detalhe_pedido: verifica stock suficiente
CREATE TRIGGER trg_verificar_stock_antes_venda
BEFORE INSERT ON detalhe_pedido FOR EACH ROW
BEGIN
    DECLARE stock_atual INT DEFAULT 0;
    SELECT IFNULL(produto.stock, 0) INTO stock_atual FROM produto WHERE produto.id = NEW.produto_id;
    IF stock_atual < NEW.quantidade THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nao ha stock suficiente para este produto.';
    END IF;
END$$

CREATE TRIGGER trg_verificar_stock_antes_update_detalhe
BEFORE UPDATE ON detalhe_pedido FOR EACH ROW
BEGIN
    DECLARE stock_atual INT DEFAULT 0;
    SELECT IFNULL(produto.stock, 0) INTO stock_atual FROM produto WHERE produto.id = NEW.produto_id;
    IF NEW.quantidade <> OLD.quantidade AND NEW.quantidade > OLD.quantidade THEN
        IF stock_atual + OLD.quantidade < NEW.quantidade THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nao ha stock suficiente para aumentar a quantidade.';
        END IF;
    END IF;
END$$

-- mensagem_pedido: remetente valido
CREATE TRIGGER trg_validar_mensagem_antes_insert
BEFORE INSERT ON mensagem_pedido FOR EACH ROW
BEGIN
    IF NEW.remetente_tipo NOT IN ('admin','cliente') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Remetente tipo deve ser admin ou cliente.';
    END IF;
END$$

CREATE TRIGGER trg_mensagem_antes_update
BEFORE UPDATE ON mensagem_pedido FOR EACH ROW
BEGIN
    IF NEW.remetente_tipo NOT IN ('admin','cliente') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Remetente tipo deve ser admin ou cliente.';
    END IF;
END$$

-- pagamento: valor maior que zero
CREATE TRIGGER trg_validar_pagamento_antes_insert
BEFORE INSERT ON pagamento FOR EACH ROW
BEGIN
    IF NEW.valor <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O valor do pagamento deve ser maior que zero.';
    END IF;
END$$

CREATE TRIGGER trg_pagamento_antes_update
BEFORE UPDATE ON pagamento FOR EACH ROW
BEGIN
    IF NEW.valor <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O valor do pagamento deve ser maior que zero.';
    END IF;
END$$

DELIMITER ;

-- =============================================================================
--  FIM  -  Base de dados SylviArtes criada (12 tabelas, 7 views,
--          4 procedures, 16 triggers, 3 indices).
-- =============================================================================
