<?php
/**
 * =============================================================================
 *  CONEXÃO À BASE DE DADOS — SylviArtes
 * =============================================================================
 *
 *  Estabelece a ligação MySQL através de PDO (PHP Data Objects).
 *  PDO é mais seguro que mysqli porque obriga ao uso de prepared statements,
 *  o que protege contra injeção SQL.
 *
 *  Após este ficheiro ser incluído, a variável $conn fica disponível em
 *  qualquer página que faça `require_once __DIR__ . '/../config/db.php';`
 *
 *  IMPORTANTE: as credenciais aqui são as de DESENVOLVIMENTO local (XAMPP).
 *  Em produção devem ir para um ficheiro fora da pasta pública.
 * =============================================================================
 */

require_once __DIR__ . '/env.php';

// --- Credenciais da base de dados ---
$host   = getenv('DB_HOST') ?: 'localhost';   // servidor MySQL (XAMPP corre em localhost)
$dbname = getenv('DB_NAME') ?: 'sylviartes';  // nome da BD criada no phpMyAdmin
$user   = getenv('DB_USER') ?: 'root';        // utilizador por defeito do MySQL no XAMPP
$dbPass = getenv('DB_PASS');
$pass   = $dbPass !== false ? $dbPass : ''; // password (vazia por defeito no XAMPP)

try {
    // Cria a ligação PDO usando UTF-8 multibyte para suportar acentos e emojis
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );

    // ATTR_ERRMODE: lança Exceptions em caso de erro SQL (em vez de silenciar)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ATTR_DEFAULT_FETCH_MODE: por defeito, devolve arrays associativos
    // (acesso por nome de coluna, ex: $row['nome'] em vez de $row[0])
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // EMULATE_PREPARES = false: usa prepared statements REAIS do MySQL
    // (mais seguros e com tipos corretos — int/string distintos)
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Se a ligação falhar, mostra mensagem amigável e termina o script
    die("Erro na ligação à base de dados: " . $e->getMessage());
}
