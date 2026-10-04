<?php
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/media.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $pdo = db();
    try {
        if ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Curso inválido.');

            $q = $pdo->prepare('SELECT id, titulo, status FROM courses WHERE id=? LIMIT 1');
            $q->execute([$id]);
            $course = $q->fetch();
            if (!$course) throw new RuntimeException('Curso não encontrado.');

            $newStatus = $course['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare('UPDATE courses SET status=? WHERE id=?')->execute([$newStatus, $id]);

            flash('success', $newStatus === 'active'
                ? 'Curso publicado na página principal.'
                : 'Curso ocultado da página principal. Ele continua disponível no painel para edição e publicação.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Curso inválido.');
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare('SELECT * FROM courses WHERE id=? FOR UPDATE');
                $q->execute([$id]);
                $course = $q->fetch();
                if (!$course) throw new RuntimeException('Curso não encontrado.');
                $q = $pdo->prepare('SELECT id, video_url, material_url FROM lessons WHERE course_id=?');
                $q->execute([$id]);
                $lessons = $q->fetchAll();
                $lessonIds = array_map(fn($r) => (int)$r['id'], $lessons);
                if ($lessonIds) {
                    $marks = implode(',', array_fill(0, count($lessonIds), '?'));
                    $pdo->prepare("DELETE FROM lesson_progress WHERE lesson_id IN ($marks)")->execute($lessonIds);
                }
                $q = $pdo->prepare('SELECT id FROM enrollments WHERE course_id=?');
                $q->execute([$id]);
                $enrollmentIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
                if ($enrollmentIds) {
                    $marks = implode(',', array_fill(0, count($enrollmentIds), '?'));
                    $pdo->prepare("DELETE FROM payments WHERE enrollment_id IN ($marks)")->execute($enrollmentIds);
                    $pdo->prepare("DELETE FROM certificates WHERE enrollment_id IN ($marks)")->execute($enrollmentIds);
                }
                $pdo->prepare('DELETE FROM certificates WHERE course_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM lessons WHERE course_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM enrollments WHERE course_id=?')->execute([$id]);
                $deleted = $pdo->prepare('DELETE FROM courses WHERE id=?');
                $deleted->execute([$id]);
                if ($deleted->rowCount() !== 1) throw new RuntimeException('O curso não pôde ser excluído.');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            if (!empty($course['imagem'])) delete_media_file($course['imagem']);
            if (!empty($course['apresentacao_imagem'])) delete_media_file($course['apresentacao_imagem']);
            foreach ($lessons as $lesson) { delete_media_file($lesson['video_url'] ?? null); delete_media_file($lesson['material_url'] ?? null); }
            flash('success', 'Curso excluído definitivamente do catálogo.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/cursos.php');
}

$rows = db()->query('SELECT * FROM courses ORDER BY id DESC')->fetchAll();
$notice = flash();
?>
<?php layout_header('Gerenciar cursos'); ?>
<?php if ($notice): ?><div class="admin-notice <?=e($notice['type'])?>"><strong><?= $notice['type']==='success' ? '✓ Concluído' : 'Atenção' ?></strong><span><?=e($notice['message'])?></span></div><?php endif; ?>
<div class="course-admin-nav" style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px;">
<a class="mini-btn" href="<?=e(url('admin/cursos.php'))?>">📚 Gerenciar cursos</a>
<a class="mini-btn" href="<?=e(url('admin/curso-form.php'))?>">＋ Criar curso</a>
<a class="mini-btn" href="<?=e(url('admin/aulas.php'))?>">▶ Aulas</a>
</div>
<div class="dash-head courses-admin-head">
  <div><span class="eyebrow">ADMINISTRAÇÃO</span><h1>Gerenciar cursos</h1><p>Consulte, publique, oculte, edite e exclua os cursos do catálogo.</p></div>
  <a class="btn" href="<?=e(url('admin/curso-form.php'))?>">＋ Criar novo curso</a>
</div>
<div class="course-admin-toolbar">
  <div class="course-admin-search"><span>⌕</span><input id="courseSearch" type="search" placeholder="Pesquisar curso ou categoria..."></div>
  <div class="course-admin-stats"><div><strong><?=count($rows)?></strong><span>cursos</span></div><div><strong><?=count(array_filter($rows,fn($r)=>$r['status']==='active'))?></strong><span>publicados</span></div><div><strong><?=count(array_filter($rows,fn($r)=>$r['status']==='inactive'))?></strong><span>rascunhos</span></div></div>
</div>
<div class="table-wrap course-admin-table-wrap"><table id="coursesTable"><thead><tr><th>Curso</th><th>Valor</th><th>Publicação</th><th>Aulas</th><th>Ações</th></tr></thead><tbody>
<?php foreach($rows as $r): $q=db()->prepare('SELECT COUNT(*) FROM lessons WHERE course_id=?');$q->execute([$r['id']]);$n=$q->fetchColumn(); ?>
<tr data-course-row data-search="<?=e(strtolower($r['titulo'].' '.$r['categoria']))?>">
<td><div class="course-table-title"><div class="course-thumb"><img src="<?=e(url($r['imagem'] ?: 'assets/img/curso1.svg'))?>" alt=""></div><div><strong><?=e($r['titulo'])?></strong><br><small><?=e($r['categoria'])?></small></div></div></td>
<td><?=((float)$r['preco']>0)?money((float)$r['preco']):'Gratuito'?></td>
<td><span class="badge <?=$r['status']?>"><?=$r['status']==='active'?'Publicado':'Rascunho'?></span></td>
<td><?=$n?></td>
<td><div class="course-actions">
<a class="mini-btn" href="<?=e(url('admin/curso-form.php?edit='.$r['id']))?>">✎ Editar</a>
<a class="mini-btn" href="<?=e(url('admin/aulas.php?course='.$r['id']))?>">▶ Conteúdo</a>
<form method="post" onsubmit="return confirm('<?= $r['status']==='active' ? 'Ocultar este curso da página principal?' : 'Publicar este curso na página principal?' ?>')"><?=csrf_field()?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="mini-btn" type="submit"><?=$r['status']==='active'?'🙈 Ocultar':'👁 Publicar'?></button></form>
<form method="post" onsubmit="return confirm('Excluir definitivamente este curso, suas aulas, matrículas, certificados e vídeos? Esta ação não pode ser desfeita.')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="mini-btn danger" type="submit">🗑 Excluir</button></form>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<script>(function(){const input=document.getElementById('courseSearch');if(!input)return;input.addEventListener('input',()=>{const q=input.value.trim().toLowerCase();document.querySelectorAll('[data-course-row]').forEach(r=>{r.style.display=!q||r.dataset.search.includes(q)?'':'none';});});})();</script>
<?php layout_footer(); ?>
