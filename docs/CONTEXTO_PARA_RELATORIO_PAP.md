# CONTEXTO PARA ESCREVER O RELATÓRIO DE PAP: PROJETO "SYLVIARTES"

> Cola tudo isto numa conversa nova com o Claude e pede: "Escreve o meu relatório de PAP
> a partir deste contexto, secção a secção, em português de Portugal."

## PARTE 1: INSTRUÇÕES PARA QUEM ESCREVE O RELATÓRIO

- Escreve em **português europeu** (não do Brasil). **Nunca** uses o travessão longo; usa
  parênteses, vírgulas ou dois pontos.
- Tom formal, na 1.ª pessoa ("desenvolvi", "escolhi"), como um aluno a explicar o SEU projeto.
- Segue a **estrutura da PARTE 2**. Deve ser longo e detalhado (o relatório-modelo tem cerca
  de 65 páginas). Cada secção com vários parágrafos.
- **Só usa factos da PARTE 3. NÃO INVENTES** funcionalidades que não estão aqui. Em especial:
  **o SylviArtes NÃO tem aplicação móvel**, não usa Flutter/Dart, nem Vercel, nem Aiven.
- Onde faltar um dado pessoal (nome da escola, curso, número, orientador, datas), deixa um
  **[PLACEHOLDER]** claro para eu preencher.
- Onde fizer sentido, marca onde inserir uma imagem com `[FIGURA: descrição]`
  (ex.: `[FIGURA: diagrama EER da base de dados no MySQL Workbench]`).
- Para cada tecnologia, usa o formato do modelo: **O que é** / **Como foi utilizado** /
  **Link oficial**.

## PARTE 2: ESTRUTURA-ALVO DO RELATÓRIO
(baseada no relatório-modelo E na estrutura obrigatória a-g da minha escola)

1. **Folha de rosto** (escola, título "Relatório da Prova de Aptidão Profissional",
   nome do projeto SylviArtes, autor, nº, curso, ano letivo, orientador, data): [PLACEHOLDERS].
2. **Página de Informações** (curso, ano letivo, ciclo, nome do relatório/projeto, aluno,
   nº, data de entrega, diretor de curso): [PLACEHOLDERS].
3. **Índice**.
4. **Introdução** (apresentação do aluno + fundamentação: o que é o projeto, porquê este
   tema, objetivos, tecnologias usadas em resumo).
5. **Tecnologias e Plataformas Utilizadas** (uma subsecção por tecnologia, formato
   O que é / Como foi utilizado / Link oficial).
6. **Planeamento e Cronograma do Projeto** (fases + tabela de fases + descrição de cada fase:
   Análise, Base de Dados, Website, Pagamentos/Stripe, Testes, Publicação).
7. **Apresentação da Empresa** (o negócio SylviArtes).
8. **Levantamento de Requisitos** (perfis: Administrador e Cliente. NÃO há "funcionário").
9. **Requisitos Funcionais**.
10. **Requisitos Não Funcionais** (Segurança, Desempenho, Usabilidade, Escalabilidade,
    Disponibilidade).
11. **Arquitetura Geral do Sistema**.
12. **Desenvolvimento da Base de Dados** (Modelação, Organização das Tabelas, Relacionamentos,
    Integridade, Otimização; falar de views/procedures/triggers).
13. **Sistema de Gestão de Encomendas por Orçamento** (o núcleo do projeto: ciclo de vida do
    pedido, orçamento manual, link Stripe, estados, log de auditoria).
14. **Desenvolvimento do Website** (Arquitetura modular, Autenticação, Registo de clientes,
    Recuperação de palavra-passe, Dashboard admin, Gestão de Categorias, Gestão de Produtos/
    Portfólio, Gestão de Encomendas, Área do Cliente, Avaliações/Testemunhos).
15. **Sistema de Pagamentos (Stripe)** (Checkout, Cartão + MB Way + Multibanco, webhook,
    páginas de sucesso/cancelamento).
16. **Sistema de Comunicação e Notificações por Email** (Resend/PHPMailer: aviso de pedido
    novo à admin, link de pagamento, mudanças de estado, recuperação de password, validação).
17. **Comunicação entre o Website e a Base de Dados** (PDO + prepared statements).
18. **Implementação e Publicação** (Docker, AWS EC2, Cloudflare, configuração de produção,
    variáveis de ambiente, vantagens).
