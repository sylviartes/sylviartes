<?php
/**
 * =============================================================================
 *  HELPERS DE PEDIDOS
 * =============================================================================
 *
 *  Funções auxiliares relacionadas com os pedidos/encomendas.
 * =============================================================================
 */

/**
 * Devolve o número do pedido do ponto de vista do CLIENTE.
 *
 * Na base de dados cada pedido tem um `id` global (auto-incremento partilhado
 * por TODOS os clientes). Para o cliente isso não faz sentido: o primeiro
 * pedido dele deve aparecer como #1, e não como #27 (que é a 27.ª encomenda
 * do site inteiro).
 *
 * Esta função conta quantos pedidos desse cliente foram criados até este
 * (inclusive) e devolve essa posição. É estável: o número mostrado para um
 * dado pedido nunca muda, porque só depende dos pedidos criados até ele.
 *
 * NOTA: este número serve apenas para MOSTRAR ao cliente. Os links e as
 * operações continuam a usar o `id` global (único), tal como o painel de admin.
 *
 * @param PDO      $conn       Ligação à base de dados
 * @param int      $pedidoId   Id global do pedido
 * @param int|null $clienteId  (opcional) dono do pedido; se não vier, é lido do pedido
 * @return int  Posição do pedido para o cliente (1, 2, 3, ...) ou 0 se não existir
 */
function numero_pedido_cliente(PDO $conn, int $pedidoId, ?int $clienteId = null): int
{
    // Se não soubermos o dono, vamos buscá-lo ao próprio pedido
    if ($clienteId === null) {
        $stmt = $conn->prepare("SELECT utilizador_id FROM pedido WHERE id = ?");
        $stmt->execute([$pedidoId]);
        $clienteId = (int)$stmt->fetchColumn();
        if ($clienteId === 0) {
            return 0; // pedido não existe
        }
    }

    // Conta os pedidos deste cliente com id <= ao deste pedido.
    // Como o id é sempre crescente, isto dá a ordem de criação (1 = o mais antigo).
    $stmt = $conn->prepare("SELECT COUNT(*) FROM pedido WHERE utilizador_id = ? AND id <= ?");
    $stmt->execute([$clienteId, $pedidoId]);
    return (int)$stmt->fetchColumn();
}
