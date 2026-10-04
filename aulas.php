<?php
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/media.php';
require_admin();

$cid = (int)($_GET['course'] ?? $_POST['course'] ?? 0);
$st = db()->prepare('SELECT * FROM courses WHERE id=?');
$st->execute([$cid]);
$course = $st->fetch();
if (!$course) exit('Curso inválido.');

function lesson_media_kind(?array $lesson): string {
    if (!$lesson) return 'none';
    if (!empty($lesson['material_url'])) return 'pdf';
    if (!empty($lesson['video_url'])) {
        return str_starts_with($lesson['video_url'], 'uploads/') ? 'video_upload' : 'youtube';
    }
    return 'none';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $pdo = db();
    try {
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $q = $pdo->prepare('SELECT video_url, material_url FROM lessons WHERE id=? AND course_id=?');
            $q->execute([$id, $cid]);
            $old = $q->fetch();
            $pdo->prepare('DELETE FROM lessons WHERE id=? AND course_id=?')->execute([$id, $cid]);
            if ($old) {
                delete_media_file($old['video_url'] ?? null);
                delete_media_file($old['material_url'] ?? null);
            }
            flash('success', 'Aula excluída.');
        } elseif ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            if ($titulo === '') throw new RuntimeException('Informe o título da aula.');
            $descricao = trim($_POST['descricao'] ?? '');
            $conteudo = trim($_POST['conteudo'] ?? '');
            $ordem = max(1, (int)($_POST['ordem'] ?? 1));
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $tipo = $_POST['tipo_conteudo'] ?? 'none';

            $q = $pdo->prepare('SELECT video_url, material_url FROM lessons WHERE id=? AND course_id=?');
            $q->execute([$id, $cid]);
            $old = $q->fetch() ?: [];
            $video = $old['video_url'] ?? null;
            $material = $old['material_url'] ?? null;

            if ($tipo === 'pdf') {
                $uploadedPdf = upload_media('pdf_arquivo', 'pdfs', [
                    'pdf' => ['application/pdf', 'application/x-pdf']
                ], 100 * 1024 * 1024);
                if ($uploadedPdf) {
                    if ($video) delete_media_file($video);
                    if ($material && $material !== $uploadedPdf) delete_media_file($material);
                    $video = null;
                    $material = $uploadedPdf;
                } elseif (!$material) {
                    throw new RuntimeException('Selecione um arquivo PDF para esta aula.');
                }
            } elseif ($tipo === 'video') {
                // Só substitui o conteúdo anterior depois que o novo vídeo/link estiver confirmado.
                $newVideo = null;
                $uploaded = upload_media('video_arquivo', 'videos', [
                    'mp4' => ['video/mp4', 'application/mp4'],
                    'webm' => ['video/webm'],
                    'ogg' => ['video/ogg', 'application/ogg'],
                    'mov' => ['video/quicktime'],
                    'm4v' => ['video/x-m4v', 'video/mp4']
                ], 500 * 1024 * 1024);

                $youtube = trim($_POST['youtube_url'] ?? '');
                if ($uploaded) {
                    $newVideo = $uploaded;
                } elseif ($youtube !== '') {
                    if (!preg_match('~^https?://(www\.)?(youtube\.com|youtu\.be)/~i', $youtube)) {
                        throw new RuntimeException('Informe uma URL válida do YouTube.');
                    }
                    $newVideo = $youtube;
                } elseif (!$video) {
                    throw new RuntimeException('Envie um vídeo ou informe o link do YouTube.');
                }

                if ($newVideo !== null) {
                    if ($video && $video !== $newVideo) delete_media_file($video);
                    if ($material) delete_media_file($material);
                    $video = $newVideo;
                    $material = null;
                }
            } else {
                delete_media_file($video);
                delete_media_file($material);
                $video = null;
                $material = null;
            }

            if ($id) {
                $pdo->prepare('UPDATE lessons SET titulo=?,descricao=?,conteudo=?,video_url=?,material_url=?,ordem=?,status=? WHERE id=? AND course_id=?')
                    ->execute([$titulo, $descricao, $conteudo, $video, $material, $ordem, $status, $id, $cid]);
            } else {
                $pdo->prepare('INSERT INTO lessons(course_id,titulo,descricao,conteudo,video_url,material_url,ordem,status) VALUES(?,?,?,?,?,?,?,?)')
                    ->execute([$cid, $titulo, $descricao, $conteudo, $video, $material, $ordem, $status]);
            }
            flash('success', 'Aula salva com sucesso.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/aulas.php?course='.$cid);
}

