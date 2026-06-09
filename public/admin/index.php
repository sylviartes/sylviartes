<?php
/**
 * =============================================================================
 *  ADMIN - Dashboard Principal
 * =============================================================================
 *
 *  Painel inicial da área administrativa, adaptado ao modelo de portfólio +
 *  pedido de orçamento. Estrutura:
 *
 *    1. Saudação personalizada com data atual em PT
 *    2. Card destaque "Pedidos por Tratar" (call-to-action diário)
 *    3. 4 KPIs (Faturação, Encomendas, Clientes, Avaliação Média)
 *    4. 2 Gráficos (Vendas 30d, Pedidos por Estado)
 *    5. 2 Listas (Próximos Prazos, Atividade Recente)
 *    6. Gráfico de Top Categorias
 * =============================================================================
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/auth.php';

// =============================================================================
// ESTADO "POR TRATAR" - pedidos novos que precisam de orçamento
// =============================================================================
// Novos pedidos são criados com estado 'em_analise'.
// O dashboard mostra quantos estão neste estado para o admin tratar.
$estadoOrcamento = 'em_analise';

// =============================================================================
// FILTRO DE PERÍODO - Aplicado em ambos os KPIs de faturação
// =============================================================================
// Separamos o filtro de DATA do filtro de ESTADO para poder reutilizar
// a condição de data em duas queries distintas: "Faturado" e "Pipeline".
$periodo = $_GET['periodo'] ?? 'mes';

// Condição de data isolada (sem filtro de estado - combinada abaixo)
$filtroPeriodo = '1=1'; // 'vida' = sem limite de data
if ($periodo === 'dia')    $filtroPeriodo = "DATE(data) = CURDATE()";
elseif ($periodo === 'semana') $filtroPeriodo = "YEARWEEK(data,1) = YEARWEEK(CURDATE(),1)";
elseif ($periodo === 'mes')    $filtroPeriodo = "YEAR(data)=YEAR(CURDATE()) AND MONTH(data)=MONTH(CURDATE())";
elseif ($periodo === 'ano')    $filtroPeriodo = "YEAR(data) = YEAR(CURDATE())";

// WHERE completo para cada KPI de faturação
// Faturado = pedidos já entregues/concluídos (dinheiro realmente recebido)
$whereFaturado = "estado IN ('concluido','entregue') AND $filtroPeriodo";
// Pipeline = pedidos confirmados mas ainda em produção ou aguardar pagamento
// (dinheiro comprometido que ainda não chegou - importante num negócio de orçamentos)
$wherePipeline = "estado IN ('aguarda_pagamento','em_producao') AND $filtroPeriodo";

$periodoLabel = ['dia'=>'Hoje','semana'=>'Esta Semana','mes'=>'Este Mês','ano'=>'Este Ano','vida'=>'Toda a Vida'][$periodo] ?? 'Este Mês';

// =============================================================================
// QUERIES - KPIs de Faturação
// =============================================================================
// Faturado: soma dos pedidos já concluídos ou entregues no período selecionado
$kpiFaturado = (float)$conn->query(
    "SELECT IFNULL(SUM(valor_total),0) FROM pedido WHERE $whereFaturado"
)->fetchColumn();

// Pipeline: soma dos pedidos em produção ou aguardar pagamento no mesmo período
// Mostra o dinheiro "garantido" que ainda não entrou - evita o dashboard mostrar 0€
// enquanto há trabalho confirmado em andamento.
$kpiPipeline = (float)$conn->query(
    "SELECT IFNULL(SUM(valor_total),0) FROM pedido WHERE $wherePipeline"
)->fetchColumn();
$kpiEncomendasMes = (int)$conn->query("SELECT COUNT(*) FROM pedido WHERE YEAR(data)=YEAR(CURDATE()) AND MONTH(data)=MONTH(CURDATE())")->fetchColumn();
$kpiClientes    = (int)$conn->query("SELECT COUNT(*) FROM utilizador WHERE nivel_acesso='cliente'")->fetchColumn();

$kpiAvaliacaoMedia = 0;
$kpiTotalAvaliacoes = 0;
try {
    $stmt = $conn->query("SELECT AVG(estrelas) AS m, COUNT(*) AS t FROM avaliacao WHERE aprovado = 1");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $kpiAvaliacaoMedia = $r['m'] !== null ? round((float)$r['m'], 1) : 0;
    $kpiTotalAvaliacoes = (int)$r['t'];
} catch (Exception $e) { /* tabela pode não existir */ }

