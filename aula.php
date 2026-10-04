<?php
require_once __DIR__.'/../config/bootstrap.php';

function video_embed_url(string $url): string {
    if (preg_match('~youtube\.com/watch\?v=([^&]+)~i', $url, $m)) return 'https://www.youtube.com/embed/'.rawurlencode($m[1]);
    if (preg_match('~youtu\.be/([^?&/]+)~i', $url, $m)) return 'https://www.youtube.com/embed/'.rawurlencode($m[1]);
    if (preg_match('~youtube\.com/shorts/([^?&/]+)~i', $url, $m)) return 'https://www.youtube.com/embed/'.rawurlencode($m[1]);
    return $url;
}

$u = require_login();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT l.*, c.titulo AS course_title, c.id AS course_id, c.imagem AS course_image
    FROM lessons l
    JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND l.status = "active"');
$st->execute([$id]);
$lesson = $st->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Aula não encontrada.');
}

$en = enrollment((int)$u['id'], (int)$lesson['course_id']);
if (!$en || !in_array($en['status'], ['active', 'completed'], true)) {
    exit('Você não possui acesso a esta aula.');
}

$lessonsSt = $pdo->prepare('SELECT l.id, l.titulo, l.descricao, l.ordem,
    COALESCE(lp.completed, 0) AS completed
    FROM lessons l
    LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
    WHERE l.course_id = ? AND l.status = "active"
    ORDER BY l.ordem, l.id');
$lessonsSt->execute([$u['id'], $lesson['course_id']]);
$lessons = $lessonsSt->fetchAll();

$currentIndex = 0;
foreach ($lessons as $index => $item) {
    if ((int)$item['id'] === $id) {
        $currentIndex = $index;
        break;
    }
}

$previousLesson = $lessons[$currentIndex - 1] ?? null;
$nextLesson = $lessons[$currentIndex + 1] ?? null;
$progress = course_progress((int)$u['id'], (int)$lesson['course_id']);
$completed = !empty($lessons[$currentIndex]['completed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $pdo->prepare('INSERT INTO lesson_progress(user_id, lesson_id, completed, completed_at)
        VALUES(?,?,1,NOW())
        ON DUPLICATE KEY UPDATE completed=1, completed_at=NOW()')
        ->execute([$u['id'], $id]);

    $progress = course_progress((int)$u['id'], (int)$lesson['course_id']);

    if ($progress >= 100) {
        $pdo->prepare('UPDATE enrollments SET status="completed", completed_at=NOW() WHERE id=?')
            ->execute([$en['id']]);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'complete_next' && $nextLesson) {
        redirect('aluno/aula.php?id='.$nextLesson['id']);
    }

    flash('success', 'Aula concluída.');
    redirect('aluno/aula.php?id='.$id);
}

$videoUrl = trim((string)($lesson['video_url'] ?? ''));
$materialUrl = trim((string)($lesson['material_url'] ?? ''));
$isUpload = str_starts_with($videoUrl, 'uploads/');
$isPdf = $materialUrl !== '';
$viewerUrl = $isUpload ? url($videoUrl) : video_embed_url($videoUrl);
$pdfViewerUrl = $isPdf ? url($materialUrl) : '';
$position = $currentIndex + 1;
$totalLessons = count($lessons);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($lesson['titulo'])?> - <?=e($lesson['course_title'])?></title>
    <link rel="stylesheet" href="<?=e(url('assets/css/style.css'))?>">
    <script>try{document.documentElement.dataset.theme=localStorage.getItem('nettocursos-theme')||'dark';}catch(e){document.documentElement.dataset.theme='dark';}</script>
</head>
<body class="lesson-player-body">
<div class="lesson-player">
    <header class="lesson-topbar">
        <a class="lesson-back" href="<?=e(url('aluno/curso.php?id='.$lesson['course_id']))?>" aria-label="Voltar ao curso">‹</a>
        <a class="lesson-brand" href="<?=e(url())?>">
            <img class="theme-logo theme-logo-dark" src="<?=e(url('assets/img/heal-logo.png'))?>" alt="HEAL">
            <img class="theme-logo theme-logo-light" src="<?=e(url('assets/img/heal-logo-light.png'))?>" alt="HEAL">
        </a>
        <div class="lesson-course-heading">
            <h1><?=e($lesson['course_title'])?></h1>
            <div class="lesson-progress-line">
                <strong><?=$progress?>%</strong> concluído · <?=$position?>/<?=$totalLessons?> aulas
            </div>
        </div>
        <div class="lesson-user">
            <span>Olá, <?=e($u['nome'])?></span>
            <a href="<?=e(url('aluno/area.php'))?>">Minha área</a>
        </div>
    </header>

    <div class="lesson-workspace">
        <main class="lesson-main">
            <section class="lesson-viewer">
                <?php if ($isPdf): ?>
                    <div class="lesson-document-wrap"><iframe class="lesson-document" src="<?=e($pdfViewerUrl)?>#toolbar=1" title="Material PDF da aula"></iframe><a class="lesson-open-material" href="<?=e($pdfViewerUrl)?>" target="_blank" rel="noopener">📄 Abrir PDF em nova aba</a></div>
                <?php elseif ($videoUrl && $isUpload): ?>
                    <video class="lesson-media" controls preload="metadata">
                        <source src="<?=e($viewerUrl)?>">
                        Seu navegador não suporta reprodução de vídeo.
                    </video>
                <?php elseif ($videoUrl): ?>
                    <iframe class="lesson-media" src="<?=e($viewerUrl)?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen title="Vídeo da aula"></iframe>
                <?php else: ?>
                    <div class="lesson-empty-viewer">
                        <div class="lesson-empty-icon">▶</div>
                        <h2>Conteúdo da aula</h2>
                        <p>O professor ainda não adicionou um PDF, vídeo ou outro material para esta aula.</p>
                    </div>
                <?php endif; ?>
            </section>

            <div class="lesson-player-controls">
                <div class="lesson-nav-arrows">
                    <?php if ($previousLesson): ?>
                        <a href="<?=e(url('aluno/aula.php?id='.$previousLesson['id']))?>" title="Aula anterior">|◀</a>
                    <?php else: ?><span class="disabled">|◀</span><?php endif; ?>
                    <?php if ($nextLesson): ?>
                        <a href="<?=e(url('aluno/aula.php?id='.$nextLesson['id']))?>" title="Próxima aula">▶|</a>
                    <?php else: ?><span class="disabled">▶|</span><?php endif; ?>
                </div>
                <div class="lesson-player-tools">
                    <button type="button" onclick="document.querySelector('.lesson-viewer').requestFullscreen?.()" title="Tela cheia">⛶</button>
                </div>
            </div>

            <section class="lesson-info-row">
                <div class="lesson-info-main">
                    <div class="lesson-title-line">
                        <h2><?=e($lesson['titulo'])?></h2>
                        <div class="lesson-actions">
                            <form method="post" class="inline-form">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="<?= $nextLesson ? 'complete_next' : 'complete' ?>">
                                <button class="lesson-complete-btn" type="submit">
                                    <?= $completed ? '✓ Concluída' : '✓ Concluir e avançar' ?>
                                </button>
                            </form>
                            <button class="lesson-action-link" type="button" data-favorite>♡ Favoritar</button>
                            <button class="lesson-action-link" type="button" data-rate>☆ Avaliar</button>
                        </div>
                    </div>

                    <div class="lesson-tabs"><button class="active" type="button">Comentários</button></div>
                    <div class="comments-disabled">
                        <div class="comments-illustration">☁</div>
                        <strong>Comentários desativados</strong>
                    </div>
                </div>

                <aside class="lesson-notes">
                    <div class="lesson-notes-head"><strong>Anotações</strong><button type="button" onclick="focusNotes()">↗</button></div>
                    <textarea id="lessonNotes" placeholder="Escreva suas anotações aqui"></textarea>
                    <div class="lesson-notes-foot">
                        <div class="note-tools"><button type="button">T</button><button type="button">☺</button><button type="button">▧</button></div>
                        <button class="lesson-save-note" type="button" onclick="saveLessonNote()">Salvar</button>
                    </div>
                </aside>
            </section>
        </main>

        <aside class="lesson-sidebar">
            <div class="lesson-sidebar-head">
                <div>
                    <small>CURSO</small>
                    <strong><?=e($lesson['course_title'])?></strong>
                </div>
                <span><?=$progress?>%</span>
            </div>
            <div class="lesson-sidebar-progress"><i style="width:<?=$progress?>%"></i></div>

            <div class="lesson-playlist">
                <?php foreach ($lessons as $index => $item):
                    $isCurrent = (int)$item['id'] === $id;
                    $isDone = !empty($item['completed']);
                ?>
                    <a class="lesson-playlist-item <?=$isCurrent?'current':''?> <?=$isDone?'done':''?>" href="<?=e(url('aluno/aula.php?id='.$item['id']))?>">
                        <span class="playlist-marker"><?= $isDone ? '✓' : str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span>
                        <span class="playlist-text">
                            <strong><?=e($item['titulo'])?></strong>
                            <?php if(!empty($item['descricao'])): ?><small><?=e($item['descricao'])?></small><?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="lesson-evaluation-card">
                <span>0%</span>
                <strong>Avaliação de Aprendizagem</strong>
                <small>Disponível após a conclusão das aulas.</small>
            </div>
        </aside>
    </div>
</div>

<button class="lesson-chat" type="button" aria-label="Abrir atendimento">◔</button>

<script>
(function(){
    const key = 'heal-note-<?= (int)$u['id'] ?>-<?= (int)$id ?>';
    const area = document.getElementById('lessonNotes');
    if(area){ area.value = localStorage.getItem(key) || ''; }
    window.saveLessonNote = function(){
        if(area) localStorage.setItem(key, area.value);
        const btn=document.querySelector('.lesson-save-note');
        if(btn){ const old=btn.textContent; btn.textContent='Salvo ✓'; setTimeout(()=>btn.textContent=old,1200); }
    };
    window.focusNotes=function(){ if(area){area.focus();area.scrollIntoView({behavior:'smooth',block:'center'});} };
    document.querySelectorAll('[data-favorite]').forEach(btn=>btn.addEventListener('click',()=>{
        btn.classList.toggle('selected'); btn.textContent=btn.classList.contains('selected')?'♥ Favoritado':'♡ Favoritar';
    }));
    document.querySelectorAll('[data-rate]').forEach(btn=>btn.addEventListener('click',()=>{
        btn.textContent = btn.textContent.includes('Avaliar') ? '★ Avaliar' : '☆ Avaliar';
    }));
})();
function updateThemeButton(){const light=document.documentElement.dataset.theme==='light';const i=document.querySelector('.theme-icon'),l=document.querySelector('.theme-label');if(i)i.textContent=light?'🌙':'☀️';if(l)l.textContent=light?'Modo escuro':'Modo claro';}
function toggleTheme(){const next=document.documentElement.dataset.theme==='light'?'dark':'light';document.documentElement.dataset.theme=next;try{localStorage.setItem('nettocursos-theme',next);}catch(e){}updateThemeButton();}
</script>
</body>
</html>
