<?php
/**
 * =============================================================================
 *  CARRINHO DE COMPRAS — SylviArtes
 * =============================================================================
 *
 *  Conjunto de funções que gerem o carrinho do utilizador.
 *  O carrinho é guardado em $_SESSION['cart'] (na sessão do utilizador no
 *  servidor) — assim não se perde quando se navega entre páginas, mas também
 *  não fica gravado na base de dados até o utilizador finalizar a compra.
 *
 *  Estrutura do carrinho na sessão:
 *      $_SESSION['cart'] = [
 *          15 => ['quantity' => 2, 'customization' => 'Bordar com nome João'],
 *          22 => ['quantity' => 1, 'customization' => ''],
 *      ];
 *  (a chave do array é o ID do produto)
 * =============================================================================
 */

/**
 * Garante que a estrutura do carrinho existe na sessão.
 * Chamada antes de qualquer operação para evitar avisos de array indefinido.
 */
function init_cart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

/**
 * Adiciona um produto ao carrinho (ou aumenta a quantidade se já lá estiver).
 * Verifica o stock disponível na BD antes de adicionar — não deixa pôr mais
 * do que existe.
 *
 * @return bool  true se conseguiu adicionar, false se houve problema
 */
function add_to_cart($conn, $product_id, $quantity = 1, $customization = '') {
    init_cart();

    // Casting para inteiro — protege contra valores não numéricos do POST
    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    if ($product_id <= 0 || $quantity <= 0) {
        return false; // valores inválidos
    }

    // Confirma que o produto existe e está visível no catálogo
    $stmt = $conn->prepare("SELECT stock FROM produto WHERE id = ? AND visivel_catalogo = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        return false; // produto não existe ou está oculto
    }

    $stock = $product['stock'];
    // Quanto já existe no carrinho deste produto (0 se for a primeira vez)
    $current_quantity = $_SESSION['cart'][$product_id]['quantity'] ?? 0;

    // Verificação de stock — só aplica se a coluna stock não for NULL
    if ($stock !== null) {
        $available = $stock - $current_quantity;
        if ($available <= 0) return false; // sem stock disponível
        if ($quantity > $available) $quantity = $available; // limita ao disponível
    }

    // Se o produto já está no carrinho, soma. Caso contrário, cria novo.
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = [
            'quantity' => $quantity,
            'customization' => trim($customization)
        ];
    }

    return true;
}

/**
 * Remove completamente um produto do carrinho.
 */
function remove_from_cart($product_id) {
    init_cart();

    $product_id = (int)$product_id;

    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        return true;
    }

    return false;
}

/**
 * Atualiza a quantidade de um produto no carrinho.
 * Se a nova quantidade for 0 ou menor, remove o produto.
 */
function update_cart_quantity($product_id, $quantity) {
    init_cart();

    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    if ($quantity <= 0) {
        return remove_from_cart($product_id);
    }

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
        return true;
    }

    return false;
}

/**
 * Devolve a lista completa de itens do carrinho com dados do produto
 * (nome, preço, imagem) e o total a pagar.
 *
 * Faz UMA SÓ query à BD para todos os produtos do carrinho — mais eficiente
 * do que fazer uma query por produto.
 *
 * @return array  ['items' => [...], 'total' => float]
 */
function get_cart_items($conn) {
    init_cart();

    $cart_items = [];
    $total = 0;

    if (!empty($_SESSION['cart'])) {
        // IDs de todos os produtos no carrinho
        $product_ids = array_keys($_SESSION['cart']);

        // Cria placeholders dinâmicos: "?,?,?" para usar com IN (...)
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

        // Query: produto + primeira imagem (ordem = 1)
        $stmt = $conn->prepare("
            SELECT
                produto.id,
                produto.nome,
                produto.preco_base,
                produto_imagem.imagem
            FROM produto
            LEFT JOIN produto_imagem
                ON produto.id = produto_imagem.produto_id
                AND produto_imagem.ordem = 1
            WHERE produto.id IN ($placeholders)
            AND produto.visivel_catalogo = 1
        ");

        $stmt->execute($product_ids);

        // Combina os dados da BD com a quantidade/customização da sessão
        while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $product_id = $product['id'];
            $quantity = $_SESSION['cart'][$product_id]['quantity'];
            $customization = $_SESSION['cart'][$product_id]['customization'];

            $cart_items[] = [
                'id' => $product_id,
                'nome' => $product['nome'],
                'preco_base' => (float)$product['preco_base'],
                'imagem' => $product['imagem'], // BLOB — convertido para base64 nas views
                'quantity' => $quantity,
                'customization' => $customization,
                'subtotal' => $product['preco_base'] * $quantity
            ];

            $total += $product['preco_base'] * $quantity;
        }
    }

    return ['items' => $cart_items, 'total' => $total];
}

/** Atalho que devolve apenas o total monetário do carrinho. */
function get_cart_total($conn) {
    return get_cart_items($conn)['total'];
}

/**
 * Conta o número total de unidades no carrinho (somando quantidades).
 * Usado para o badge no ícone do carrinho no header.
 */
function get_cart_count() {
    init_cart();
    $count = 0;

    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }

    return $count;
}

/** Esvazia o carrinho. Chamado após finalizar com sucesso uma compra. */
function clear_cart() {
    $_SESSION['cart'] = [];
}

/** Devolve true se o carrinho não tiver nenhum produto. */
function is_cart_empty() {
    init_cart();
    return empty($_SESSION['cart']);
}
