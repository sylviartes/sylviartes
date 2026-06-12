# SylviArtes

Plataforma web de **bordados personalizados por orçamento** (PAP). Os clientes pedem
orçamentos a partir do portfólio, a administradora define o valor e envia um link de
pagamento (Stripe), e o cliente acompanha a encomenda na sua área pessoal.

Site em produção: **https://sylviartes.pt**

> Para a defesa da PAP, ver também **`docs/GUIA_DEFESA.md`** (explica arquitetura,
> base de dados, segurança e perguntas prováveis do júri).

---

## Tecnologias
- **PHP 8.2** (sem framework) + **MySQL 8.0** (PDO, prepared statements)
- **Bootstrap 5** + CSS/JS próprios (interface responsiva)
- **Stripe** (pagamentos), **Resend** (emails)
- **Docker** (nginx + PHP-FPM + MySQL) em **AWS EC2**, com **Cloudflare** (DNS + HTTPS)

---

## Estrutura do projeto
```
public/            Raiz web (o que o servidor serve)
  index.php        Pagina inicial
  catalogo.php     Portfolio
  produto.php      Detalhe de um item
  pedir-orcamento.php   Formulario de pedido
  stripe_webhook.php    Recebe confirmacoes de pagamento do Stripe
  cliente/         Area do cliente (login, encomendas, perfil...)
  admin/           Painel de gestao (encomendas, produtos, categorias, avaliacoes)
  imagens/         Logo, CSS de animacoes, fotos
config/            Ligacao a BD, sessao, CSRF, .env
src/               Logica reutilizavel (email, avaliacoes, throttle de login)
docs/              Documentacao + SQL da base de dados
  db/bd_sylviartes.sql        Cria toda a base de dados (tabelas, views, triggers...)
  db/documentacao_bd.md       Explicacao da base de dados
  GUIA_DEFESA.md              Guia de apoio a defesa
vendor/            Bibliotecas (Stripe, PHPMailer) via Composer
```

---

## Como correr localmente (XAMPP)
1. Instalar o **XAMPP** (Apache + MySQL + PHP 8.2) e arrancar Apache e MySQL.
2. Copiar o projeto para `htdocs` (ou apontar o virtual host para a pasta `public/`).
3. Criar a base de dados: abrir o phpMyAdmin (ou MySQL Workbench) e **importar**
   `docs/db/bd_sylviartes.sql`. Cria a BD `sylviartes` com tudo.
4. Configurar os segredos:
   ```bash
   copy config\.env.example config\.env
   ```
   e preencher os valores (ver secção abaixo).
5. Abrir no browser: `http://localhost/public/` (ou o caminho onde puseste o projeto).

> Dependências PHP (Stripe, PHPMailer) já estão em `vendor/`. Se precisares de reinstalar:
> `composer install`.

---

## Variáveis de ambiente (`config/.env`)
Copiar de `config/.env.example` e preencher. Nunca commitar o `.env` (tem segredos).

| Variável | Para quê |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Ligação à base de dados |
| `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY` | Chaves do Stripe |
| `STRIPE_WEBHOOK_SECRET` | Validar os webhooks do Stripe |
| `SITE_BASE_URL` | URL base do site (para links nos emails) |
| `RESEND_API_KEY`, `RESEND_FROM` | Envio de emails |
| `SMTP_*` | Fallback de email opcional |

---

## Produção (resumo)
O site corre num servidor **AWS EC2 (Ubuntu)** com **Docker Compose** (3 containers:
nginx, PHP-FPM, MySQL). O **Cloudflare** trata do DNS e do HTTPS. O deploy é feito por
SSH (envio dos ficheiros) e a base de dados vive num **volume Docker persistente**.

```bash
# no servidor, na pasta do projeto:
sudo docker compose up -d        # arranca os 3 containers
sudo docker ps                   # ver estado
```

> A configuração de produção (`config/.env`, `docker-compose.yml`, `.docker/nginx.conf`)
> fica no servidor e **não** contém segredos no repositório.

---

## Segurança (resumo)
Passwords em bcrypt, prepared statements (anti SQL injection), tokens CSRF, throttle de
login, cabeçalhos de segurança no nginx, HTTPS, sessões seguras e pagamentos delegados ao
Stripe (o site nunca guarda dados de cartão). Detalhes em `docs/GUIA_DEFESA.md`.

---

*Projeto desenvolvido no âmbito da Prova de Aptidão Profissional (PAP).*
