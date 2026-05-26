# Setup — Login de Clientes + Stripe

Guia rápido para preparar e testar a nova funcionalidade.

## 1. Aplicar alterações à base de dados

Abre o phpMyAdmin (ou `mysql` na linha de comandos) e corre o ficheiro:

```
docs/db/alter_login_stripe.sql
```

Isto adiciona o método `cartao` ao enum, e os campos `stripe_session_id` / `stripe_payment_intent_id` à tabela `pagamento`.

## 2. Instalar o SDK do Stripe

Na raiz do projeto (`sylviartes_site/`):

```bash
composer require stripe/stripe-php
```

> Se ainda não tens Composer: descarrega de https://getcomposer.org/Composer-Setup.exe

## 3. Configurar as chaves Stripe

Abre `config/stripe.php` e substitui as 3 constantes:

- `STRIPE_PUBLISHABLE_KEY` — `pk_test_...` (Dashboard → Developers → API keys)
- `STRIPE_SECRET_KEY` — `sk_test_...`
- `STRIPE_WEBHOOK_SECRET` — gerado pela Stripe CLI no passo 4

Cria uma conta de teste em https://dashboard.stripe.com/register e fica em **modo Test**.

## 4. Webhook (necessário para confirmar pagamentos)

Instala a Stripe CLI: https://docs.stripe.com/stripe-cli

```bash
stripe login
stripe listen --forward-to localhost:8080/public/stripe_webhook.php
```

A CLI vai mostrar um `whsec_...` — copia para `STRIPE_WEBHOOK_SECRET` em `config/stripe.php`.

## 5. Iniciar o servidor PHP

Da pasta `sylviartes_site/`:

```bash
php -S localhost:8080
```

Abre: http://localhost:8080/public/index.php

## 6. Testar

| Fluxo | Como |
|---|---|
| Criar conta | http://localhost:8080/public/cliente/registo.php |
| Login | http://localhost:8080/public/cliente/login.php |
| Editar perfil | Após login → "Os meus dados" |
| Compra com auto-fill | Estar logado → adicionar produto → carrinho → finalizar |
| Pagamento Cartão (Stripe) | Cartão de teste: `4242 4242 4242 4242`, CVC qualquer, data futura |
| Pagamento MB Way | Stripe vai pedir nº — em modo de teste podes simular |
| Transferência | Finalizar → ir à área cliente → upload do comprovativo |
| Cancelar pedido | Detalhe da encomenda → "Cancelar pedido" (só estados iniciais) |
| Admin valida | Login admin → encomendas → ver detalhes → "Validar pagamento" |

## Estrutura criada

```
config/
  stripe.php                    ← chaves + helper criar_checkout_session()

public/
  cliente/
    auth.php                    ← guard de sessão cliente
    cliente_style.css           ← estilos da área cliente
    login.php
    logout.php
    registo.php
    index.php                   ← dashboard "Minha Conta"
    perfil.php                  ← editar dados + password
    encomendas.php              ← lista de pedidos
    encomenda.php               ← detalhe + cancelar + upload comprovativo
  stripe_success.php            ← URL sucesso Stripe
  stripe_cancel.php             ← URL cancelamento Stripe
  stripe_retomar.php            ← reabrir pagamento Stripe de pedido existente
  stripe_webhook.php            ← endpoint público webhook

docs/
  db/alter_login_stripe.sql     ← ALTERs à BD

public/header.php               ← (modificado) menu com Login/Conta
public/pedido.php               ← (modificado) auto-fill + redirect Stripe
public/admin/encomendas/view.php← (modificado) validação manual de comprovativo
```

## Notas

- **Convidados continuam a poder comprar sem conta** — login é opcional.
- **Sem login**: o sistema continua a criar utilizador automático com password aleatória (como antes).
- **Stripe modo TESTE**: para o PAP é o adequado — não há dinheiro real envolvido. O professor pode ver os pagamentos no Dashboard Stripe.
- **HTTPS em produção**: a Stripe exige HTTPS para webhooks fora de localhost. Em `localhost` durante o teste funciona em HTTP.
