<?php
require_once __DIR__.'/../config/bootstrap.php';
require_admin();

$pdo = db();

/* =========================================================
   CONFIGURAÇÃO DA ATA
   ========================================================= */
$alunosPorPagina = 32;
$numeroAta = 1;

/* =========================================================
   BUSCAR TODOS OS ALUNOS
   A ordenação é fixa por data de cadastro + ID para que a
   numeração da ata permaneça previsível.
   ========================================================= */
$sql = "
    SELECT
        u.id,
        u.nome,
        u.email,
        u.data_nascimento,
        u.status,
        u.created_at,
        GROUP_CONCAT(
            DISTINCT CONCAT(
                c.titulo,
                ' | ',
                c.categoria,
                ' | ',
                e.status,
                ' | ',
                DATE_FORMAT(e.enrolled_at, '%d/%m/%Y'),
                ' | R$ ',
                REPLACE(REPLACE(FORMAT(c.preco, 2, 'pt_BR'), '.', ''), ',', '.')
            )
            ORDER BY e.enrolled_at DESC
            SEPARATOR '||'
        ) AS cursos
    FROM users u
    LEFT JOIN enrollments e ON e.user_id = u.id
    LEFT JOIN courses c ON c.id = e.course_id
    WHERE u.role='student'
    GROUP BY
        u.id,
        u.nome,
        u.email,
        u.data_nascimento,
        u.status,
        u.created_at
    ORDER BY u.created_at ASC, u.id ASC
";

$rows = $pdo->query($sql)->fetchAll();

function ataDate(?string $date): string
{
    if (!$date) {
        return 'Não informado';
    }

    $time = strtotime($date);
    return $time ? date('d/m/Y', $time) : $date;
}

function ataCourses(?string $value): array
{
    if (!$value) {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode('||', $value))));
}

