<?php
require_once __DIR__.'/../config/bootstrap.php';
require_admin();

$pdo = db();

/* =========================================================
   FILTROS
   ========================================================= */
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

/* =========================================================
   AÇÕES DO ADMINISTRADOR
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        $st = $pdo->prepare('SELECT id,nome,role FROM users WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $student = $st->fetch();

        if (!$student || $student['role'] !== 'student') {
            throw new RuntimeException('Aluno não encontrado.');
        }

        if ($action === 'delete') {
            $pdo->beginTransaction();

            try {
                $qEnroll = $pdo->prepare('SELECT id FROM enrollments WHERE user_id=?');
                $qEnroll->execute([$id]);
                $enrollmentIds = array_map('intval', $qEnroll->fetchAll(PDO::FETCH_COLUMN));

                $pdo->prepare('DELETE FROM lesson_progress WHERE user_id=?')->execute([$id]);

                if ($enrollmentIds) {
                    $marks = implode(',', array_fill(0, count($enrollmentIds), '?'));
                    $pdo->prepare("DELETE FROM payments WHERE enrollment_id IN ($marks)")->execute($enrollmentIds);
                    $pdo->prepare("DELETE FROM certificates WHERE enrollment_id IN ($marks)")->execute($enrollmentIds);
                }

                $pdo->prepare('DELETE FROM enrollments WHERE user_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM remember_tokens WHERE user_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM users WHERE id=? AND role="student"')->execute([$id]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            flash('success', 'Cadastro do aluno excluído definitivamente.');

        } elseif ($action === 'status') {
            $status = ($_POST['status'] ?? '') === 'blocked' ? 'blocked' : 'active';

            $pdo->prepare(
                'UPDATE users SET status=? WHERE id=? AND role="student"'
            )->execute([$status, $id]);

            flash(
                'success',
                $status === 'active'
                    ? 'Aluno ativado com sucesso.'
                    : 'Aluno bloqueado com sucesso.'
            );
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect('admin/alunos.php');
}

/* =========================================================
   CONSULTA COMPLETA DOS ALUNOS E CURSOS
   ========================================================= */
$sql = "
    SELECT
        u.id,
        u.nome,
        u.email,
        u.data_nascimento,
        u.status,
        u.created_at,
        u.updated_at,
        COUNT(DISTINCT e.id) AS matriculas,
        GROUP_CONCAT(
            DISTINCT CONCAT(
                c.titulo,
                ' | ',
                c.categoria,
                ' | ',
                e.status,
                ' | ',
                DATE_FORMAT(e.enrolled_at, '%d/%m/%Y')
            )
            ORDER BY e.enrolled_at DESC
            SEPARATOR '||'
        ) AS cursos_detalhes,
        GROUP_CONCAT(
            DISTINCT c.titulo
            ORDER BY e.enrolled_at DESC
            SEPARATOR '||'
        ) AS cursos,
        GROUP_CONCAT(
            DISTINCT CONCAT(
                c.titulo,
                ' | R$ ',
                REPLACE(REPLACE(FORMAT(c.preco, 2, 'pt_BR'), '.', ''), ',', '.')
            )
            ORDER BY e.enrolled_at DESC
            SEPARATOR '||'
        ) AS cursos_valores
    FROM users u
    LEFT JOIN enrollments e ON e.user_id = u.id
    LEFT JOIN courses c ON c.id = e.course_id
    WHERE u.role='student'
      AND (u.nome LIKE :q_nome OR u.email LIKE :q_email)
";

if ($statusFilter === 'active' || $statusFilter === 'blocked') {
    $sql .= ' AND u.status = :status ';
}

$sql .= "
    GROUP BY
        u.id,
        u.nome,
        u.email,
        u.data_nascimento,
        u.status,
        u.created_at,
        u.updated_at
    ORDER BY u.created_at DESC
";

$st = $pdo->prepare($sql);
$busca = '%' . $q . '%';
$st->bindValue(':q_nome', $busca);
$st->bindValue(':q_email', $busca);

if ($statusFilter === 'active' || $statusFilter === 'blocked') {
    $st->bindValue(':status', $statusFilter);
}

$st->execute();
$rows = $st->fetchAll();

/* =========================================================
   FUNÇÕES VISUAIS
   ========================================================= */
function adminFormatDate(?string $date): string
{
    if (!$date) {
        return 'Não informado';
    }

    $time = strtotime($date);
    return $time ? date('d/m/Y', $time) : e($date);
}

function adminFormatDateTime(?string $date): string
{
    if (!$date) {
        return 'Não informado';
    }

    $time = strtotime($date);
    return $time ? date('d/m/Y H:i', $time) : e($date);
}

function splitAdminList(?string $value): array
{
    if (!$value) {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode('||', $value))));
}
?>

<?php layout_header('Alunos'); ?>