$rows = db()->prepare('SELECT * FROM lessons WHERE course_id=? ORDER BY ordem,id');
$rows->execute([$cid]);
$lessons = $rows->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $q = db()->prepare('SELECT * FROM lessons WHERE id=? AND course_id=?');
    $q->execute([(int)$_GET['edit'], $cid]);
    $edit = $q->fetch();
}
$editKind = lesson_media_kind($edit);
$editYoutube = $editKind === 'youtube' ? ($edit['video_url'] ?? '') : '';
?>
<?php layout_header('Conteúdo do curso'); ?>
<div class="dash-head">
    <div><span class="eyebrow">CONTEÚDO DO CURSO</span><h1><?=e($course['titulo'])?></h1><p>Crie aulas com PDF, vídeo enviado pelo administrador ou vídeo do YouTube.</p></div>
    <a class="btn ghost" href="<?=e(url('admin/cursos.php'))?>">← Cursos</a>
</div>

<div class="admin-form panel lesson-admin-panel">
    <div class="lesson-admin-intro">
        <div><h2><?=$edit?'Editar aula':'Nova aula'?></h2><p>O aluno verá o conteúdo dentro da interface de aulas da plataforma.</p></div>
        <div class="lesson-media-badges"><span>📄 PDF</span><span>🎬 Vídeo</span><span>▶ YouTube</span></div>
    </div>
    <form method="post" enctype="multipart/form-data">
        <?=csrf_field()?><input type="hidden" name="course" value="<?=$cid?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit['id']??0?>">
        <label>Título da aula</label><input name="titulo" required value="<?=e($edit['titulo']??'')?>" placeholder="Ex.: Segurança operacional">
        <label>Descrição curta</label><input name="descricao" value="<?=e($edit['descricao']??'')?>" placeholder="Ex.: Princípios básicos de segurança">
        <label>Conteúdo textual da aula <span class="muted-label">opcional</span></label>
        <textarea name="conteudo" rows="8" placeholder="Você também pode escrever uma explicação complementar para o aluno..." ><?=e($edit['conteudo']??'')?></textarea>

        <div class="lesson-media-selector">
            <div class="media-selector-title"><strong>Material principal da aula</strong><small>Escolha uma opção. O aluno verá esse material no painel principal.</small></div>
            <div class="media-options">
                <label class="media-option"><input type="radio" name="tipo_conteudo" value="none" <?=$editKind==='none'?'checked':''?>><span>📝<b>Somente texto</b><small>Usar apenas o conteúdo escrito.</small></span></label>
                <label class="media-option"><input type="radio" name="tipo_conteudo" value="pdf" <?=$editKind==='pdf'?'checked':''?>><span>📄<b>PDF</b><small>Enviar apostila/material em PDF.</small></span></label>
                <label class="media-option"><input type="radio" name="tipo_conteudo" value="video" <?=$editKind!=='pdf' && $editKind!=='none'?'checked':''?>><span>🎬<b>Vídeo</b><small>Enviar MP4 ou usar YouTube.</small></span></label>
            </div>
        </div>

        <div class="lesson-media-fields" data-media-fields>
            <div class="media-field media-field-pdf">
                <label>Upload do PDF</label>
                <input type="file" name="pdf_arquivo" accept="application/pdf">
                <small class="form-help">PDF até 100 MB. Recomendado: material em A4 e texto pesquisável.</small>
                <?php if($editKind==='pdf'): ?><div class="current-media">📄 PDF atual: <a href="<?=e(url($edit['material_url']))?>" target="_blank" rel="noopener">Abrir PDF</a>. Enviar outro PDF substitui o atual.</div><?php endif; ?>
            </div>
            <div class="media-field media-field-video">
                <label>Upload de vídeo</label>
                <input type="file" name="video_arquivo" accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-m4v">
                <small class="form-help">Formatos: MP4, WebM, OGG, MOV e M4V. Limite da aplicação: 500 MB, sujeito ao limite do PHP do XAMPP.</small>
                <small class="form-help"><strong>Se o envio falhar:</strong> confira <code>upload_max_filesize</code> e <code>post_max_size</code> em <code>C:\\xampp\\php\\php.ini</code> e reinicie o Apache.</small>
                <label>Ou vídeo do YouTube</label>
                <input name="youtube_url" value="<?=e($editYoutube)?>" placeholder="https://www.youtube.com/watch?v=...">
                <small class="form-help">Cole o link normal do YouTube. O sistema converte automaticamente para o player.</small>
                <?php if($editKind==='video_upload'): ?><div class="current-media">🎬 Vídeo enviado atualmente: <a href="<?=e(url($edit['video_url']))?>" target="_blank" rel="noopener">Abrir vídeo</a>. Enviar outro vídeo substitui o atual.</div><?php elseif($editKind==='youtube'): ?><div class="current-media">▶ YouTube atual: <?=e($edit['video_url'])?></div><?php endif; ?>
            </div>
        </div>

        <div class="two-col"><div><label>Ordem</label><input type="number" min="1" name="ordem" value="<?=e($edit['ordem']??count($lessons)+1)?>"></div><div><label>Status</label><select name="status"><option value="active" <?=($edit['status']??'active')==='active'?'selected':''?>>Publicada</option><option value="inactive" <?=($edit['status']??'')==='inactive'?'selected':''?>>Rascunho</option></select></div></div>
        <button class="btn" type="submit">💾 <?=$edit?'Atualizar aula':'Publicar aula'?></button>
    </form>
