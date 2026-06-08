# Documentação da Base de Dados — SylviArtes

Base de dados relacional **MySQL** (`sylviartes`), motor **InnoDB**, codificação `utf8mb4`.
Esta é a estrutura completa: **12 tabelas**, **7 views**, **4 stored procedures** e **16 triggers**.

> A base de dados não se limita a guardar dados: usa **views** (consultas pré-preparadas para
> relatórios), **stored procedures** (lógica de negócio no servidor) e **triggers** (regras de
> validação automáticas), demonstrando um uso avançado de SQL.

---

## 1. Como gerar o modelo (diagrama EER) no MySQL Workbench

No diagrama EER **só as tabelas** aparecem como caixas, com as linhas das relações (chaves
estrangeiras). As views, procedures e triggers não são desenhadas no diagrama: ficam listadas
na árvore do schema, ao lado, e podem ser abertas individualmente.

**Engenharia reversa da base de dados (recomendado):**
1. Abrir o **MySQL Workbench**.
2. Menu **Database → Reverse Engineer** (Ctrl+R).
3. Escolher a ligação ao MySQL local (XAMPP) e o schema **`sylviartes`**.
4. O Workbench desenha automaticamente o **diagrama com as 12 tabelas** e as relações, e
   importa também as views, procedures e triggers (visíveis no painel lateral).
5. Arrastar as tabelas para organizar o diagrama e exportar como imagem
   (**File → Export → Export as PNG/SVG**) para inserir no relatório.

**Alternativa (a partir de ficheiro):** Menu **File → Import → Reverse Engineer MySQL Create
Script** e escolher o `docs/db/setup_completo.sql` ou o dump principal.

---

## 2. Tabelas (12)

| # | Tabela | Função | Relações (FK) |
|---|--------|--------|---------------|
| 1 | **utilizador** | Clientes e administradores (distinguidos por `nivel_acesso`). Password em hash bcrypt, email único. | — |
| 2 | **categoria** | Tipos de peça do portfólio (Babetes, Toalhas, Kits de Batizado...). | — |
| 3 | **produto** | Cada trabalho/modelo do portfólio. `preco_base` pode ser 0 (modelo por orçamento). | `categoria_id` → categoria |
| 4 | **produto_imagem** | Várias fotografias por produto (a de `ordem = 1` é a principal). | `produto_id` → produto |
| 5 | **pedido** | Pedido de orçamento de um cliente. `estado` percorre o ciclo de vida da encomenda. | `utilizador_id` → utilizador |
| 6 | **detalhe_pedido** | Produtos que compõem cada pedido (ligação N:N com personalização). | `pedido_id` → pedido, `produto_id` → produto |
| 7 | **pagamento** | Pagamento de cada pedido. Guarda o URL da fatura/checkout Stripe. | `pedido_id` → pedido |
| 8 | **avaliacao** | Estrelas + comentário de um cliente sobre um produto (sujeita a moderação). | `utilizador_id` → utilizador, `produto_id` → produto |
| 9 | **pedido_inspiracao** | Fotos de inspiração anexadas ao pedido (até 3, guardadas em binário `MEDIUMBLOB`). | `pedido_id` → pedido |
| 10 | **mensagem_pedido** | Conversa entre cliente e administrador dentro de um pedido. | `pedido_id` → pedido |
| 11 | **log_alteracoes_pedido** | Histórico de mudanças de estado de cada pedido (auditoria). | `pedido_id` → pedido |
| 12 | **password_reset** | Tokens temporários para recuperação de palavra-passe (validade de 1h). | `utilizador_id` → utilizador |

---

## 3. Views (7)

As views são consultas guardadas que simplificam o código PHP e alimentam o painel de gestão.

| View | O que devolve |
|------|---------------|
| **view_dashboard_completo** | Linha única com os indicadores do dashboard: faturação total, ticket médio, total de clientes, novos clientes (30 dias), produtos com stock baixo, pedidos pendentes/em produção, receita dos últimos 30 dias e receita do mês atual. |
| **view_melhores_clientes** | Clientes ordenados pelo total gasto (número de encomendas e valor), excluindo pedidos cancelados. |
| **view_produtos_mais_vendidos** | Produtos ordenados pela quantidade vendida, com receita total, nº de pedidos e preço médio de venda. |
| **view_pedidos_pendentes_detalhado** | Pedidos em análise/aguarda pagamento, com dados do cliente e a lista de produtos concatenada. |
| **view_produtos_disponiveis** | Produtos visíveis no catálogo e com stock, já com a imagem principal e o nome da categoria. |
| **view_ultimas_encomendas** | As 10 encomendas mais recentes (cliente, valor, estado, data). |
| **view_aviso_stock_baixo** | Produtos com stock inferior a 10 unidades (alerta de reposição). |

