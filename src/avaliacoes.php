<?php
/**
 * =============================================================================
 *  AVALIAÇÕES DE PRODUTOS — Helpers
 * =============================================================================
 *
 *  Funções utilitárias para o sistema de avaliações (estrelas + comentário):
 *    - cliente_pode_avaliar()     → verifica se um cliente pode avaliar X
 *    - obter_avaliacoes_produto() → lista as avaliações aprovadas
 *    - calcular_media_estrelas()  → média + nº de avaliações por produto
 *    - render_estrelas()          → HTML de estrelas (cheias/vazias)
 * =============================================================================
 */

/**
 * Verifica se a coluna `produto_id` existe na tabela `avaliacao`.
 * Se a SQL alter_avaliacoes.sql ainda não foi aplicada, devolve false e
 * todas as funções abaixo desativam-se graciosamente (sem crash).
 *
 * Resultado em cache estática para evitar query repetida em cada chamada.
 */
function avaliacoes_disponiveis(PDO $conn): bool
{
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $stmt = $conn->query("SHOW COLUMNS FROM avaliacao LIKE 'produto_id'");
        $cache = (bool)$stmt->fetch();
    } catch (Exception $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Um cliente pode deixar uma avaliação SE:
 *   1. Estiver autenticado
 *   2. Já tiver pelo menos UM pedido entregue/concluído (qualquer pedido)
 *   3. Ainda não tiver avaliado este item específico do portfólio
 *
 * Adaptado para o modelo de orçamento personalizado: como cada peça é
 * personalizada e raramente corresponde a um produto específico, basta
 * o cliente ter completado pelo menos uma encomenda para poder partilhar
 * a sua experiência sobre qualquer item do portfólio.
 */
function cliente_pode_avaliar(PDO $conn, int $clienteId, int $produtoId): bool
{
    if (!avaliacoes_disponiveis($conn)) return false;
    if ($clienteId <= 0 || $produtoId <= 0) return false;

    // Já avaliou este item? (chave UNIQUE garante 1 avaliação por cliente/produto)
    $stmt = $conn->prepare("SELECT 1 FROM avaliacao WHERE utilizador_id = ? AND produto_id = ?");
    $stmt->execute([$clienteId, $produtoId]);
    if ($stmt->fetch()) return false;

    // Tem pelo menos um pedido entregue/concluído?
    $stmt = $conn->prepare("
        SELECT 1 FROM pedido
        WHERE utilizador_id = ?
          AND estado IN ('concluido', 'entregue')
        LIMIT 1
    ");
    $stmt->execute([$clienteId]);
    return (bool)$stmt->fetch();
}

/**
 * Verifica se o cliente tem pelo menos 1 pedido elegível para avaliar
 * (entregue/concluído). Usado para mostrar convite "Deixe-nos uma avaliação"
 * em vários sítios.
 */
function cliente_tem_pedido_avaliavel(PDO $conn, int $clienteId): bool
{
    if ($clienteId <= 0) return false;
    $stmt = $conn->prepare("
        SELECT 1 FROM pedido
        WHERE utilizador_id = ?
          AND estado IN ('concluido', 'entregue')
        LIMIT 1
    ");
    $stmt->execute([$clienteId]);
    return (bool)$stmt->fetch();
}

/**
 * Devolve as avaliações APROVADAS de um produto, ordenadas da mais recente.
 * Inclui o nome do cliente para mostrar.
 */
function obter_avaliacoes_produto(PDO $conn, int $produtoId): array
{
    if (!avaliacoes_disponiveis($conn)) return [];
    $stmt = $conn->prepare("
        SELECT a.estrelas, a.comentario, a.data, u.nome
        FROM avaliacao a
        JOIN utilizador u ON u.id = a.utilizador_id
        WHERE a.produto_id = ? AND a.aprovado = 1
        ORDER BY a.data DESC
    ");
    $stmt->execute([$produtoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcula a média de estrelas e o nº de avaliações aprovadas de um produto.
 * Devolve ['media' => 4.3, 'total' => 12] ou ['media' => 0, 'total' => 0].
 */
function calcular_media_estrelas(PDO $conn, int $produtoId): array
{
    if (!avaliacoes_disponiveis($conn)) {
        return ['media' => 0, 'total' => 0];
    }
    $stmt = $conn->prepare("
        SELECT AVG(estrelas) AS media, COUNT(*) AS total
        FROM avaliacao
        WHERE produto_id = ? AND aprovado = 1
    ");
    $stmt->execute([$produtoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'media' => $row['media'] !== null ? round((float)$row['media'], 1) : 0,
        'total' => (int)$row['total'],
    ];
}

/**
 * Devolve HTML de estrelas para um valor entre 0 e 5.
 * Usa font-awesome (já carregado pelo header.php).
 *
 * @param float $valor    Estrelas a mostrar (ex: 4.3)
 * @param bool  $pequeno  Se true, usa tamanho menor (para listagens)
 */
function render_estrelas(float $valor, bool $pequeno = false): string
{
    $tamanho = $pequeno ? '0.85em' : '1em';
    $cheias = (int)floor($valor);                // 4 estrelas cheias
    $meia   = ($valor - $cheias) >= 0.5 ? 1 : 0; // meia estrela?
    $vazias = 5 - $cheias - $meia;

    $html = "<span style='color:#f5b301; font-size:$tamanho; letter-spacing:1px;'>";
    $html .= str_repeat('<i class="fas fa-star"></i>', $cheias);
    if ($meia) $html .= '<i class="fas fa-star-half-alt"></i>';
    $html .= str_repeat('<i class="far fa-star"></i>', $vazias);
    $html .= '</span>';
    return $html;
}
