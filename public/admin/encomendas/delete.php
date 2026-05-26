<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apagar_pedido'])) {
    csrf_validate();
    $id_apagar = (int)($_POST['id_apagar'] ?? 0);

    if ($id_apagar > 0) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("DELETE FROM mensagem_pedido WHERE pedido_id = :pedido_id");
            $stmt->execute([':pedido_id' => $id_apagar]);

            $stmt = $conn->prepare("DELETE FROM pagamento WHERE pedido_id = :pedido_id");
            $stmt->execute([':pedido_id' => $id_apagar]);

            $stmt = $conn->prepare("DELETE FROM detalhe_pedido WHERE pedido_id = :pedido_id");
            $stmt->execute([':pedido_id' => $id_apagar]);

            $stmt = $conn->prepare("DELETE FROM log_alteracoes_pedido WHERE pedido_id = :pedido_id");
            $stmt->execute([':pedido_id' => $id_apagar]);

            $stmt = $conn->prepare("DELETE FROM pedido WHERE id = :id");
            $stmt->execute([':id' => $id_apagar]);

            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Admin: erro ao apagar encomenda: " . $e->getMessage());
            die('<p style="font-family:sans-serif;text-align:center;margin-top:80px;color:#555;">Ocorreu um erro ao apagar a encomenda. <a href="index.php">Voltar</a></p>');
        }
    }
}

header("Location: index.php");
exit;
?>

