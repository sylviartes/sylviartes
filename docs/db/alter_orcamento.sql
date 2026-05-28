-- =============================================================================
-- alter_orcamento.sql
-- Guarda o URL do link de pagamento Stripe para poder reenviar ao cliente.
-- Corre este ficheiro uma só vez no phpMyAdmin (BD sylviartes → SQL).
-- =============================================================================

-- Coluna para guardar o URL do Payment Link Stripe
-- (ex: https://buy.stripe.com/test_xxx)
ALTER TABLE pagamento
  ADD COLUMN IF NOT EXISTS stripe_payment_link_url VARCHAR(500) NULL;
