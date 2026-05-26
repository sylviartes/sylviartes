<?php
require_once __DIR__ . '/../../../config/session.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// CORREÇÃO 1: Adicionado mais um "../" para recuar 3 pastas até à raiz do site
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

csrf_validate();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    try {
        $conn->beginTransaction();

        // buscar imagens para apagar ficheiros físicos
        $stmtImgs = $conn->prepare("SELECT imagem FROM produto_imagem WHERE produto_id = :id");
        $stmtImgs->execute([':id' => $id]);
        $imagens = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("DELETE FROM detalhe_pedido WHERE produto_id = :id");
        $stmt->execute([':id' => $id]);

        $stmt = $conn->prepare("DELETE FROM produto_imagem WHERE produto_id = :id");
        $stmt->execute([':id' => $id]);

        $stmt = $conn->prepare("DELETE FROM produto WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $conn->commit();

        // apagar ficheiros físicos
        foreach ($imagens as $img) {
            if (!empty($img['imagem'])) {
                // CORREÇÃO 2: Ajustado também o caminho da pasta das imagens (recua 2 pastas)
                @unlink(__DIR__ . '/../../imagens/produtos/' . $img['imagem']);
            }
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ((int)$e->getCode() === 23000) {
            // ignore FK constraint
        } else {
            die("Erro ao apagar produto: " . $e->getMessage());
        }
    }
}

header("Location: index.php");
exit;
?>