// =============================================================================
// PEDIDOS POR TRATAR (destaque principal)
// =============================================================================
// TIMESTAMPDIFF calcula os minutos decorridos DENTRO do MySQL (NOW() e p.data
// usam o mesmo relógio), evitando desalinhamentos de fuso entre PHP e a BD.
$stmtPorTratar = $conn->prepare("
    SELECT p.id, p.data, p.observacoes, u.nome, u.email,
           TIMESTAMPDIFF(MINUTE, p.data, NOW()) AS minutos_atras
    FROM pedido p
    JOIN utilizador u ON u.id = p.utilizador_id
    WHERE p.estado = ?
    ORDER BY p.data ASC
    LIMIT 5
");
$stmtPorTratar->execute([$estadoOrcamento]);
$pedidosPorTratar = $stmtPorTratar->fetchAll(PDO::FETCH_ASSOC);

$totalPorTratar = (int)$conn->query("SELECT COUNT(*) FROM pedido WHERE estado = " . $conn->quote($estadoOrcamento))->fetchColumn();

// =============================================================================
// PRÓXIMOS PRAZOS DE ENTREGA (em produção, próximos 7 dias)
// =============================================================================
$stmtPrazos = $conn->query("
    SELECT p.id, p.prazo_entrega_desejado, p.estado, u.nome,
           DATEDIFF(p.prazo_entrega_desejado, CURDATE()) AS dias_restantes
    FROM pedido p
    JOIN utilizador u ON u.id = p.utilizador_id
    WHERE p.estado IN ('em_producao','aguarda_pagamento','em_analise')
      AND p.prazo_entrega_desejado IS NOT NULL
      AND p.prazo_entrega_desejado >= CURDATE()
    ORDER BY p.prazo_entrega_desejado ASC
    LIMIT 5
");
$proximosPrazos = $stmtPrazos->fetchAll(PDO::FETCH_ASSOC);

// =============================================================================
// ATIVIDADE RECENTE (últimos eventos)
// =============================================================================
$stmtAtividade = $conn->query("
    (SELECT 'pedido' AS tipo, p.id AS pedido_id, p.data, u.nome, p.valor_total
     FROM pedido p JOIN utilizador u ON u.id = p.utilizador_id
     ORDER BY p.data DESC LIMIT 5)
    UNION ALL
    (SELECT 'pagamento' AS tipo, pg.pedido_id AS pedido_id, pg.data, u.nome, pg.valor
     FROM pagamento pg
     JOIN pedido p ON p.id = pg.pedido_id
     JOIN utilizador u ON u.id = p.utilizador_id
     WHERE pg.estado_pagamento = 'validado'
     ORDER BY pg.data DESC LIMIT 5)
    ORDER BY data DESC
    LIMIT 8
");
$atividadeRecente = $stmtAtividade->fetchAll(PDO::FETCH_ASSOC);

// =============================================================================
// GRÁFICO 1 - Faturação & Pipeline dos últimos 30 dias
// =============================================================================
// Preenche dois arrays em paralelo:
//   $valores30d  → receita real (concluido + entregue) - linha rosa
//   $pipeline30d → receita a caminho (em_producao + aguarda_pagamento) - linha cinza
// Uma query por dia × 2: simples e fácil de explicar na defesa de PAP.
$labels30d   = [];
$valores30d  = [];
$pipeline30d = [];

for ($i = 29; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));

    // Faturado neste dia
    $stmt = $conn->prepare(
        "SELECT IFNULL(SUM(valor_total),0) FROM pedido
         WHERE DATE(data)=? AND estado IN ('concluido','entregue')"
    );
    $stmt->execute([$dia]);
    $valores30d[] = (float)$stmt->fetchColumn();

    // Pipeline neste dia
    $stmt = $conn->prepare(
        "SELECT IFNULL(SUM(valor_total),0) FROM pedido
         WHERE DATE(data)=? AND estado IN ('em_producao','aguarda_pagamento')"
    );
    $stmt->execute([$dia]);
    $pipeline30d[] = (float)$stmt->fetchColumn();

    $labels30d[] = date('d/m', strtotime($dia));
}

// =============================================================================
// GRÁFICO 2 - Distribuição por estado (donut)
// =============================================================================
$stmtEstados = $conn->query("SELECT estado, COUNT(*) AS qtd FROM pedido GROUP BY estado");
$rotulosEstado = [
    'aguarda_orcamento' => 'Aguarda Orçamento',
    'em_analise' => 'Em Análise',
    'aguarda_pagamento' => 'Aguarda Pagamento',
    'em_producao' => 'Em Produção',
    'concluido' => 'Concluído',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado',
];
$estadosLabels = [];
$estadosValores = [];
foreach ($stmtEstados->fetchAll(PDO::FETCH_ASSOC) as $e) {
    $estadosLabels[]  = $rotulosEstado[$e['estado']] ?? $e['estado'];
    $estadosValores[] = (int)$e['qtd'];
}

// =============================================================================
// GRÁFICO 3 - Top 5 categorias mais pedidas (barras horizontais)
// =============================================================================
$stmtCat = $conn->query("
    SELECT c.nome, COUNT(*) AS qtd
    FROM detalhe_pedido dp
    JOIN produto p ON p.id = dp.produto_id
    JOIN categoria c ON c.id = p.categoria_id
    GROUP BY c.id, c.nome
    ORDER BY qtd DESC
    LIMIT 5
");
$catLabels = [];
$catValores = [];
foreach ($stmtCat->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $catLabels[]  = $c['nome'];
    $catValores[] = (int)$c['qtd'];
}

// =============================================================================
// SAUDAÇÃO + DATA EM PT
// =============================================================================
$hora = (int)date('H');
$saudacao = ($hora < 12) ? 'Bom dia' : (($hora < 19) ? 'Boa tarde' : 'Boa noite');
$primeiroNome = explode(' ', $_SESSION['admin_nome'] ?? 'Admin')[0];

$diasSemana = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
$meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$dataPT = $diasSemana[(int)date('w')] . ', ' . (int)date('j') . ' de ' . $meses[(int)date('n') - 1] . ' de ' . date('Y');

/**
 * Converte um nº de minutos decorridos num texto amigável em português.
 * Nunca mostra valores negativos (se a data for "futura" por algum acerto
 * de relógio, mostra "agora mesmo").
 */
function tempo_decorrido(int $minutos): string {
    if ($minutos < 1)  return 'agora mesmo';
    if ($minutos < 60) return "há {$minutos} min";
    $horas = intdiv($minutos, 60);
    if ($horas < 24)   return "há {$horas} " . ($horas === 1 ? 'hora' : 'horas');
    $dias = intdiv($horas, 24);
    return "há {$dias} " . ($dias === 1 ? 'dia' : 'dias');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SylviArtes Admin</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../imagens/logo_sylviartes.png">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ============== Dashboard refeita - paleta consistente ============== */
        .dash-greeting {
            margin-bottom: 28px;
        }
        .dash-greeting h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 600;
            color: #2d3436;
            margin: 0 0 6px;
        }
        .dash-greeting .subtitulo {
            color: #888;
            font-size: 15px;
            text-transform: capitalize;
        }

        /* HERO de pedidos por tratar */
        .hero-tratar {
            background: linear-gradient(135deg, #fff8fa 0%, #fdf0f4 100%);
            border: 1px solid #f0c8d2;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .hero-tratar::before {
            content: '';
            position: absolute; top: -40%; right: -10%;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(214,109,127,0.10) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-numero {
            font-family: 'Playfair Display', serif;
            font-size: 80px;
            line-height: 1;
            font-weight: 700;
            color: #d66d7f;
            margin: 0 0 8px;
        }
        .hero-tratar h2 {
            font-family: 'Playfair Display', serif;
            margin: 0 0 6px;
            color: #2d3436;
        }
        .hero-tratar p { color: #666; margin: 0 0 16px; }
        .hero-cta {
            display: inline-block; padding: 12px 26px;
            background: linear-gradient(135deg,#d66d7f,#bf5b6d); color: #fff;
            text-decoration: none; border-radius: 999px; font-weight: 600;
            transition: 0.2s;
        }
        .hero-cta:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(201,95,122,0.25); color: #fff; }

        .hero-lista { background: #fff; border-radius: 14px; padding: 18px; max-height: 280px; overflow-y: auto; }
        .hero-lista h4 { color: #d66d7f; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px; }
        .hero-pedido {
            padding: 10px 0; border-bottom: 1px solid #f3e7eb;
        }
        .hero-pedido:last-child { border-bottom: none; }
        .hero-pedido a { color: #2d3436; text-decoration: none; font-weight: 600; display: block; }
        .hero-pedido a:hover { color: #d66d7f; }
        .hero-pedido .meta { color: #999; font-size: 12px; margin-top: 2px; }
        .hero-pedido .desc { color: #666; font-size: 13px; margin-top: 4px; line-height: 1.4; max-height: 36px; overflow: hidden; }

        /* KPIs limpos com paleta consistente */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px; margin-bottom: 28px;
        }
        .kpi-card {
            background: #fff; border-radius: 14px; padding: 22px;
            border: 1px solid #f0e3e7; box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: 0.2s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(214,109,127,0.10); }
        .kpi-icone {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 10px;
            background: #fff8fa; color: #d66d7f; font-size: 18px;
            margin-bottom: 12px;
        }
        .kpi-label { color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .kpi-valor { color: #2d3436; font-size: 28px; font-weight: 700; }
        .kpi-extra { color: #888; font-size: 12px; margin-top: 4px; }

        /* Filtro período */
        .filtro-periodo { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .filtro-periodo a {
            padding: 7px 14px; border-radius: 999px; background: #fff;
            border: 1px solid #e8e8e8; color: #666; font-size: 13px;
            text-decoration: none; transition: 0.15s;
        }
        .filtro-periodo a.ativo { background: #d66d7f; color: #fff; border-color: #d66d7f; }
        .filtro-periodo a:hover { border-color: #d66d7f; }

        /* Grelha de gráficos + listas */
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .row-2col-asym { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 28px; }
        .panel {
            background: #fff; border-radius: 14px; padding: 24px;
            border: 1px solid #f0e3e7; box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .panel h3 {
            margin: 0 0 16px; font-family: 'Playfair Display', serif;
            font-weight: 600; color: #2d3436; font-size: 18px;
            padding-bottom: 12px; border-bottom: 1px solid #f3e7eb;
        }

        /* Listas */
        .lista-item { padding: 12px 0; border-bottom: 1px solid #f3e7eb; }
        .lista-item:last-child { border-bottom: none; }
        .lista-item a { text-decoration: none; color: inherit; display: block; }
        .lista-item a:hover .titulo { color: #d66d7f; }
        .lista-item .titulo { font-weight: 600; color: #2d3436; }
        .lista-item .meta { color: #888; font-size: 13px; margin-top: 2px; }

        .urgencia-badge {
            display: inline-block; padding: 3px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 600;
        }
        .urg-vermelho { background: #fee2e2; color: #991b1b; }
        .urg-laranja  { background: #fed7aa; color: #9a3412; }
        .urg-verde    { background: #d1fae5; color: #065f46; }

        .vazio {
            text-align: center; padding: 30px 10px; color: #999; font-style: italic;
        }

        @media (max-width: 900px) {
            .hero-tratar { grid-template-columns: 1fr; }
            .row-2col, .row-2col-asym { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-content">

    <!-- ============================================================ -->
    <!-- 1. SAUDAÇÃO PERSONALIZADA                                     -->
    <!-- ============================================================ -->
    <div class="dash-greeting">
        <h1><?= htmlspecialchars($saudacao) ?>, <?= htmlspecialchars($primeiroNome) ?> 👋</h1>
        <div class="subtitulo"><?= htmlspecialchars($dataPT) ?></div>
    </div>

    <!-- ============================================================ -->
    <!-- 2. HERO - PEDIDOS POR TRATAR                                  -->
    <!-- ============================================================ -->
    <div class="hero-tratar">
        <div>
            <div style="color:#d66d7f; font-size:13px; text-transform:uppercase; letter-spacing:2px; margin-bottom:4px;">
                <i class="fas fa-bell"></i> Por Tratar
            </div>
            <div class="hero-numero"><?= $totalPorTratar ?></div>
            <h2><?= $totalPorTratar === 1 ? 'pedido aguarda orçamento' : 'pedidos aguardam orçamento' ?></h2>
            <p>São pedidos novos que ainda não foram orçados. Telefone à cliente, ajuste o valor e envie o link de pagamento.</p>
            <a href="encomendas/index.php?status=<?= htmlspecialchars($estadoOrcamento) ?>" class="hero-cta">
                Ver todos os pendentes <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="hero-lista">
            <h4>5 mais antigos</h4>
            <?php if (empty($pedidosPorTratar)): ?>
                <div class="vazio">✓ Sem pedidos pendentes - tudo em dia!</div>
            <?php else: ?>
                <?php foreach ($pedidosPorTratar as $pt): ?>
                    <div class="hero-pedido">
                        <a href="encomendas/view.php?id=<?= (int)$pt['id'] ?>">
                            #<?= (int)$pt['id'] ?> · <?= htmlspecialchars($pt['nome']) ?>
                        </a>
                        <div class="meta">
                            <?= date('d/m/Y H:i', strtotime($pt['data'])) ?>
                            · <?= tempo_decorrido((int)$pt['minutos_atras']) ?>
                        </div>
                        <?php if (!empty($pt['observacoes'])): ?>
                            <div class="desc">"<?= htmlspecialchars(mb_strimwidth($pt['observacoes'], 0, 90, '…')) ?>"</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 3. KPIs + filtro de período                                   -->
    <!-- ============================================================ -->
    <div class="filtro-periodo">
        <?php foreach (['dia'=>'Hoje','semana'=>'Semana','mes'=>'Mês','ano'=>'Ano','vida'=>'Toda a Vida'] as $key => $lbl): ?>
            <a href="?periodo=<?= $key ?>" class="<?= $periodo === $key ? 'ativo' : '' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>

    <div class="kpi-grid">

        <!-- KPI: Faturado - dinheiro realmente recebido (concluídos + entregues) -->
        <div class="kpi-card">
            <div class="kpi-icone" style="background:#ecfdf5; color:#059669;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="kpi-label">Faturado · <?= htmlspecialchars($periodoLabel) ?></div>
            <div class="kpi-valor" style="color:#059669;">
                <?= number_format($kpiFaturado, 2, ',', '.') ?> €
            </div>
            <div class="kpi-extra">Concluídos &amp; entregues</div>
        </div>

        <!-- KPI: Pipeline - dinheiro confirmado mas ainda a caminho -->
        <!-- Evita que o dashboard mostre 0€ enquanto há trabalho ativo em produção -->
        <div class="kpi-card">
            <div class="kpi-icone" style="background:#f3f4f6; color:#6b7280;">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="kpi-label">Pipeline · <?= htmlspecialchars($periodoLabel) ?></div>
            <div class="kpi-valor" style="color:#6b7280;">
                <?= number_format($kpiPipeline, 2, ',', '.') ?> €
            </div>
            <div class="kpi-extra">Em produção &amp; aguarda pagamento</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icone"><i class="fas fa-box"></i></div>
            <div class="kpi-label">Encomendas este Mês</div>
            <div class="kpi-valor"><?= $kpiEncomendasMes ?></div>
            <div class="kpi-extra">Total recebidas em <?= $meses[(int)date('n')-1] ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icone"><i class="fas fa-users"></i></div>
            <div class="kpi-label">Clientes Registadas</div>
            <div class="kpi-valor"><?= $kpiClientes ?></div>
            <div class="kpi-extra">Com conta no site</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icone"><i class="fas fa-star"></i></div>
            <div class="kpi-label">Avaliação Média</div>
            <div class="kpi-valor">
                <?= $kpiTotalAvaliacoes > 0 ? $kpiAvaliacaoMedia . ' ★' : '-' ?>
            </div>
            <div class="kpi-extra"><?= $kpiTotalAvaliacoes ?> <?= $kpiTotalAvaliacoes === 1 ? 'avaliação aprovada' : 'avaliações aprovadas' ?></div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 4. GRÁFICOS - Vendas 30d + Estados                            -->
    <!-- ============================================================ -->
    <div class="row-2col-asym">
        <div class="panel">
            <h3><i class="fas fa-chart-area"></i> Faturação &amp; Pipeline - 30 dias</h3>
            <div style="height: 260px;"><canvas id="grafVendas"></canvas></div>
        </div>
        <div class="panel">
            <h3><i class="fas fa-chart-pie"></i> Pedidos por Estado</h3>
            <div style="height: 260px;"><canvas id="grafEstados"></canvas></div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 5. LISTAS - Próximos Prazos + Atividade Recente               -->
    <!-- ============================================================ -->
    <div class="row-2col">
        <div class="panel">
            <h3><i class="fas fa-calendar-day"></i> Próximos Prazos de Entrega</h3>
            <?php if (empty($proximosPrazos)): ?>
                <div class="vazio">Sem prazos próximos no horizonte.</div>
            <?php else: ?>
                <?php foreach ($proximosPrazos as $pr): ?>
                    <?php
                    $dias = (int)$pr['dias_restantes'];
                    $urgencia = $dias <= 3 ? 'urg-vermelho' : ($dias <= 7 ? 'urg-laranja' : 'urg-verde');
                    $textoUrg = $dias === 0 ? 'Hoje' : ($dias === 1 ? 'Amanhã' : "$dias dias");
                    ?>
                    <div class="lista-item">
                        <a href="encomendas/view.php?id=<?= (int)$pr['id'] ?>">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div class="titulo">Pedido #<?= (int)$pr['id'] ?> · <?= htmlspecialchars($pr['nome']) ?></div>
                                    <div class="meta">
                                        Entregar até <?= date('d/m/Y', strtotime($pr['prazo_entrega_desejado'])) ?>
                                        · <?= $rotulosEstado[$pr['estado']] ?? $pr['estado'] ?>
                                    </div>
                                </div>
                                <span class="urgencia-badge <?= $urgencia ?>"><?= $textoUrg ?></span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3><i class="fas fa-history"></i> Atividade Recente</h3>
            <?php if (empty($atividadeRecente)): ?>
                <div class="vazio">Sem atividade recente.</div>
            <?php else: ?>
                <?php foreach ($atividadeRecente as $a): ?>
                    <div class="lista-item">
                        <a href="encomendas/view.php?id=<?= (int)$a['pedido_id'] ?>">
                            <?php if ($a['tipo'] === 'pagamento'): ?>
                                <div class="titulo">
                                    <i class="fas fa-check-circle" style="color:#22c55e;"></i>
                                    <?= htmlspecialchars($a['nome']) ?> pagou
                                    <?php
                                    // Correção: o UNION devolve 'valor' para linhas de pagamento,
                                    // não 'valor_total' - usar coalescência para evitar NULL.
                                    $valorAtividade = $a['valor_total'] ?? $a['valor'] ?? 0;
                                    echo number_format((float)$valorAtividade, 2, ',', '.');
                                    ?> €
                                </div>
                            <?php else: ?>
                                <div class="titulo">
                                    <i class="fas fa-plus-circle" style="color:#d66d7f;"></i>
                                    Novo pedido de <?= htmlspecialchars($a['nome']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="meta">
                                Pedido #<?= (int)$a['pedido_id'] ?>
                                · <?= date('d/m/Y H:i', strtotime($a['data'])) ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 6. TOP CATEGORIAS                                             -->
    <!-- ============================================================ -->
    <?php if (!empty($catLabels)): ?>
    <div class="panel">
        <h3><i class="fas fa-tags"></i> Categorias Mais Pedidas</h3>
        <div style="height: 240px;"><canvas id="grafCategorias"></canvas></div>
    </div>
    <?php endif; ?>

</div>

<script>
// =============================================================================
// Gráfico 1: Faturação & Pipeline - últimos 30 dias (linha dupla)
// =============================================================================
// Dataset rosa (faturado): receita real confirmada (concluido + entregue)
// Dataset cinza tracejado (pipeline): receita a caminho (em_producao + aguarda_pagamento)
// Ambos os arrays foram preenchidos no PHP acima com uma query por dia.
new Chart(document.getElementById('grafVendas'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels30d) ?>,
        datasets: [
            {
                label: 'Faturado (€)',
                data: <?= json_encode($valores30d) ?>,
                borderColor: '#d66d7f',
                backgroundColor: 'rgba(214, 109, 127, 0.10)',
                borderWidth: 2.5,
                tension: 0.35,
                fill: true,
                pointRadius: 2,
                pointHoverRadius: 6,
            },
            {
                label: 'Pipeline (€)',
                data: <?= json_encode($pipeline30d) ?>,
                borderColor: '#9ca3af',
                backgroundColor: 'transparent',
                borderWidth: 1.5,
                borderDash: [5, 4],   // linha tracejada = "ainda não confirmado"
                tension: 0.35,
                fill: false,
                pointRadius: 2,
                pointHoverRadius: 5,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            // Legenda visível agora que há dois datasets distintos
            legend: {
                display: true,
                position: 'top',
                labels: { font: { size: 12 }, boxWidth: 16 }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => v + ' €' } },
            x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } }
        }
    }
});

// === Gráfico 2: Estados ===
<?php if (!empty($estadosLabels)): ?>
new Chart(document.getElementById('grafEstados'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($estadosLabels) ?>,
        datasets: [{
            data: <?= json_encode($estadosValores) ?>,
            backgroundColor: ['#fce7f3','#fef3c7','#fed7aa','#dbeafe','#d1fae5','#cffafe','#fee2e2'],
            borderColor: '#fff',
            borderWidth: 2,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
    }
});
<?php endif; ?>

// === Gráfico 3: Top Categorias ===
<?php if (!empty($catLabels)): ?>
new Chart(document.getElementById('grafCategorias'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($catLabels) ?>,
        datasets: [{
            label: 'Pedidos',
            data: <?= json_encode($catValores) ?>,
            backgroundColor: 'rgba(214, 109, 127, 0.7)',
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>