19. **Melhorias Futuras**.
20. **Conclusão**.
21. **Análise Crítica Global** (dificuldades e como as superei).
22. **Bibliografia e Webgrafia**.
23. **Anexos** (registos de autoavaliação por fase + avaliações intermédias do orientador):
    [deixar modelo em branco para eu preencher].

## PARTE 3: FACTOS REAIS DO SYLVIARTES (fonte de verdade)

### 3.1 O que é o SylviArtes
Plataforma web para um **negócio familiar de bordados personalizados** (peças feitas à
medida: babetes, fraldas, toalhas, kits de batizado, etc.). Como cada peça é única e o preço
depende do que o cliente pede, o site **não é uma loja de preços fixos**: funciona **por
pedido de orçamento**. Está online em **https://sylviartes.pt**.

Fluxo do negócio:
1. O cliente vê o portfólio e pede um orçamento (descreve o que quer, com fotos de inspiração).
2. A administradora (a Sylvia) analisa, define o valor e envia um link de pagamento.
3. O cliente paga online (cartão, MB Way ou Multibanco) e acompanha o estado da encomenda.

Objetivos: presença online profissional, automatizar a recolha de pedidos, os pagamentos e a
comunicação com o cliente, e ter um painel de gestão simples.

### 3.2 Tecnologias (com o "porquê" para o formato do modelo)
| Tecnologia | Para quê | Porquê / Como foi usada |
|---|---|---|
| **PHP 8.2 (sem framework)** | Lógica do servidor (backend) | Domínio da linguagem; sem framework percebo e explico cada linha. Gera as páginas, valida dados, gere sessões. |
| **MySQL 8.0** | Base de dados relacional | Relações claras entre clientes, pedidos, produtos e pagamentos. |
| **PDO + Prepared Statements** | Acesso à BD | Seguro contra SQL injection, portável. Todas as queries são preparadas. |
| **HTML5, CSS3, Bootstrap 5** | Interface | Bootstrap para responsividade rápida; CSS próprio para o visual da marca. |
| **JavaScript (vanilla)** | Interação | Estrelas de avaliação, upload de fotos, sem dependências pesadas. |
| **Stripe** | Pagamentos online | Plataforma certificada PCI; suporta Cartão, MB Way e Multibanco. O site nunca toca em dados de cartão. Usa Checkout Sessions + Webhooks. |
| **Resend + PHPMailer** | Envio de emails | Enviar do domínio verificado (noreply@sylviartes.pt) de forma fiável. |
| **Docker (Compose)** | Empacotamento/alojamento | nginx + PHP-FPM + MySQL em containers iguais em qualquer servidor. |
| **AWS EC2 (Ubuntu)** | Servidor cloud | Onde corre o Docker, acessível pela Internet. |
| **Cloudflare** | DNS + HTTPS | HTTPS grátis, proteção e aceleração. |
| **Git / GitHub** | Controlo de versões + backup | Histórico de alterações e cópia de segurança do código. |
| **Composer** | Gestão de dependências PHP | Instala o SDK do Stripe e o PHPMailer (pasta vendor/). |
| **MySQL Workbench** | Modelação da BD | Diagrama EER, ligação à BD de produção via túnel SSH (127.0.0.1:3306). |
| **VS Code** | Editor de código | IDE principal do desenvolvimento. |
| **XAMPP** | Ambiente local | Correr o site no computador durante o desenvolvimento (antes do Docker/produção). |

Links oficiais: php.net, mysql.com, getbootstrap.com, stripe.com, resend.com, docker.com,
aws.amazon.com, cloudflare.com, github.com, getcomposer.org, code.visualstudio.com,
apachefriends.org (XAMPP).

### 3.3 Base de dados (12 tabelas, 7 views, 4 procedures, 16 triggers)
BD MySQL `sylviartes`, motor InnoDB, `utf8mb4`. Ficheiro: `docs/db/bd_sylviartes.sql`.

