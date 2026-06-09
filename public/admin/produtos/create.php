<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';
$mensagem = '';
$tipo_msg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validate();
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria_id = (int)($_POST['categoria'] ?? 0);
    $visivel = isset($_POST['visivel']) ? 1 : 0;

    if ($nome === '' || $categoria_id === 0) {
        $mensagem = "Preenche o nome e escolhe uma categoria.";
        $tipo_msg = "erro";
    } else {
        // === Upload de IMAGENS (suporta múltiplas) ===
        // O form usa name="fotos[]" multiple, por isso $_FILES['fotos'] vem como array
        $imagensCarregadas = [];   // lista de nomes de ficheiro guardados em public/imagens/produtos/
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        // Caminho: a partir de public/admin/produtos/, subir 2 níveis até public/, depois entrar em imagens/produtos/
        $pasta_produtos = __DIR__ . '/../../imagens/produtos';

        if (isset($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
            if (!is_dir($pasta_produtos)) {
                mkdir($pasta_produtos, 0755, true);
            }

            $totalFotos = count($_FILES['fotos']['name']);
            for ($i = 0; $i < $totalFotos; $i++) {
                if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;

                $ext = strtolower(pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $permitidas, true)) continue;

                $imageInfo = @getimagesize($_FILES['fotos']['tmp_name'][$i]);
                if ($imageInfo === false) continue; // Not a valid image

                $nome_foto = uniqid('prod_', true) . '.' . $ext;
                $caminho = $pasta_produtos . '/' . $nome_foto;

                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $caminho)) {
                    $imagensCarregadas[] = $nome_foto;
                }
            }
        }
        // Compatibilidade retroativa: ainda aceita campo "foto" único
        elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $permitidas, true)) {
                if (!is_dir($pasta_produtos)) mkdir($pasta_produtos, 0755, true);
                $imageInfo = @getimagesize($_FILES['foto']['tmp_name']);
                if ($imageInfo === false) {
                    $mensagem = "O ficheiro enviado não é uma imagem válida.";
                    $tipo_msg = "erro";
                } else {
                    $nome_foto = uniqid('prod_', true) . '.' . $ext;
                    $caminho = $pasta_produtos . '/' . $nome_foto;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminho)) {
                        $imagensCarregadas[] = $nome_foto;
                    }
                }
            }
        }

        if (empty($imagensCarregadas)) {
            $mensagem = "É obrigatório adicionar pelo menos uma foto do produto.";
            $tipo_msg = "erro";
        } else {
            try {
                $conn->beginTransaction();

                // Site é portfólio (orçamento sob medida), por isso o produto
                // não tem preço fixo nem stock: preco_base=0 e stock=NULL.
                // A visibilidade é controlada apenas pelo "Mostrar no Catálogo".
                $stmt = $conn->prepare("
                    INSERT INTO produto
                    (nome, descricao, preco_base, categoria_id, visivel_catalogo, stock)
                    VALUES
                    (:nome, :descricao, 0, :categoria_id, :visivel, NULL)
                ");

                $ok = $stmt->execute([
                    ':nome' => $nome,
                    ':descricao' => $descricao,
                    ':categoria_id' => $categoria_id,
                    ':visivel' => $visivel
                ]);

                $produto_id = (int)$conn->lastInsertId();

                if ($ok && $produto_id > 0) {
                    // Insere cada imagem com a ordem incremental (1, 2, 3...)
                    // A primeira (ordem=1) é a principal mostrada no catálogo.
                    $stmtImg = $conn->prepare("
                        INSERT INTO produto_imagem (produto_id, imagem, ordem)
                        VALUES (:produto_id, :imagem, :ordem)
                    ");
                    foreach ($imagensCarregadas as $idx => $nomeFich) {
                        $stmtImg->execute([
                            ':produto_id' => $produto_id,
                            ':imagem' => $nomeFich,
                            ':ordem' => $idx + 1,
                        ]);
                    }
                }

                $conn->commit();

                header("Location: index.php");
                exit;
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Admin: erro ao criar produto: " . $e->getMessage());
                $mensagem = "Ocorreu um erro ao guardar o produto. Tente novamente.";
                $tipo_msg = "erro";
            }
        }
    }
}

