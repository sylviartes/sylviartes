<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

csrf_validate();

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM categoria WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Se correu tudo bem, volta para a lista
        header("Location: index.php");
        exit;
        
    } catch (PDOException $e) {
        // O código 23000 significa que há dados dependentes (produtos associados)
        if ($e->getCode() == 23000) {
            die("
                <div style='font-family: sans-serif; text-align: center; margin-top: 100px; color: #333;'>
                    <h2 style='color: #dc3545;'>⚠️ Não é possível apagar esta categoria!</h2>
                    <p>Ainda existem <b>produtos</b> que pertencem a esta categoria.</p>
                    <p>Para poderes apagar esta categoria, tens de ir primeiro à Gestão de Produtos e apagá-los (ou mudá-los para outra categoria).</p>
                    <br><br>
                    <a href='index.php' style='background: #d66d7f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 25px; font-weight: bold;'>Voltar às Categorias</a>
                </div>
            ");
        } else {
            // Se for outro erro qualquer da base de dados
            die("Erro crítico na base de dados: " . htmlspecialchars($e->getMessage()));
        }
    }
} else {
    header("Location: index.php");
    exit;
}
?>