$pages = array_chunk($rows, $alunosPorPagina);
$totalPaginas = max(1, count($pages));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ata de alunos - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <style>
        .ata-toolbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 20px;
            background: rgba(2, 11, 32, .96);
            border-bottom: 1px solid rgba(255,255,255,.1);
            backdrop-filter: blur(12px);
        }
        .ata-toolbar-title strong { display:block; font-size:15px; }
        .ata-toolbar-title span { display:block; color:#9eacc1; font-size:12px; }
        .ata-toolbar-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .ata-page {
            width: 297mm;
            min-height: 210mm;
            margin: 24px auto;
            padding: 10mm 10mm 9mm;
            background: #fff;
            color: #101828;
            box-shadow: 0 20px 60px rgba(0,0,0,.22);
            page-break-after: always;
            box-sizing: border-box;
        }
        .ata-page:last-child { page-break-after: auto; }
        .ata-header {
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:20px;
            padding-bottom:10px;
            border-bottom:2px solid #101828;
        }
        .ata-header h1 { margin:0 0 3px; font-size:20px; }
        .ata-header p { margin:0; font-size:10px; color:#475467; }
        .ata-number { text-align:right; font-size:11px; }
        .ata-number strong { display:block; font-size:14px; }
        .ata-meta {
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:7px;
            margin:9px 0;
        }
        .ata-meta-box { border:1px solid #d0d5dd; padding:6px 8px; border-radius:5px; }
        .ata-meta-box span { display:block; font-size:7px; color:#667085; text-transform:uppercase; letter-spacing:.05em; }
        .ata-meta-box strong { display:block; margin-top:2px; font-size:10px; }
        .ata-table { width:100%; border-collapse:collapse; table-layout:fixed; }
        .ata-table th,
        .ata-table td { border:1px solid #bfc5ce; padding:4px 5px; vertical-align:top; }
        .ata-table th { background:#eef2f6; font-size:7px; text-transform:uppercase; letter-spacing:.04em; }
        .ata-table td { font-size:7.5px; line-height:1.25; }
        .ata-table .reg { width:24mm; font-weight:800; text-align:center; }
        .ata-table .aluno { width:51mm; }
        .ata-table .cadastro { width:42mm; }
        .ata-table .curso { width:103mm; }
        .ata-table .situacao { width:30mm; }
        .ata-student-name { font-weight:800; font-size:8px; }
        .ata-muted { color:#667085; }
        .ata-course { margin-bottom:3px; padding-bottom:3px; border-bottom:1px dotted #d0d5dd; }
        .ata-course:last-child { margin-bottom:0; padding-bottom:0; border-bottom:0; }
        .ata-footer { display:flex; justify-content:space-between; margin-top:8px; padding-top:6px; border-top:1px solid #d0d5dd; font-size:8px; color:#475467; }
        .ata-empty { padding:30px; text-align:center; }
        @media screen and (max-width: 1100px) {
            .ata-page { width: calc(100% - 24px); min-height: auto; overflow:auto; }
        }
        @media print {
            @page { size: A4 landscape; margin: 0; }
            body { background:#fff !important; margin:0 !important; }
            .ata-toolbar { display:none !important; }
            .ata-page { width:297mm; height:210mm; min-height:210mm; margin:0; padding:9mm 9mm 7mm; box-shadow:none; }
        }
    </style>
</head>
<body>

<div class="ata-toolbar">
    <div class="ata-toolbar-title">
        <strong>Ata de alunos</strong>
        <span><?= count($rows) ?> aluno<?= count($rows) === 1 ? '' : 's' ?> • <?= $totalPaginas ?> página<?= $totalPaginas === 1 ? '' : 's' ?> • 32 alunos por página</span>
    </div>

    <div class="ata-toolbar-actions">
        <a class="mini-btn" href="<?= e(url('admin/alunos.php')) ?>">← Voltar</a>
        <button class="mini-btn primary" type="button" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    </div>
</div>

<?php if (!$rows): ?>
    <section class="ata-page">
        <div class="ata-empty">Nenhum aluno cadastrado para gerar a ata.</div>
    </section>
<?php else: ?>

    <?php foreach ($pages as $pageIndex => $pageRows): ?>
        <?php $pagina = $pageIndex + 1; ?>

        <section class="ata-page">

            <header class="ata-header">
                <div>
                    <h1><?= e(APP_NAME) ?> — ATA DE ALUNOS</h1>
                    <p>Registro administrativo de alunos e respectivas matrículas.</p>
                </div>

                <div class="ata-number">
                    <span>ATA</span>
                    <strong><?= str_pad((string)$numeroAta, 2, '0', STR_PAD_LEFT) ?></strong>
                    <span>PÁGINA <?= str_pad((string)$pagina, 2, '0', STR_PAD_LEFT) ?> / <?= str_pad((string)$totalPaginas, 2, '0', STR_PAD_LEFT) ?></span>
                </div>
            </header>

            <div class="ata-meta">
                <div class="ata-meta-box">
                    <span>Total de alunos</span>
                    <strong><?= count($rows) ?></strong>
                </div>
                <div class="ata-meta-box">
                    <span>Alunos nesta página</span>
                    <strong><?= count($pageRows) ?> / <?= $alunosPorPagina ?></strong>
                </div>
                <div class="ata-meta-box">
                    <span>Primeiro registro</span>
                    <strong>01-<?= str_pad((string)$pagina, 2, '0', STR_PAD_LEFT) ?>/01</strong>
                </div>
                <div class="ata-meta-box">
                    <span>Último registro</span>
                    <strong>01-<?= str_pad((string)$pagina, 2, '0', STR_PAD_LEFT) ?>/<?= str_pad((string)count($pageRows), 2, '0', STR_PAD_LEFT) ?></strong>
                </div>
            </div>

            <table class="ata-table">
                <thead>
                    <tr>
                        <th class="reg">Registro</th>
                        <th class="aluno">Aluno / e-mail</th>
                        <th class="cadastro">Cadastro</th>
                        <th class="curso">Curso / matrícula</th>
                        <th class="situacao">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pageRows as $position => $student): ?>
                        <?php
                            $posicao = $position + 1;
                            $registro = sprintf(
                                '%02d-%02d/%02d',
                                $numeroAta,
                                $pagina,
                                $posicao
                            );
                            $courses = ataCourses($student['cursos']);
                        ?>
                        <tr>
                            <td class="reg">
                                <?= e($registro) ?>
                            </td>

                            <td class="aluno">
                                <div class="ata-student-name"><?= e($student['nome']) ?></div>
                                <div><?= e($student['email']) ?></div>
                                <div class="ata-muted">Nascimento: <?= e(ataDate($student['data_nascimento'])) ?></div>
                            </td>

                            <td class="cadastro">
                                <strong><?= e(ataDate($student['created_at'])) ?></strong><br>
                                ID: <?= (int)$student['id'] ?>
                            </td>

                            <td class="curso">
                                <?php if ($courses): ?>
                                    <?php foreach ($courses as $course): ?>
                                        <?php $parts = array_map('trim', explode(' | ', $course)); ?>
                                        <div class="ata-course">
                                            <strong><?= e($parts[0] ?? 'Curso') ?></strong><br>
                                            <?= e($parts[1] ?? 'Categoria não informada') ?><br>
                                            <span class="ata-muted">
                                                Matrícula: <?= e($parts[2] ?? 'pendente') ?> ·
                                                <?= e($parts[3] ?? 'sem data') ?> ·
                                                <?= e($parts[4] ?? 'Gratuito') ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="ata-muted">Sem curso matriculado.</span>
                                <?php endif; ?>
                            </td>

                            <td class="situacao">
                                <strong><?= $student['status'] === 'active' ? 'ATIVO' : 'BLOQUEADO' ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <footer class="ata-footer">
                <span>Registro: 01-<?= str_pad((string)$pagina, 2, '0', STR_PAD_LEFT) ?>/01 até 01-<?= str_pad((string)$pagina, 2, '0', STR_PAD_LEFT) ?>/<?= str_pad((string)count($pageRows), 2, '0', STR_PAD_LEFT) ?></span>
                <span>Folha <?= $pagina ?> de <?= $totalPaginas ?></span>
            </footer>

        </section>
    <?php endforeach; ?>

<?php endif; ?>

</body>
</html>
