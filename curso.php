<?php
require_once __DIR__.'/../config/bootstrap.php';
$u=current_user();
$id=(int)($_GET['id']??0);
$s=db()->prepare('SELECT * FROM courses WHERE id=? AND status="active"');
$s->execute([$id]);
$course=$s->fetch();
if(!$course){http_response_code(404);exit('Curso não encontrado.');}
$en=$u?enrollment((int)$u['id'],$id):null;
$ls=db()->prepare('SELECT * FROM lessons WHERE course_id=? AND status="active" ORDER BY ordem,id');
$ls->execute([$id]);
$lessons=$ls->fetchAll();
$p=$u&&$en?course_progress((int)$u['id'],$id):0;
$firstLesson=$lessons[0]??null;
$hasAccess=$en && in_array($en['status'],['active','completed'],true);
$accessUrl=$firstLesson?url('aluno/aula.php?id='.$firstLesson['id']):url('aluno/area.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$u){flash('warning','Entre ou cadastre-se para realizar a matrícula.');redirect('aluno/login.php');}
    if(!$en){
        verify_csrf();
        if((float)$course['preco']<=0){
            $st=db()->prepare('INSERT INTO enrollments(user_id,course_id,status,paid_at) VALUES(?,? ,"active",NOW()) ON DUPLICATE KEY UPDATE status="active",paid_at=NOW()');
            $st->execute([$u['id'],$id]);
            $newEnrollmentId=(int)db()->lastInsertId();

            // Em matrícula gratuita, o aviso é enviado imediatamente.
            notify_admin_enrollment_once($newEnrollmentId);

            flash('success','Matrícula realizada.');
            redirect('aluno/curso.php?id='.$id);
        }
        redirect('pagamento.php?course='.$id);
    }
}
function course_lines(?string $value): array {
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$value))));
}
function safe_course_color($value, string $fallback): string {
    $value=(string)$value;
    return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?$value:$fallback;
}
$accent=safe_course_color($course['cor_destaque']??null,'#0878ff');
$secondary=safe_course_color($course['cor_secundaria']??null,'#ff1e2d');
$badge=trim((string)($course['apresentacao_badge']??'')) ?: 'CAPACITAÇÃO PROFISSIONAL';
$presTitle=trim((string)($course['apresentacao_titulo']??'')) ?: $course['titulo'];
$subtitle=trim((string)($course['apresentacao_subtitulo']??''));
$presText=trim((string)($course['apresentacao_texto']??'')) ?: ($course['descricao']??'');
$highlights=course_lines($course['apresentacao_destaques']??'');
$audience=course_lines($course['apresentacao_publico']??'');
$requirements=course_lines($course['apresentacao_requisitos']??'');
$presImage=trim((string)($course['apresentacao_imagem']??''));
$heroImage=$presImage ?: ($course['imagem']?:'assets/img/curso1.svg');
$cta=trim((string)($course['cta_texto']??'')) ?: 'Quero me matricular';
?>
<?php layout_header($course['titulo']); ?>
<div class="course-showcase" style="--course-accent:<?=$accent?>;--course-secondary:<?=$secondary?>">
  <section class="course-hero-modern">
    <div class="course-hero-copy">
      <span class="course-badge"><?=e($badge)?></span>
      <div class="course-kicker"><span><?=e($course['categoria'])?></span><i></i><span><?=e($course['carga_horaria'])?> horas</span></div>
      <h1><?=e($presTitle)?></h1>
      <?php if($subtitle): ?><p class="course-subtitle"><?=e($subtitle)?></p><?php endif; ?>
      <p class="course-description"><?=nl2br(e($presText))?></p>
      <div class="course-meta-pills"><span>⏱ <?=e($course['carga_horaria'])?>h</span><span>🎓 Certificado <?=!empty($course['certificado'])?'incluído':'não incluído'?></span><span><?=((float)$course['preco']>0)?'💳 '.money((float)$course['preco']):'✓ Gratuito'?></span></div>
      <?php if($hasAccess): ?><div class="course-enrollment-status"><span>✓</span><div><strong>Você já está matriculado neste curso</strong><small><?=$p?>% concluído · Acesse suas aulas para continuar.</small></div></div><?php elseif($u): ?><div class="course-enrollment-status pending"><span>🎓</span><div><strong>Pronto para começar?</strong><small>Faça sua matrícula e tenha acesso ao conteúdo do curso.</small></div></div><?php else: ?><div class="course-enrollment-status pending"><span>👤</span><div><strong>Faça seu cadastro para se matricular</strong><small>Entre ou crie sua conta para garantir sua matrícula.</small></div></div><?php endif; ?>
      <div class="course-hero-actions">
        <?php if($hasAccess): ?><a class="btn course-cta-primary" href="<?=e($accessUrl)?>">▶ Acessar curso</a>
        <?php elseif($u): ?><form method="post"><?=csrf_field()?><button class="btn course-cta-primary" type="submit"><?=e(((float)$course['preco']>0)?$cta:'Matricular gratuitamente')?></button></form>
        <?php else: ?><a class="btn course-cta-primary" href="<?=e(url('aluno/login.php'))?>"><?=e($cta)?></a><?php endif; ?>
        <a class="btn ghost" href="#conteudo">Ver conteúdo</a>
      </div>
    </div>
    <div class="course-hero-visual">
      <div class="course-image-glow"></div>
      <img src="<?=e(url($heroImage))?>" alt="<?=e($course['titulo'])?>">
      <div class="course-price-float"><small>INVESTIMENTO</small><strong><?=((float)$course['preco']>0)?money((float)$course['preco']):'Gratuito'?></strong></div>
    </div>
  </section>

  <?php if(!empty($course['mostrar_destaques']) && $highlights): ?>
  <section class="presentation-section highlight-section">
    <div class="presentation-heading"><span class="eyebrow">POR QUE ESTE CURSO?</span><h2>O que você vai encontrar</h2></div>
    <div class="highlight-grid"><?php foreach($highlights as $item): ?><div class="highlight-card"><span class="highlight-icon">✓</span><p><?=e($item)?></p></div><?php endforeach; ?></div>
  </section>
  <?php endif; ?>

  <section class="presentation-section two-presentation-columns">
    <?php if(!empty($course['mostrar_publico']) && $audience): ?><div class="info-card"><span class="eyebrow">PERFIL</span><h2>Para quem é</h2><ul><?php foreach($audience as $item): ?><li><?=e($item)?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if(!empty($course['mostrar_requisitos']) && $requirements): ?><div class="info-card"><span class="eyebrow">ANTES DE COMEÇAR</span><h2>Requisitos</h2><ul><?php foreach($requirements as $item): ?><li><?=e($item)?></li><?php endforeach; ?></ul></div><?php endif; ?>
  </section>

  <?php if(!empty($course['mostrar_aulas'])): ?>
  <section id="conteudo" class="presentation-section content-presentation panel">
    <div class="section-head"><div><span class="eyebrow">CONTEÚDO PROGRAMADO</span><h2>O que você vai aprender</h2><p>Confira a estrutura do curso e a sequência das aulas.</p></div><?php if($u&&$en): ?><strong><?=$p?>% concluído</strong><?php endif; ?></div>
    <div class="lesson-list"><?php foreach($lessons as $l): ?><div class="lesson-item preview"><span class="lesson-number"><?=e($l['ordem'])?></span><div><strong><?=e($l['titulo'])?></strong><small><?=e($l['descricao'])?></small></div><span>🔒</span></div><?php endforeach; ?><?php if(!$lessons): ?><div class="empty">O conteúdo das aulas será disponibilizado em breve.</div><?php endif; ?></div>
  </section>
  <?php endif; ?>

  <section class="final-enroll" style="background:linear-gradient(135deg,<?=$accent?>,<?=$secondary?>)">
    <div><span class="eyebrow">PRONTO PARA COMEÇAR?</span><h2><?=e($course['titulo'])?></h2><p>Faça sua matrícula e comece sua jornada de capacitação.</p></div>
    <?php if($hasAccess): ?><a class="btn final-btn" href="<?=e($accessUrl)?>">▶ Acessar curso</a><?php elseif($u): ?><form method="post"><?=csrf_field()?><button class="btn final-btn" type="submit">🎓 <?=e(((float)$course['preco']>0)?$cta:'Matricule-se gratuitamente')?></button></form><?php else: ?><a class="btn final-btn" href="<?=e(url('aluno/login.php'))?>"><?=e($cta)?></a><?php endif; ?>
  </section>
</div>
<?php layout_footer(); ?>
