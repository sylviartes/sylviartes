<?php
/**
 * =============================================================================
 *  HELPERS PDO — Funções utilitárias para queries
 * =============================================================================
 *
 *  Este ficheiro fornece pequenas funções "wrapper" que simplificam o uso
 *  do PDO e mantêm uma sintaxe parecida com mysqli (usado em algumas partes
 *  antigas do projeto). Assim evita-se ter de duplicar código de prepare()
 *  + execute() em todas as páginas.
 *
 *  Exemplo de uso:
 *      $stmt = query($conn, "SELECT * FROM produto WHERE id = ?", [$id]);
 *      $produto = fetch($stmt);
 * =============================================================================
 */

/**
 * Executa uma query SQL de forma segura, com ou sem parâmetros.
 *
 * @param PDO    $conn    Objeto de ligação PDO (vem do db.php)
 * @param string $sql     Comando SQL com placeholders ? para parâmetros
 * @param array  $params  Valores a passar aos placeholders
 *
 * @return PDOStatement   Pode ser usado em fetch(), fetchAll(), etc.
 */
function query($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        // Usa execute() — mais seguro: PDO faz escape dos parâmetros
        $stmt->execute($params);
    } else {
        // Sem parâmetros — query direta
        $stmt->query($sql);
    }
    return $stmt;
}

/** Devolve apenas a próxima linha do resultado (ou false se não houver). */
function fetch($stmt) {
    return $stmt->fetch();
}

/** Devolve TODAS as linhas do resultado num array. */
function fetchAll($stmt) {
    return $stmt->fetchAll();
}

/** Conta quantas linhas foram afetadas pela última query (UPDATE/DELETE/INSERT). */
function num_rows($stmt) {
    return $stmt->rowCount();
}