// categorias
$stmtCats = $conn->query("SELECT * FROM categoria ORDER BY nome");
$categorias = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Produto - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../../imagens/logo_sylviartes.png">
    <link rel="stylesheet" href="../admin_style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body  { font-family: 'Poppins', sans-serif; }
        h1    { font-family: 'Playfair Display', serif; font-size: 26px; color: #2d3436; font-weight: 600; }
        .msg-box    { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .msg-sucesso{ background: rgba(40,167,69,0.15);  color: #28a745; }
        .msg-erro   { background: rgba(220,53,69,0.15);  color: #dc3545; }
        .form-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%; padding: 12px; border: 2px solid #eee;
            border-radius: 10px; font-size: 14px; transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #d66d7f; outline: none;
            box-shadow: 0 0 0 4px rgba(214,109,127,0.1);
        }

        /* ============================================================
           PICKER DE FOTOS - substitui o input nativo (incompatível com
           o seletor de thumbnails do Windows 11 para múltiplos ficheiros)
           ============================================================ */

        /* Grelha de pré-visualizações */
        .fotos-preview {
            display: flex; flex-wrap: wrap; gap: 12px;
            margin-bottom: 14px;
        }

        /* Cada miniatura */
        .foto-item {
            width: 110px; border-radius: 10px; overflow: hidden;
            border: 2px solid #e2dde0; background: #fff;
            transition: border-color 0.15s;
        }
        /* A 1ª foto é a "principal" - borda rosa */
        .foto-item.foto-principal { border-color: #d66d7f; }

        /* Imagem da miniatura */
        .foto-thumb { height: 90px; position: relative; overflow: hidden; }
        .foto-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* Badge "★ Principal" em cima da 1ª foto */
        .foto-badge {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: rgba(214,109,127,0.85); color: #fff;
            font-size: 10px; font-weight: 700; text-align: center;
            padding: 3px 0; letter-spacing: 0.5px;
        }

        /* Rodapé da miniatura: nome + botão apagar */
        .foto-rodape {
            display: flex; align-items: center; justify-content: space-between;
            padding: 5px 7px; font-size: 11px; color: #666;
        }
        .foto-rodape span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 75px; }
        .btn-foto-del {
            background: none; border: none; color: #e74c3c;
            cursor: pointer; padding: 0 2px; font-size: 12px; flex-shrink: 0;
        }
        .btn-foto-del:hover { color: #c0392b; }

        /* Botão "Adicionar foto" - estilo dashed */
        .btn-add-foto {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px;
            border: 2px dashed #d66d7f; border-radius: 10px;
            background: #fff8fa; color: #d66d7f;
            font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600;
            cursor: pointer; transition: background 0.15s, border-color 0.15s;
        }
        .btn-add-foto:hover { background: #fff0f3; border-color: #bf5b6d; }

        /* Texto de ajuda abaixo do picker */
        .fotos-hint { font-size: 12px; color: #aaa; margin-top: 8px; }

        /* Aviso de validação (pelo menos 1 foto) */
        .fotos-erro { color: #dc3545; font-size: 13px; margin-top: 8px; display: none; }
        .fotos-erro.visivel { display: block; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px;">
        <h1><i class="fas fa-plus-circle"></i> Adicionar Novo Produto</h1>
        <a href="index.php" class="btn-action" style="background:#6c757d;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if($mensagem): ?>
        <div class="msg-box <?php echo ($tipo_msg === 'sucesso') ? 'msg-sucesso' : 'msg-erro'; ?>">
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nome do Produto:</label>
                    <input type="text" name="nome" required placeholder="Ex: Toalha Bordada">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Categoria:</label>
                    <select name="categoria" required>
                        <option value="">-- Selecionar --</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>">
                                <?php echo htmlspecialchars($c['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Picker de fotos - cada clique em "Adicionar foto" abre 1 seletor
                     e cria um <input type="file" name="fotos[]"> separado no DOM.
                     O PHP recebe todos via $_FILES['fotos'] (array) como antes. -->
                <div class="form-group full-width">
                    <label><i class="fas fa-images"></i> Fotos do Produto</label>

                    <!-- Miniaturas das fotos selecionadas (preenchidas por JS) -->
                    <div id="fotosPreview" class="fotos-preview"></div>

                    <!-- Botão para adicionar mais fotos (1 picker por clique) -->
                    <button type="button" id="btnAddFoto" class="btn-add-foto">
                        <i class="fas fa-plus"></i> Adicionar foto
                    </button>

                    <!-- Container onde os inputs de ficheiro ficam guardados (escondidos) -->
                    <div id="fotosInputs" style="display:none;"></div>

                    <div class="fotos-hint">A 1ª foto é a principal no catálogo. Formatos: jpg, png, gif, webp.</div>
                    <div id="fotosErro" class="fotos-erro">
                        <i class="fas fa-exclamation-circle"></i> Adiciona pelo menos uma foto antes de guardar.
                    </div>
                </div>

                <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="visivel" id="v" checked style="width:auto;">
                    <label for="v" style="margin:0; cursor:pointer;">Mostrar no Catálogo</label>
                </div>

                <div class="full-width form-group">
                    <label><i class="fas fa-align-left"></i> Descrição:</label>
                    <textarea name="descricao" rows="3" placeholder="Detalhes do produto..."></textarea>
                </div>
            </div>
            <button type="submit" class="btn-action" style="margin-top:15px; width:100%;">
                <i class="fas fa-save"></i> Gravar Produto
            </button>
        </form>
    </div>
</div>

<script>
// =============================================================================
// PICKER DE FOTOS - adiciona pré-visualizações uma a uma
// =============================================================================
// Cada clique em "Adicionar foto" cria um <input type="file"> invisível e
// abre o seletor do sistema operativo. Quando o utilizador escolhe UMA imagem,
// aparece a miniatura na grelha. Para adicionar outra, clica de novo.
// Ao submeter, o PHP recebe todos os ficheiros em $_FILES['fotos'] (array).
// =============================================================================

var fotoMap     = {};   // id → elemento <input> correspondente
var fotoCounter = 0;    // contador para gerar IDs únicos por sessão

// Clique em "Adicionar foto"
document.getElementById('btnAddFoto').addEventListener('click', function () {
    // Cria um input invisível para UM ficheiro
    var input = document.createElement('input');
    input.type    = 'file';
    input.name    = 'fotos[]';
    input.accept  = 'image/*';
    input.style.display = 'none';
    document.getElementById('fotosInputs').appendChild(input);

    input.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            var file = this.files[0];
            var id   = ++fotoCounter;
            input.dataset.fotoid = id;
            fotoMap[id] = input;

            // Lê a imagem e cria a miniatura
            var reader = new FileReader();
            reader.onload = function (e) {
                criarMiniatura(id, e.target.result, file.name);
            };
            reader.readAsDataURL(file);

            // Esconde o aviso de "sem fotos"
            document.getElementById('fotosErro').classList.remove('visivel');
        } else {
            // Utilizador cancelou - remove o input sem ficheiro
            input.remove();
        }
    });

    input.click();  // abre o seletor do SO
});

// Cria a miniatura de pré-visualização na grelha
// Usa createElement/textContent em vez de innerHTML para evitar XSS:
// o nome do ficheiro vem do sistema do utilizador e poderia conter HTML/JS malicioso.
function criarMiniatura(id, src, nome) {
    var preview = document.getElementById('fotosPreview');

    // Card da miniatura
    var div = document.createElement('div');
    div.className  = 'foto-item';
    div.dataset.id = id;

    // --- Zona da imagem ---
    var thumbDiv = document.createElement('div');
    thumbDiv.className = 'foto-thumb';

    var img = document.createElement('img');
    img.src = src;   // data: URL gerado pelo FileReader - seguro
    img.alt = '';    // nome aparece no rodapé
    thumbDiv.appendChild(img);

    // --- Rodapé com nome e botão apagar ---
    var rodapeDiv = document.createElement('div');
    rodapeDiv.className = 'foto-rodape';

    var span = document.createElement('span');
    var nomeExibido = nome.length > 14 ? nome.substring(0, 14) + '…' : nome;
    span.textContent = nomeExibido;  // textContent - nunca interpreta HTML
    span.title = nome;

    var btn = document.createElement('button');
    btn.type      = 'button';
    btn.className = 'btn-foto-del';
    btn.title     = 'Remover esta foto';
    // Usa addEventListener (não onclick="...") para evitar injeção via id
    btn.addEventListener('click', (function (fid) {
        return function () { removerFoto(fid); };
    })(id));

    var icon = document.createElement('i');
    icon.className = 'fas fa-trash';
    btn.appendChild(icon);

    rodapeDiv.appendChild(span);
    rodapeDiv.appendChild(btn);

    div.appendChild(thumbDiv);
    div.appendChild(rodapeDiv);

    preview.appendChild(div);
    atualizarPrincipal();  // recalcula qual é a 1ª foto
}

// Remove uma foto da grelha e o seu input correspondente
function removerFoto(id) {
    var item = document.querySelector('.foto-item[data-id="' + id + '"]');
    if (item) item.remove();

    if (fotoMap[id]) {
        fotoMap[id].remove();
        delete fotoMap[id];
    }

    atualizarPrincipal();
}

// Marca a 1ª miniatura com o badge "★ Principal" e borda rosa
function atualizarPrincipal() {
    var items = document.querySelectorAll('.foto-item');

    items.forEach(function (item, index) {
        // Remove badge anterior
        var badge = item.querySelector('.foto-badge');
        if (badge) badge.remove();

        if (index === 0) {
            // Primeira foto = principal
            item.classList.add('foto-principal');
            var span      = document.createElement('span');
            span.className = 'foto-badge';
            span.textContent = '★ Principal';
            item.querySelector('.foto-thumb').appendChild(span);
        } else {
            item.classList.remove('foto-principal');
        }
    });
}

// Validação ao submeter: obriga a ter pelo menos 1 foto
document.querySelector('form').addEventListener('submit', function (e) {
    if (Object.keys(fotoMap).length === 0) {
        e.preventDefault();
        document.getElementById('fotosErro').classList.add('visivel');
        document.getElementById('btnAddFoto').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
</body>
</html>