<div class="dash-head admin-students-head">
    <div>
        <span class="eyebrow">ADMINISTRAÇÃO</span>
        <h1>Alunos</h1>
        <p>Visualize o cadastro completo, cursos, matrículas e situação de cada aluno.</p>
    </div>

    <div class="admin-students-actions">
        <a class="btn" href="<?= e(url('admin/ata.php')) ?>" target="_blank">
            📄 Gerar ata
        </a>
    </div>
</div>

<div class="student-admin-toolbar">
    <form class="filter student-search-form" method="get">
        <input
            name="q"
            value="<?= e($q) ?>"
            placeholder="Buscar por nome ou e-mail"
        >

        <select name="status">
            <option value="">Todos os status</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
            <option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : '' ?>>Bloqueados</option>
        </select>

        <button class="btn" type="submit">Pesquisar</button>

        <?php if ($q !== '' || $statusFilter !== ''): ?>
            <a class="mini-btn" href="<?= e(url('admin/alunos.php')) ?>">Limpar</a>
        <?php endif; ?>
    </form>

    <div class="student-admin-summary">
        <strong><?= count($rows) ?></strong>
        <span>aluno<?= count($rows) === 1 ? '' : 's' ?> encontrado<?= count($rows) === 1 ? '' : 's' ?></span>
    </div>
</div>

<div class="students-admin-list">

    <?php foreach ($rows as $index => $r): ?>
        <?php
            $courses = splitAdminList($r['cursos']);
            $courseDetails = splitAdminList($r['cursos_detalhes']);
            $courseValues = splitAdminList($r['cursos_valores']);
        ?>

        <article class="student-admin-card">

            <div class="student-admin-card-head">
                <div class="student-admin-identity">
                    <div class="student-admin-avatar">
                        <?= e(strtoupper(substr($r['nome'], 0, 2))) ?>
                    </div>

                    <div>
                        <span class="student-admin-label">ALUNO #<?= (int)$r['id'] ?></span>
                        <h2><?= e($r['nome']) ?></h2>
                        <p><?= e($r['email']) ?></p>
                    </div>
                </div>

                <span class="badge <?= e($r['status']) ?>">
                    <?= $r['status'] === 'active' ? 'Ativo' : 'Bloqueado' ?>
                </span>
            </div>

            <div class="student-admin-grid">

                <div class="student-info-box">
                    <span>CADASTRO</span>
                    <strong><?= adminFormatDateTime($r['created_at']) ?></strong>
                </div>

                <div class="student-info-box">
                    <span>DATA DE NASCIMENTO</span>
                    <strong><?= adminFormatDate($r['data_nascimento']) ?></strong>
                </div>

                <div class="student-info-box">
                    <span>ÚLTIMA ATUALIZAÇÃO</span>
                    <strong><?= adminFormatDateTime($r['updated_at']) ?></strong>
                </div>

                <div class="student-info-box">
                    <span>MATRÍCULAS</span>
                    <strong><?= (int)$r['matriculas'] ?></strong>
                </div>

            </div>

            <div class="student-courses-box">
                <div class="student-section-title">
                    <span>🎓</span>
                    <div>
                        <strong>Cursos e matrículas</strong>
                        <small>Informações registradas para este aluno</small>
                    </div>
                </div>

                <?php if ($courseDetails): ?>
                    <div class="student-course-list">
                        <?php foreach ($courseDetails as $courseDetail): ?>
                            <?php $parts = array_map('trim', explode(' | ', $courseDetail)); ?>
                            <div class="student-course-row">
                                <div>
                                    <strong><?= e($parts[0] ?? 'Curso') ?></strong>
                                    <small>
                                        Categoria: <?= e($parts[1] ?? 'Não informada') ?>
                                    </small>
                                </div>
                                <div class="student-course-meta">
                                    <span><?= e(ucfirst($parts[2] ?? 'pendente')) ?></span>
                                    <small>Matriculado em <?= e($parts[3] ?? 'Não informado') ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="student-no-course">
                        Este aluno ainda não possui curso matriculado.
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($courseValues): ?>
                <div class="student-course-values">
                    <strong>Valores dos cursos:</strong>
                    <?php foreach ($courseValues as $courseValue): ?>
                        <span><?= e($courseValue) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="student-admin-card-footer">

                <div class="student-record-note">
                    Registro administrativo: <strong><?= (int)$r['id'] ?></strong>
                </div>

                <div class="action-stack">

                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="action" value="status">

                        <select name="status" class="mini-select">
                            <option value="active" <?= $r['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                            <option value="blocked" <?= $r['status'] === 'blocked' ? 'selected' : '' ?>>Bloqueado</option>
                        </select>

                        <button class="mini-btn" type="submit">
                            Atualizar status
                        </button>
                    </form>

                    <form
                        method="post"
                        class="inline-form"
                        onsubmit="return confirm('Excluir definitivamente o cadastro deste aluno, matrículas, progresso e certificados?')"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="action" value="delete">

                        <button class="mini-btn danger" type="submit">
                            Excluir cadastro
                        </button>
                    </form>

                </div>
            </div>

        </article>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
        <div class="empty">
            Nenhum aluno encontrado.
        </div>
    <?php endif; ?>

</div>

<?php layout_footer(); ?>