**12 tabelas:**
1. `utilizador`: clientes e administradores (`nivel_acesso`), password em bcrypt, email único.
2. `categoria`: tipos de peça (Babetes, Toalhas, Kits de Batizado...).
3. `produto`: trabalhos do portfólio; `preco_base` pode ser 0 (modelo por orçamento); FK categoria.
4. `produto_imagem`: várias fotos por produto (ordem=1 é a principal); FK produto.
5. `pedido`: pedido de orçamento; `estado` percorre o ciclo de vida; FK utilizador.
6. `detalhe_pedido`: produtos que compõem cada pedido (N:N com personalização); FK pedido, produto.
7. `pagamento`: pagamento de cada pedido; guarda URL da checkout/fatura Stripe; FK pedido.
8. `avaliacao`: estrelas (1-5) + comentário, sujeita a moderação; FK utilizador, produto.
9. `pedido_inspiracao`: até 3 fotos de inspiração em MEDIUMBLOB; FK pedido.
10. `mensagem_pedido`: conversa cliente/admin dentro de um pedido; FK pedido.
11. `log_alteracoes_pedido`: histórico de mudanças de estado (auditoria); FK pedido.
12. `password_reset`: tokens de recuperação de password (validade 1h); FK utilizador.

**7 views:** view_dashboard_completo, view_melhores_clientes, view_produtos_mais_vendidos,
view_pedidos_pendentes_detalhado, view_produtos_disponiveis, view_ultimas_encomendas,
view_aviso_stock_baixo. (Consultas pré-preparadas que alimentam o painel de gestão.)

**4 stored procedures:** gerir_stock_produto, obter_totais_dashboard,
processar_pedido_completo (calcula 5€ de portes se for ao domicílio), relatorio_vendas_mensal.

**16 triggers** (validação automática, `SIGNAL SQLSTATE '45000'`): validam password com pelo
menos 6 caracteres e email na tabela utilizador; estrelas 1-5 na avaliacao; nome não vazio na
categoria; prazo de entrega futuro no pedido; preço/stock não negativos no produto; stock
suficiente no detalhe_pedido; remetente 'admin'/'cliente' na mensagem; valor maior que zero no
pagamento. (Cada um em BEFORE INSERT e BEFORE UPDATE.)

Detalhe importante: como as peças são à medida, o `stock` é frequentemente `NULL`
(sem stock fixo); os triggers tratam NULL como "à medida/ilimitado". `preco_base = 0` é
permitido (orçamento).

### 3.4 Ciclo de vida de um pedido (estados)
`aguarda_orcamento` -> `em_analise` -> `aguarda_pagamento` -> `em_producao` -> `concluido` ->
`entregue` (e `cancelado` em qualquer altura). Cada mudança fica registada em
`log_alteracoes_pedido`.

### 3.5 Fluxo completo de uma encomenda
1. Cliente preenche `pedir-orcamento.php` (descrição, tipo de entrega, fotos de inspiração).
   Cria-se um `pedido` em análise. (Login opcional, mas há conta para acompanhar.)
2. A administradora recebe email (via Resend) a avisar de pedido novo.
3. No painel admin, a Sylvia define o `valor_total` e envia o link de pagamento Stripe por
   email (`enviar_link.php`, que cria uma Stripe Checkout Session).
4. Cliente paga (Cartão / MB Way / Multibanco) na página segura do Stripe.
5. O Stripe avisa o site por **webhook** (`stripe_webhook.php`): eventos `checkout.session.*`
   e `invoice.*`. O site marca o pagamento como validado e avança o pedido para em produção.
6. A Sylvia muda o estado (em produção -> concluído -> entregue); a cada mudança o cliente
   recebe um email automático.
7. Depois de concluída, o cliente pode avaliar a encomenda (estrelas + comentário); após
   aprovação no admin, aparece como testemunho na página inicial.

### 3.6 Arquitetura (diagrama para incluir)
```
Cliente (browser, HTTPS)
        |
        v
   Cloudflare  (DNS + HTTPS/SSL)
        |  (HTTP porta 80)
        v
+----------- Servidor AWS EC2 (Docker) -----------+
|  nginx --FastCGI :9000--> PHP-FPM --PDO :3306--> MySQL  |
+-------------------------------------------------+
        |                         |
        v                         v
     Stripe (pagamentos)      Resend (emails)
```
3 containers Docker: `meu-nginx-pap` (porta 80), `meu-php-pap` (PHP-FPM), `meu-mysql-pap`
(MySQL, volume persistente). O docroot é a pasta `public/`. Versão Mermaid no
`docs/GUIA_DEFESA.md`.