---

## 4. Stored Procedures (4)

Procedimentos que encapsulam lógica de negócio do lado do servidor.

| Procedure | Parâmetros | O que faz |
|-----------|-----------|-----------|
| **gerir_stock_produto** | `produto_id`, `quantidade`, `operacao` (adicionar/remover/definir), `motivo` | Ajusta o stock de um produto. Ao remover, valida que há stock suficiente (senão lança erro). |
| **obter_totais_dashboard** | — | Devolve os totais rápidos do dashboard (faturação, pedidos pendentes, total de clientes, alerta de stock). |
| **processar_pedido_completo** | dados do pedido + `OUT pedido_id` | Cria um pedido novo, calcula o custo de envio (5€ se for ao domicílio) e devolve o ID criado. |
| **relatorio_vendas_mensal** | `ano`, `mes` | Relatório de vendas de um mês: total de pedidos, faturação, ticket médio, entregas ao domicílio e total de portes. |

---

## 5. Triggers (16)

Os triggers garantem **integridade e regras de negócio automaticamente**, antes de cada
inserção/atualização. Quando uma regra é violada, lançam `SIGNAL SQLSTATE '45000'` com uma
mensagem, impedindo a operação.

| Trigger | Tabela / Momento | Regra que valida |
|---------|------------------|------------------|
| trg_validar_utilizador_antes_insert | utilizador · BEFORE INSERT | Password ≥ 6 caracteres e email com formato válido |
| trg_utilizador_antes_update | utilizador · BEFORE UPDATE | Igual ao anterior, ao atualizar |
| trg_validar_avaliacao_antes_insert | avaliacao · BEFORE INSERT | Estrelas entre 1 e 5 |
| trg_avaliacao_antes_update | avaliacao · BEFORE UPDATE | Estrelas entre 1 e 5 |
| trg_validar_categoria_antes_insert | categoria · BEFORE INSERT | Nome da categoria não vazio |
| trg_categoria_antes_update | categoria · BEFORE UPDATE | Nome da categoria não vazio |
| trg_validar_pedido_antes_insert | pedido · BEFORE INSERT | Prazo de entrega tem de ser uma data futura |
| trg_pedido_antes_update | pedido · BEFORE UPDATE | Prazo de entrega tem de ser uma data futura |
| trg_validar_produto_antes_insert | produto · BEFORE INSERT | Preço base não negativo; stock não negativo (preço 0 é permitido) |
| trg_produto_antes_update | produto · BEFORE UPDATE | Igual ao anterior, ao atualizar |
| trg_verificar_stock_antes_venda | detalhe_pedido · BEFORE INSERT | Há stock suficiente para a quantidade pedida |
| trg_verificar_stock_antes_update_detalhe | detalhe_pedido · BEFORE UPDATE | Há stock suficiente ao aumentar a quantidade |
| trg_validar_mensagem_antes_insert | mensagem_pedido · BEFORE INSERT | Remetente é 'admin' ou 'cliente' |
| trg_mensagem_antes_update | mensagem_pedido · BEFORE UPDATE | Remetente é 'admin' ou 'cliente' |
| trg_validar_pagamento_antes_insert | pagamento · BEFORE INSERT | Valor do pagamento maior que zero |
| trg_pagamento_antes_update | pagamento · BEFORE UPDATE | Valor do pagamento maior que zero |

> **Nota sobre os triggers de produto:** localmente, os triggers `trg_validar_produto_*`
> permitem `preço = 0` (essencial para o modelo por orçamento). Na base de dados de produção
> existiam versões mais antigas e restritivas (exigiam preço > 0) que tiveram de ser
> **removidas** durante a publicação, porque impediam a criação de produtos sem preço fixo.

---

## 6. Ciclo de vida de um pedido (estados)

O campo `pedido.estado` percorre, tipicamente, a seguinte sequência:

```
aguarda_orcamento → em_analise → aguarda_pagamento → em_producao → concluido → entregue
                                                   ↘ cancelado (em qualquer altura)
```

- **aguarda_orcamento / em_analise** — pedido submetido, à espera de orçamento da proprietária.
- **aguarda_pagamento** — orçamento enviado (fatura Stripe); à espera de pagamento.
- **em_producao** — pagamento confirmado; peça a ser feita.
- **concluido / entregue** — peça terminada e entregue.
- **cancelado** — pedido cancelado.

Cada mudança de estado fica registada na tabela `log_alteracoes_pedido` (auditoria).