</div>

<div class="lesson-list">
<?php foreach($lessons as $l): $kind=lesson_media_kind($l); ?>
    <div class="lesson-item">
        <span class="lesson-number"><?=e($l['ordem'])?></span>
        <div><strong><?=e($l['titulo'])?></strong><small><?=e($l['descricao'])?></small><small><?= $kind==='pdf'?'📄 PDF':($kind==='video_upload'?'🎬 Vídeo enviado':($kind==='youtube'?'▶ YouTube':'📝 Texto')) ?> · <?=$l['status']==='active'?'Publicada':'Rascunho'?></small></div>
        <a class="mini-btn" href="?course=<?=$cid?>&edit=<?=$l['id']?>">Editar</a>
        <form method="post" onsubmit="return confirm('Excluir esta aula e o material enviado?')"><?=csrf_field()?><input type="hidden" name="course" value="<?=$cid?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$l['id']?>"><button class="mini-btn danger">Excluir</button></form>
    </div>
<?php endforeach; ?>
</div>
<script>
(function(){
  const radios=document.querySelectorAll('input[name="tipo_conteudo"]');
  const pdf=document.querySelector('.media-field-pdf');
  const video=document.querySelector('.media-field-video');
  function sync(){const v=document.querySelector('input[name="tipo_conteudo"]:checked')?.value; if(pdf)pdf.style.display=v==='pdf'?'block':'none'; if(video)video.style.display=v==='video'?'block':'none';}
  radios.forEach(r=>r.addEventListener('change',sync)); sync();
})();
</script>
<?php layout_footer(); ?>