### 3.7 Estrutura de pastas do projeto
- `config/`: db.php (PDO), stripe.php, env.php, session.php, csrf.php.
- `src/`: código partilhado: email.php, cart.php, avaliacoes.php, breadcrumbs.php,
  login_throttle.php.
- `public/`: docroot. Páginas públicas (index, catalogo, produto, sobre, contacto,
  pedir-orcamento, sitemap, 404, header/footer), stripe_success/cancel/retomar/webhook.
  - `public/admin/`: painel: login, dashboard (index), categorias, produtos, encomendas
    (index/view/enviar_link/delete), avaliacoes, sidebar, admin_style.css.
  - `public/cliente/`: área do cliente: registo, login, perfil, encomendas, encomenda,
    esqueci_password, nova_password, cliente_style.css.
- `docs/`: documentação (bd_sylviartes.sql, documentacao_bd.md, GUIA_DEFESA.md, etc.).
- `vendor/`: dependências Composer (Stripe SDK, PHPMailer).

### 3.8 Segurança (para Requisitos Não Funcionais / Segurança)
- Passwords em **bcrypt** (`password_hash`/`password_verify`), nunca em texto.
- **Prepared statements (PDO)** em todas as queries (anti SQL injection).
- **htmlspecialchars** ao mostrar dados (anti XSS).
- **Tokens CSRF** nos formulários (config/csrf.php).
- **Throttle de login**: bloqueia 5 minutos após 5 tentativas falhadas (src/login_throttle.php),
  no admin e no cliente.
- **Cabeçalhos de segurança HTTP** no nginx: X-Frame-Options, X-Content-Type-Options,
  Referrer-Policy, Permissions-Policy, Content-Security-Policy.
- **HTTPS** em todo o site (Cloudflare).
- **Sessões seguras** + `session_regenerate_id` no login (anti session fixation).
- **Verificação de dono**: cada cliente só vê os seus pedidos (`WHERE utilizador_id = ?`).
- **Pagamentos no Stripe** (certificado PCI): o site nunca recebe nem guarda dados de cartão.
- **Segredos no `.env`** (fora do código e do git).

### 3.9 Perguntas prováveis do júri
Ver `docs/GUIA_DEFESA.md` secção 7: porquê sem framework; isolamento de contas por
utilizador_id; anti SQL injection; segurança dos pagamentos (Stripe PCI); modelo por
orçamento (stock NULL); webhook ao pagar; porquê Docker; alojamento (EC2+Cloudflare);
responsividade (cartões no telemóvel).

### 3.10 Dificuldades reais superadas (para a Análise Crítica Global)
- Integrar **Stripe com webhooks** e migrar de Faturas para **Checkout Sessions** para o
  **MB Way funcionar** (Faturas não suportam MB Way; Checkout suporta Cartão + MB Way + Multibanco).
- Corrigir o **link do email** que apontava para localhost (passou a usar SITE_BASE_URL de
  confiança, resolvendo também uma vulnerabilidade de Host Header Injection).
- **Estabilidade do servidor**: o MySQL era morto por falta de memória (OOM, 21 vezes);
  resolvido com um **swap de 1 GB** + limpeza de disco (imagens/volumes Docker antigos).
- Fuso horário português na BD e cache-busting do CSS (`?v=N` / filemtime).
- Tornar o site **responsivo no telemóvel** (tabelas de encomendas/avaliações viram cartões).

### 3.11 Diferenças a NÃO esquecer (vs. o relatório-modelo FISIOESTETIC)
- **NÃO há app móvel, nem Flutter, nem Dart, nem "API para mobile".** É só website.
- Núcleo NÃO são "marcações/slots": é **pedidos de orçamento + pagamento**.
- Alojamento é **Docker + AWS EC2 + Cloudflare**, NÃO Vercel; BD é **MySQL em container no
  EC2** (Workbench por túnel SSH), NÃO Aiven.
- Há 2 perfis (Administrador e Cliente), NÃO 3 (não há "Funcionário").

### 3.12 Dados pessoais / capa (a preencher pelo Tomás): [PLACEHOLDERS]
Escola: [ESCOLA] · Curso: [CURSO] · Ano letivo: [ANO] · Ciclo/ano: [ANO] · Autor: [NOME] ·
Número: [Nº] · Orientador/Diretor de curso: [ORIENTADOR] · Data de entrega: [DATA].
