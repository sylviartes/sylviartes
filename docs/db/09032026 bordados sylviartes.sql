-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ==========================================================
-- Patch aplicado: juntar alterações (alter_*.sql) num só
-- ==========================================================


-- -----------------------------------------------------
-- Schema sylviartes
-- -----------------------------------------------------
DROP SCHEMA IF EXISTS `sylviartes` ;

-- -----------------------------------------------------
-- Schema sylviartes
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `sylviartes` DEFAULT CHARACTER SET utf8mb4 ;
USE `sylviartes` ;

-- -----------------------------------------------------
-- Table `sylviartes`.`utilizador`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`utilizador` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `telefone` VARCHAR(20) NOT NULL,
  `morada` VARCHAR(100) NOT NULL,
  `codigo_postal` VARCHAR(20) NOT NULL,
  `localidade` VARCHAR(50) NOT NULL,
  `nivel_acesso` ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
  `data_criacao` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `email` (`email` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 2
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`avaliacao`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`avaliacao` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT NOT NULL,
  `produto_id` INT NULL,                          -- produto avaliado (NULL = avaliação geral à loja)
  `estrelas` INT NOT NULL,
  `comentario` TEXT NULL DEFAULT NULL,
  `data` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  `aprovado` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `utilizador_id` (`utilizador_id` ASC),
  INDEX `idx_aval_produto` (`produto_id` ASC),                       -- pesquisa rápida por produto
  UNIQUE INDEX `uniq_aval_user_produto` (`utilizador_id`, `produto_id`), -- 1 avaliação por cliente/produto
  CONSTRAINT `avaliacao_loja_ibfk_1`
    FOREIGN KEY (`utilizador_id`)
    REFERENCES `sylviartes`.`utilizador` (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`categoria`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`categoria` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(50) NOT NULL,
  `descricao` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 5
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`pedido`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`pedido` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `utilizador_id` INT NOT NULL,
  `data` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  `prazo_entrega_desejado` DATE NOT NULL,
  `estado` ENUM('aguarda_orcamento', 'em_analise', 'aguarda_pagamento', 'em_producao', 'concluido', 'entregue', 'cancelado') NOT NULL DEFAULT 'em_analise',
  `valor_total` DECIMAL(10,2) NOT NULL,
  `observacoes` TEXT NULL DEFAULT NULL,
  `tipo_entrega` ENUM('levantamento_atelier', 'domicilio') NOT NULL,
  `morada_entrega` VARCHAR(100) NOT NULL,
  `custo_envio` DECIMAL(10,2) NOT NULL,
  `portfolio_inspiracao_id` INT NULL,             -- produto do portfólio que inspirou o pedido (opcional)
  PRIMARY KEY (`id`),
  INDEX `utilizador_id` (`utilizador_id` ASC),
  CONSTRAINT `pedido_ibfk_1`
    FOREIGN KEY (`utilizador_id`)
    REFERENCES `sylviartes`.`utilizador` (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`produto`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`produto` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `categoria_id` INT NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT NULL DEFAULT NULL,
  `preco_base` DECIMAL(10,2) NOT NULL,
  `visivel_catalogo` TINYINT NOT NULL DEFAULT 1,
  `stock` INT NULL DEFAULT 100,
  PRIMARY KEY (`id`),
  INDEX `categoria_id` (`categoria_id` ASC),
  CONSTRAINT `produto_ibfk_1`
    FOREIGN KEY (`categoria_id`)
    REFERENCES `sylviartes`.`categoria` (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`detalhe_pedido`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`detalhe_pedido` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `produto_id` INT NOT NULL,
  `quantidade` INT NOT NULL,
  `preco_unitario` DECIMAL(10,2) NULL DEFAULT NULL,
  `descricao` TEXT NOT NULL,
  `imagem_referencia` LONGBLOB NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `pedido_id` (`pedido_id` ASC),
  INDEX `produto_id` (`produto_id` ASC),
  CONSTRAINT `detalhe_pedido_ibfk_1`
    FOREIGN KEY (`pedido_id`)
    REFERENCES `sylviartes`.`pedido` (`id`),
  CONSTRAINT `detalhe_pedido_ibfk_2`
    FOREIGN KEY (`produto_id`)
    REFERENCES `sylviartes`.`produto` (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`log_alteracoes_pedido`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`log_alteracoes_pedido` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NULL DEFAULT NULL,
  `estado_anterior` ENUM('aguarda_orcamento', 'em_analise', 'aguarda_pagamento', 'em_producao', 'concluido', 'entregue', 'cancelado') NULL DEFAULT NULL,
  `estado_novo` ENUM('aguarda_orcamento', 'em_analise', 'aguarda_pagamento', 'em_producao', 'concluido', 'entregue', 'cancelado') NULL DEFAULT NULL,
  `alterado_por` VARCHAR(50) NULL DEFAULT NULL,
  `data` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  INDEX `pedido_id` (`pedido_id` ASC),
  CONSTRAINT `log_alteracoes_pedido_ibfk_1`
    FOREIGN KEY (`pedido_id`)
    REFERENCES `sylviartes`.`pedido` (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`mensagem_pedido`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`mensagem_pedido` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `remetente_tipo` ENUM('admin', 'cliente') NOT NULL,
  `mensagem` TEXT NOT NULL,
  `lida` TINYINT NULL DEFAULT 0,
  `data_envio` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  INDEX `pedido_id` (`pedido_id` ASC),
  CONSTRAINT `mensagem_pedido_ibfk_1`
    FOREIGN KEY (`pedido_id`)
    REFERENCES `sylviartes`.`pedido` (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`pagamento`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`pagamento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `metodo` ENUM('mbway', 'transferencia', 'dinheiro') NOT NULL,
  `data` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `valor` DECIMAL(10,2) NOT NULL,
  `comprovativo` LONGBLOB NULL,
  `estado_pagamento` ENUM('validado', 'recusado', 'analise_pagamento') NOT NULL DEFAULT 'analise_pagamento',
  `stripe_payment_link_url` VARCHAR(500) NULL,    -- URL do link de pagamento Stripe (para reenviar à cliente)
  PRIMARY KEY (`id`),
  UNIQUE INDEX `pedido_id` (`pedido_id` ASC),
  CONSTRAINT `pagamento_ibfk_1`
    FOREIGN KEY (`pedido_id`)
    REFERENCES `sylviartes`.`pedido` (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `sylviartes`.`produto_imagem`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`produto_imagem` (
  `id` INT NULL AUTO_INCREMENT,
  `produto_id` INT NOT NULL,
  `imagem` LONGBLOB NULL,
  `ordem` INT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `fk_produto_imagem_produto_idx` (`produto_id` ASC),
  CONSTRAINT `fk_produto_imagem_produto`
    FOREIGN KEY (`produto_id`)
    REFERENCES `sylviartes`.`produto` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sylviartes`.`pedido_inspiracao`
-- Fotos de inspiração que a cliente envia ao pedir orçamento (até 3 por pedido)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`pedido_inspiracao` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `imagem` MEDIUMBLOB NOT NULL,                    -- foto em binário (até ~16MB)
  `ordem` TINYINT NOT NULL DEFAULT 1,              -- ordem de apresentação (1, 2, 3)
  PRIMARY KEY (`id`),
  INDEX `fk_pi_pedido_idx` (`pedido_id` ASC),
  CONSTRAINT `fk_pi_pedido`
    FOREIGN KEY (`pedido_id`)
    REFERENCES `sylviartes`.`pedido` (`id`)
    ON DELETE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4;

USE `sylviartes` ;

-- -----------------------------------------------------
-- Placeholder table for view `sylviartes`.`view_aviso_stock_baixo`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`view_aviso_stock_baixo` (`id` INT, `nome` INT, `stock` INT);

-- -----------------------------------------------------
-- Placeholder table for view `sylviartes`.`view_dashboard_completo`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`view_dashboard_completo` (`total_pedidos` INT, `faturacao_total` INT, `ticket_medio` INT, `total_clientes` INT, `novos_clientes_30d` INT, `produtos_stock_baixo` INT, `total_produtos` INT, `pedidos_pendentes` INT, `pedidos_producao` INT, `entregas_30d` INT, `receita_30d` INT, `receita_mes_atual` INT);

-- -----------------------------------------------------
-- Placeholder table for view `sylviartes`.`view_melhores_clientes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`view_melhores_clientes` (`id` INT, `nome` INT, `email` INT, `num_encomendas` INT, `total_gasto` INT);

-- -----------------------------------------------------
-- Placeholder table for view `sylviartes`.`view_pedidos_pendentes_detalhado`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`view_pedidos_pendentes_detalhado` (`id` INT, `cliente_nome` INT, `cliente_email` INT, `cliente_telefone` INT, `data_pedido` INT, `prazo_entrega_desejado` INT, `valor_total` INT, `observacoes` INT, `tipo_entrega` INT, `morada_entrega` INT, `num_itens` INT, `produtos` INT);

-- -----------------------------------------------------
-- Placeholder table for view `sylviartes`.`view_produtos_disponiveis`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`view_produtos_disponiveis` (`id` INT, `nome` INT, `preco_base` INT, `imagem` INT, `categoria` INT);

-- -----------------------------------------------------
-- Placeholder table for view `sylviartes`.`view_produtos_mais_vendidos`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`view_produtos_mais_vendidos` (`id` INT, `nome` INT, `categoria` INT, `quantidade_vendida` INT, `receita_total` INT, `num_pedidos` INT, `preco_medio_venda` INT);

-- -----------------------------------------------------
-- Placeholder table for view `sylviartes`.`view_ultimas_encomendas`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sylviartes`.`view_ultimas_encomendas` (`id` INT, `cliente` INT, `valor_total` INT, `estado` INT, `data_pedido` INT);

-- -----------------------------------------------------
-- procedure gerir_stock_produto
-- -----------------------------------------------------

DELIMITER $$
USE `sylviartes`$$
CREATE PROCEDURE gerir_stock_produto(
    IN p_produto_id INT,
    IN p_quantidade INT,
    IN p_operacao ENUM('adicionar', 'remover', 'definir'),
    IN p_motivo VARCHAR(100)
)
BEGIN
    DECLARE v_stock_atual INT;

    -- Obter o stock atual do produto
    SELECT produto.stock INTO v_stock_atual FROM produto WHERE produto.id = p_produto_id;

    -- Executar a operação solicitada
    CASE p_operacao
        WHEN 'adicionar' THEN
            UPDATE produto SET produto.stock = produto.stock + p_quantidade WHERE produto.id = p_produto_id;
        WHEN 'remover' THEN
            IF v_stock_atual >= p_quantidade THEN
                UPDATE produto SET produto.stock = produto.stock - p_quantidade WHERE produto.id = p_produto_id;
            ELSE
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente para realizar a operação';
            END IF;
        WHEN 'definir' THEN
            UPDATE produto SET produto.stock = p_quantidade WHERE produto.id = p_produto_id;
    END CASE;
END$$

DELIMITER ;

-- -----------------------------------------------------
-- procedure obter_totais_dashboard
-- -----------------------------------------------------

DELIMITER $$
USE `sylviartes`$$
CREATE PROCEDURE obter_totais_dashboard()
BEGIN
    SELECT 
        (SELECT IFNULL(SUM(pedido.valor_total), 0) FROM pedido WHERE pedido.estado != 'cancelado') AS faturacao_total,
        (SELECT COUNT(*) FROM pedido WHERE pedido.estado = 'em_analise') AS pedidos_pendentes,
        (SELECT COUNT(*) FROM utilizador WHERE utilizador.nivel_acesso = 'cliente') AS total_clientes,
        (SELECT COUNT(*) FROM produto WHERE produto.stock < 10) AS stock_alerta;
END$$

DELIMITER ;

-- -----------------------------------------------------
-- procedure processar_pedido_completo
-- -----------------------------------------------------

DELIMITER $$
USE `sylviartes`$$
CREATE PROCEDURE processar_pedido_completo(
    IN p_utilizador_id INT,
    IN p_prazo_entrega DATE,
    IN p_tipo_entrega ENUM('levantamento_atelier', 'domicilio'),
    IN p_morada_entrega VARCHAR(100),
    IN p_observacoes TEXT,
    OUT p_pedido_id INT
)
BEGIN
    DECLARE v_custo_envio DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_valor_total DECIMAL(10,2) DEFAULT 0.00;

    -- Calcular o custo de envio baseado no tipo
    IF p_tipo_entrega = 'domicilio' THEN
        SET v_custo_envio = 5.00;
    END IF;

    -- Criar o novo pedido na base de dados
    INSERT INTO pedido (utilizador_id, prazo_entrega_desejado, estado, valor_total,
                        observacoes, tipo_entrega, morada_entrega, custo_envio)
    VALUES (p_utilizador_id, p_prazo_entrega, 'em_analise', v_valor_total,
            p_observacoes, p_tipo_entrega, p_morada_entrega, v_custo_envio);

    -- Devolver o ID do pedido criado
    SET p_pedido_id = LAST_INSERT_ID();
END$$

DELIMITER ;

-- -----------------------------------------------------
-- procedure relatorio_vendas_mensal
-- -----------------------------------------------------

DELIMITER $$
USE `sylviartes`$$
CREATE PROCEDURE relatorio_vendas_mensal(IN p_ano INT, IN p_mes INT)
BEGIN
    SELECT
        COUNT(*) as total_pedidos,
        SUM(pedido.valor_total) as faturacao_total,
        AVG(pedido.valor_total) as ticket_medio,
        COUNT(CASE WHEN pedido.tipo_entrega = 'domicilio' THEN 1 END) as entregas_domicilio,
        SUM(pedido.custo_envio) as total_envios
    FROM pedido
    WHERE YEAR(pedido.data) = p_ano
    AND MONTH(pedido.data) = p_mes
    AND pedido.estado != 'cancelado';
END$$

DELIMITER ;

-- -----------------------------------------------------
-- View `sylviartes`.`view_aviso_stock_baixo`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sylviartes`.`view_aviso_stock_baixo`;
USE `sylviartes`;
CREATE OR REPLACE VIEW view_aviso_stock_baixo AS
SELECT 
    produto.id,
    produto.nome,
    produto.stock
FROM produto
WHERE produto.stock IS NOT NULL
  AND produto.stock < 10;

-- -----------------------------------------------------
-- View `sylviartes`.`view_dashboard_completo`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sylviartes`.`view_dashboard_completo`;
USE `sylviartes`;
CREATE OR REPLACE VIEW view_dashboard_completo AS
SELECT
    (SELECT COUNT(*) FROM pedido WHERE pedido.estado <> 'cancelado') AS total_pedidos,
    (SELECT IFNULL(SUM(pedido.valor_total),0) FROM pedido WHERE pedido.estado <> 'cancelado') AS faturacao_total,
    (SELECT IFNULL(AVG(pedido.valor_total),0) FROM pedido WHERE pedido.estado <> 'cancelado') AS ticket_medio,

    (SELECT COUNT(*) FROM utilizador WHERE utilizador.nivel_acesso = 'cliente') AS total_clientes,
    (SELECT COUNT(*)
       FROM utilizador
      WHERE utilizador.nivel_acesso = 'cliente'
        AND utilizador.data_criacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ) AS novos_clientes_30d,

    (SELECT COUNT(*) FROM produto WHERE produto.stock < 10) AS produtos_stock_baixo,
    (SELECT COUNT(*) FROM produto) AS total_produtos,

    (SELECT COUNT(*) FROM pedido WHERE pedido.estado = 'em_analise') AS pedidos_pendentes,
    (SELECT COUNT(*) FROM pedido WHERE pedido.estado = 'em_producao') AS pedidos_producao,
    (SELECT COUNT(*)
       FROM pedido
      WHERE pedido.estado = 'entregue'
        AND pedido.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ) AS entregas_30d,

    (SELECT IFNULL(SUM(pedido.valor_total),0)
       FROM pedido
      WHERE pedido.estado <> 'cancelado'
        AND pedido.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ) AS receita_30d,

    (SELECT IFNULL(SUM(pedido.valor_total),0)
       FROM pedido
      WHERE pedido.estado <> 'cancelado'
        AND YEAR(pedido.data) = YEAR(CURDATE())
        AND MONTH(pedido.data) = MONTH(CURDATE())
    ) AS receita_mes_atual;

-- -----------------------------------------------------
-- View `sylviartes`.`view_melhores_clientes`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sylviartes`.`view_melhores_clientes`;
USE `sylviartes`;
CREATE OR REPLACE VIEW view_melhores_clientes AS
SELECT 
    utilizador.id,
    utilizador.nome,
    utilizador.email,
    COUNT(pedido.id) AS num_encomendas,
    IFNULL(SUM(pedido.valor_total),0) AS total_gasto
FROM utilizador
JOIN pedido ON pedido.utilizador_id = utilizador.id
WHERE pedido.estado <> 'cancelado'
GROUP BY utilizador.id, utilizador.nome, utilizador.email
ORDER BY total_gasto DESC;

-- -----------------------------------------------------
-- View `sylviartes`.`view_pedidos_pendentes_detalhado`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sylviartes`.`view_pedidos_pendentes_detalhado`;
USE `sylviartes`;
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
    GROUP_CONCAT(
      CONCAT(produto.nome, ' (', detalhe_pedido.quantidade, 'x)')
      ORDER BY detalhe_pedido.id
      SEPARATOR ', '
    ) AS produtos
FROM pedido
JOIN utilizador ON utilizador.id = pedido.utilizador_id
LEFT JOIN detalhe_pedido ON detalhe_pedido.pedido_id = pedido.id
LEFT JOIN produto ON produto.id = detalhe_pedido.produto_id
WHERE pedido.estado IN ('em_analise','aguarda_pagamento')
GROUP BY
    pedido.id,
    utilizador.nome,
    utilizador.email,
    utilizador.telefone,
    pedido.data,
    pedido.prazo_entrega_desejado,
    pedido.valor_total,
    pedido.observacoes,
    pedido.tipo_entrega,
    pedido.morada_entrega;

-- -----------------------------------------------------
-- View `sylviartes`.`view_produtos_disponiveis`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sylviartes`.`view_produtos_disponiveis`;
USE `sylviartes`;
CREATE OR REPLACE VIEW view_produtos_disponiveis AS
SELECT
    produto.id,
    produto.nome,
    produto.preco_base,
    produto_imagem.imagem,
    categoria.nome AS categoria
FROM produto
JOIN categoria 
    ON categoria.id = produto.categoria_id
LEFT JOIN produto_imagem 
    ON produto_imagem.produto_id = produto.id
    AND produto_imagem.ordem = 1
WHERE produto.visivel_catalogo = 1
  AND (produto.stock IS NULL OR produto.stock > 0);

-- -----------------------------------------------------
-- View `sylviartes`.`view_produtos_mais_vendidos`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sylviartes`.`view_produtos_mais_vendidos`;
USE `sylviartes`;
CREATE OR REPLACE VIEW view_produtos_mais_vendidos AS
SELECT
    produto.id,
    produto.nome,
    categoria.nome AS categoria,
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

-- -----------------------------------------------------
-- View `sylviartes`.`view_ultimas_encomendas`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sylviartes`.`view_ultimas_encomendas`;
USE `sylviartes`;
CREATE OR REPLACE VIEW view_ultimas_encomendas AS
SELECT
    pedido.id,
    utilizador.nome AS cliente,
    pedido.valor_total,
    pedido.estado,
    pedido.data AS data_pedido
FROM pedido
JOIN utilizador ON utilizador.id = pedido.utilizador_id
ORDER BY pedido.data DESC
LIMIT 10;
USE `sylviartes`;

DELIMITER $$
USE `sylviartes`$$
CREATE TRIGGER trg_validar_utilizador_antes_insert
BEFORE INSERT ON utilizador
FOR EACH ROW
BEGIN
    IF LENGTH(NEW.password) < 6 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Password deve ter pelo menos 6 caracteres.';
    END IF;

    IF NEW.email NOT REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Email inválido.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_utilizador_antes_update
BEFORE UPDATE ON utilizador
FOR EACH ROW
BEGIN
    IF LENGTH(NEW.password) < 6 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Password deve ter pelo menos 6 caracteres.';
    END IF;

    IF NEW.email NOT REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Email inválido.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_validar_avaliacao_antes_insert
BEFORE INSERT ON avaliacao
FOR EACH ROW
BEGIN
    IF NEW.estrelas < 1 OR NEW.estrelas > 5 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Estrelas devem ser entre 1 e 5.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_avaliacao_antes_update
BEFORE UPDATE ON avaliacao
FOR EACH ROW
BEGIN
    IF NEW.estrelas < 1 OR NEW.estrelas > 5 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Estrelas devem ser entre 1 e 5.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_validar_categoria_antes_insert
BEFORE INSERT ON categoria
FOR EACH ROW
BEGIN
    IF LENGTH(TRIM(NEW.nome)) = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Nome da categoria não pode ser vazio.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_categoria_antes_update
BEFORE UPDATE ON categoria
FOR EACH ROW
BEGIN
    IF LENGTH(TRIM(NEW.nome)) = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Nome da categoria não pode ser vazio.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_validar_pedido_antes_insert
BEFORE INSERT ON pedido
FOR EACH ROW
BEGIN
    IF NEW.prazo_entrega_desejado <= CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O prazo de entrega desejado deve ser uma data futura.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_pedido_antes_update
BEFORE UPDATE ON pedido
FOR EACH ROW
BEGIN
    IF NEW.prazo_entrega_desejado <= CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O prazo de entrega desejado deve ser uma data futura.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_validar_produto_antes_insert
BEFORE INSERT ON produto
FOR EACH ROW
BEGIN
    IF NEW.preco_base <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O preço base deve ser maior que zero.';
    END IF;

    IF NEW.stock IS NOT NULL AND NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O stock não pode ser negativo.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_produto_antes_update
BEFORE UPDATE ON produto
FOR EACH ROW
BEGIN
    IF NEW.preco_base <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O preço base deve ser maior que zero.';
    END IF;

    IF NEW.stock IS NOT NULL AND NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O stock não pode ser negativo.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_verificar_stock_antes_venda
BEFORE INSERT ON detalhe_pedido
FOR EACH ROW
BEGIN
    DECLARE stock_atual INT DEFAULT 0;

    SELECT IFNULL(produto.stock, 0)
      INTO stock_atual
      FROM produto
     WHERE produto.id = NEW.produto_id;

    IF stock_atual < NEW.quantidade THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Não há stock suficiente para este produto.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_verificar_stock_antes_update_detalhe
BEFORE UPDATE ON detalhe_pedido
FOR EACH ROW
BEGIN
    DECLARE stock_atual INT DEFAULT 0;

    SELECT IFNULL(produto.stock, 0)
      INTO stock_atual
      FROM produto
     WHERE produto.id = NEW.produto_id;

    IF NEW.quantidade <> OLD.quantidade AND NEW.quantidade > OLD.quantidade THEN
        IF stock_atual + OLD.quantidade < NEW.quantidade THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Não há stock suficiente para aumentar a quantidade.';
        END IF;
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_validar_mensagem_antes_insert
BEFORE INSERT ON mensagem_pedido
FOR EACH ROW
BEGIN
    IF NEW.remetente_tipo NOT IN ('admin', 'cliente') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Remetente tipo deve ser admin ou cliente.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_mensagem_antes_update
BEFORE UPDATE ON mensagem_pedido
FOR EACH ROW
BEGIN
    IF NEW.remetente_tipo NOT IN ('admin', 'cliente') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Remetente tipo deve ser admin ou cliente.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_validar_pagamento_antes_insert
BEFORE INSERT ON pagamento
FOR EACH ROW
BEGIN
    IF NEW.valor <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O valor do pagamento deve ser maior que zero.';
    END IF;
END$$

USE `sylviartes`$$
CREATE TRIGGER trg_pagamento_antes_update
BEFORE UPDATE ON pagamento
FOR EACH ROW
BEGIN
    IF NEW.valor <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O valor do pagamento deve ser maior que zero.';
    END IF;
END$$


DELIMITER ;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- -----------------------------------------------------
-- Data for table `sylviartes`.`utilizador`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`utilizador` (`id`, `nome`, `email`, `password`, `telefone`, `morada`, `codigo_postal`, `localidade`, `nivel_acesso`, `data_criacao`) VALUES (1, 'Administrador', 'admin@sylviartes.pt', 'admin123', '912345678', 'Rua Exemplo', '1000-001', 'Olhao', 'admin', NULL);
INSERT INTO `sylviartes`.`utilizador` (`id`, `nome`, `email`, `password`, `telefone`, `morada`, `codigo_postal`, `localidade`, `nivel_acesso`, `data_criacao`) VALUES (2, 'Joana Silva', 'joana.silva@email.com', 'pass1234', '912645342', 'Rua das Flores 10', '4000-001', 'Porto', 'cliente', NULL);
INSERT INTO `sylviartes`.`utilizador` (`id`, `nome`, `email`, `password`, `telefone`, `morada`, `codigo_postal`, `localidade`, `nivel_acesso`, `data_criacao`) VALUES (3, 'Carlos Pereira', 'carlos.p@email.com', 'segredo789', '927352413', 'Av. da Liberdade 50', '1000-050', 'Lisboa', 'cliente', NULL);
INSERT INTO `sylviartes`.`utilizador` (`id`, `nome`, `email`, `password`, `telefone`, `morada`, `codigo_postal`, `localidade`, `nivel_acesso`, `data_criacao`) VALUES (4, 'Maria Santos', 'maria.s@email.com', 'maria2024', '913376532', 'Praceta do Sol 3', '8000-100', 'Faro', 'cliente', NULL);
INSERT INTO `sylviartes`.`utilizador` (`id`, `nome`, `email`, `password`, `telefone`, `morada`, `codigo_postal`, `localidade`, `nivel_acesso`, `data_criacao`) VALUES (5, 'Ana Costa', 'ana.costa@email.com', 'costa123', '912375453', 'Rua do Mar 5', '9000-001', 'Funchal', 'cliente', NULL);

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`avaliacao`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`avaliacao` (`id`, `utilizador_id`, `estrelas`, `comentario`, `data`, `aprovado`) VALUES (1, 3, 5, 'Adorei o conjunto, ficou perfeito!', '2026-02-10 15:30:00', 1);
INSERT INTO `sylviartes`.`avaliacao` (`id`, `utilizador_id`, `estrelas`, `comentario`, `data`, `aprovado`) VALUES (2, 4, 4, 'Entrega rápida e produtos de qualidade.', '2026-02-12 09:45:00', 1);
INSERT INTO `sylviartes`.`avaliacao` (`id`, `utilizador_id`, `estrelas`, `comentario`, `data`, `aprovado`) VALUES (3, 2, 5, 'O bordado é  lindo, recomendo muito.', '2026-02-14 18:20:00', 0);

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`categoria`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`categoria` (`id`, `nome`, `descricao`) VALUES (1, 'Babetes', 'Babetes personalizados com frases divertidas ou nomes');
INSERT INTO `sylviartes`.`categoria` (`id`, `nome`, `descricao`) VALUES (2, 'Mantas', 'Mantas quentinhas polares com bordados');
INSERT INTO `sylviartes`.`categoria` (`id`, `nome`, `descricao`) VALUES (3, 'Sacos de Pano', 'Sacos de Pano personalizado ao seu gosto!');
INSERT INTO `sylviartes`.`categoria` (`id`, `nome`, `descricao`) VALUES (4, 'Fitas para chupas', 'Fitas para chupas personalizadas');
INSERT INTO `sylviartes`.`categoria` (`id`, `nome`, `descricao`) VALUES (5, 'Toalhas', 'Toalhas personalizadas ao seu gosto!');

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`pedido`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`pedido` (`id`, `utilizador_id`, `data`, `prazo_entrega_desejado`, `estado`, `valor_total`, `observacoes`, `tipo_entrega`, `morada_entrega`, `custo_envio`) VALUES (1, 2, '2026-05-01 10:00:00', '2026-06-15', 'aguarda_pagamento', 18.50, '', 'domicilio', 'Rua das Flores 10', 5.00);
INSERT INTO `sylviartes`.`pedido` (`id`, `utilizador_id`, `data`, `prazo_entrega_desejado`, `estado`, `valor_total`, `observacoes`, `tipo_entrega`, `morada_entrega`, `custo_envio`) VALUES (2, 3, '2026-05-02 14:30:00', '2026-06-20', 'em_producao', 30.00, '', 'levantamento_atelier', 'Atelier', 0.00);
INSERT INTO `sylviartes`.`pedido` (`id`, `utilizador_id`, `data`, `prazo_entrega_desejado`, `estado`, `valor_total`, `observacoes`, `tipo_entrega`, `morada_entrega`, `custo_envio`) VALUES (3, 2, '2026-05-03 09:15:00', '2026-06-22', 'em_analise', 22.00, '', 'domicilio', 'Rua das Flores 10', 5.00);
INSERT INTO `sylviartes`.`pedido` (`id`, `utilizador_id`, `data`, `prazo_entrega_desejado`, `estado`, `valor_total`, `observacoes`, `tipo_entrega`, `morada_entrega`, `custo_envio`) VALUES (4, 4, '2026-05-05 11:00:00', '2026-06-25', 'concluido', 35.00, '', 'levantamento_atelier', 'Atelier', 0.00);

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`produto`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (1, 1, 'Babete Sou o Maior', 'Babete impermeavel com frase bordada', 19.00, 1, 100);
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (2, 1, 'Babete com Nome', 'Babete simples com nome a escolha', 20.00, 1, 100);
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (3, 2, 'Manta Polar Ursinho', 'Manta com desenho de urso', 22.00, 1, 100);
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (4, 2, 'Manta de Berço', 'Manta leve para primavera', 18.00, 1, 100);
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (5, 3, 'Saco Primeira Roupa', 'Saco de tecido com cordao de fecho', 12.00, 1, 100);
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (6, 3, 'Saco para Infantario', 'Saco grande para muda de roupa', 15.00, 1, 100);
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (7, 4, 'Bastidor Nascimento', 'Quadro com dados de nascimento do bebe', 30.00, 1, 100);
INSERT INTO `sylviartes`.`produto` (`id`, `categoria_id`, `nome`, `descricao`, `preco_base`, `visivel_catalogo`, `stock`) VALUES (8, 4, 'Bastidor Porta Maternidade', 'Quadro com nome para a porta do quarto', 35.00, 1, 100);

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`detalhe_pedido`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`detalhe_pedido` (`id`, `pedido_id`, `produto_id`, `quantidade`, `preco_unitario`, `descricao`, `imagem_referencia`) VALUES (1, 1, 1, 1, 20.00, 'Frase: Sou o Maior da Aldeia', NULL);
INSERT INTO `sylviartes`.`detalhe_pedido` (`id`, `pedido_id`, `produto_id`, `quantidade`, `preco_unitario`, `descricao`, `imagem_referencia`) VALUES (2, 2, 7, 1, 30.00, 'Dados: Tiago, 3.5kg, 50cm', NULL);
INSERT INTO `sylviartes`.`detalhe_pedido` (`id`, `pedido_id`, `produto_id`, `quantidade`, `preco_unitario`, `descricao`, `imagem_referencia`) VALUES (3, 3, 3, 1, 22.00, 'Cor do urso: Azul claro', NULL);
INSERT INTO `sylviartes`.`detalhe_pedido` (`id`, `pedido_id`, `produto_id`, `quantidade`, `preco_unitario`, `descricao`, `imagem_referencia`) VALUES (4, 4, 8, 1, 35.00, 'Nome: Matilde', NULL);

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`mensagem_pedido`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`mensagem_pedido` (`id`, `pedido_id`, `remetente_tipo`, `mensagem`, `lida`, `data_envio`) VALUES (1, 1, 'cliente', 'Ja fiz o pagamento por MBWay podem confirmar?', 0, '2026-02-01 10:30:00');
INSERT INTO `sylviartes`.`mensagem_pedido` (`id`, `pedido_id`, `remetente_tipo`, `mensagem`, `lida`, `data_envio`) VALUES (2, 1, 'admin', 'Olá Joana vamos verificar e confirmamos em breve.', 1, '2026-02-01 11:00:00');
INSERT INTO `sylviartes`.`mensagem_pedido` (`id`, `pedido_id`, `remetente_tipo`, `mensagem`, `lida`, `data_envio`) VALUES (3, 2, 'cliente', 'Queria alterar a cor da linha para verde ainda vou a tempo?', 0, '2026-02-02 15:00:00');

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`pagamento`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`pagamento` (`id`, `pedido_id`, `metodo`, `data`, `valor`, `comprovativo`, `estado_pagamento`) VALUES (1, 1, 'mbway', DEFAULT, 18.50, NULL, 'analise_pagamento');
INSERT INTO `sylviartes`.`pagamento` (`id`, `pedido_id`, `metodo`, `data`, `valor`, `comprovativo`, `estado_pagamento`) VALUES (2, 2, 'transferencia', DEFAULT, 30.00, NULL, 'validado');
INSERT INTO `sylviartes`.`pagamento` (`id`, `pedido_id`, `metodo`, `data`, `valor`, `comprovativo`, `estado_pagamento`) VALUES (4, 4, 'dinheiro', DEFAULT, 35.00, NULL, 'validado');

COMMIT;


-- -----------------------------------------------------
-- Data for table `sylviartes`.`produto_imagem`
-- -----------------------------------------------------
START TRANSACTION;
USE `sylviartes`;
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (1, 1, NULL, 1);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (2, 1, NULL, 2);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (3, 2, NULL, 1);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (4, 2, NULL, 2);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (5, 3, NULL, 1);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (6, 3, NULL, 2);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (7, 4, NULL, 1);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (8, 5, NULL, 1);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (9, 6, NULL, 1);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (10, 7, NULL, 1);
INSERT INTO `sylviartes`.`produto_imagem` (`id`, `produto_id`, `imagem`, `ordem`) VALUES (11, 8, NULL, 1);

COMMIT;

