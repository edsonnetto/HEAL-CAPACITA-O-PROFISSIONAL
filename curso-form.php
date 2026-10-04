<?php
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/media.php';
require_admin();

function slugify_course(string $text): string {
    $text = trim($text);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'curso';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $pdo = db();

    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
                        $titulo = trim($_POST['titulo'] ?? '');
                        if ($titulo === '') throw new RuntimeException('Informe o título do curso.');
                        $slug = slugify_course($_POST['slug'] ?? $titulo);
                        $cat = trim($_POST['categoria'] ?? 'Geral');
                        $desc = trim($_POST['descricao'] ?? '');
                        $horas = max(1, (int)($_POST['carga_horaria'] ?? 10));
                        $precoRaw = str_replace(['R$', ' ', '.'], ['', '', ''], $_POST['preco'] ?? '0');
                        $precoRaw = str_replace(',', '.', $precoRaw);
                        $preco = max(0, (float)$precoRaw);
                        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                        $cert = !empty($_POST['certificado']) ? 1 : 0;
                        $presBadge = trim($_POST['apresentacao_badge'] ?? 'CAPACITAÇÃO PROFISSIONAL');
                        $presTitle = trim($_POST['apresentacao_titulo'] ?? '');
                        $presSubtitle = trim($_POST['apresentacao_subtitulo'] ?? '');
                        $presText = trim($_POST['apresentacao_texto'] ?? '');
                        $presHighlights = trim($_POST['apresentacao_destaques'] ?? '');
                        $presAudience = trim($_POST['apresentacao_publico'] ?? '');
                        $presRequirements = trim($_POST['apresentacao_requisitos'] ?? '');
                        $cta = trim($_POST['cta_texto'] ?? 'Quero me matricular');
                        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['cor_destaque'] ?? '') ? $_POST['cor_destaque'] : '#0878ff';
                        $secondary = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['cor_secundaria'] ?? '') ? $_POST['cor_secundaria'] : '#ff1e2d';
                        $showHighlights = !empty($_POST['mostrar_destaques']) ? 1 : 0;
                        $showAudience = !empty($_POST['mostrar_publico']) ? 1 : 0;
                        $showRequirements = !empty($_POST['mostrar_requisitos']) ? 1 : 0;
                        $showLessons = !empty($_POST['mostrar_aulas']) ? 1 : 0;
                        $infinitepayLink = trim($_POST['infinitepay_link'] ?? '');
                        if ($infinitepayLink !== '' && !filter_var($infinitepayLink, FILTER_VALIDATE_URL)) throw new RuntimeException('O link da InfinitePay não é válido.');

                        // Garante slug único ao criar/editar.
                        $baseSlug = $slug; $n = 2;
                        while (true) {
                            $q = $pdo->prepare('SELECT id FROM courses WHERE slug=? AND id<>?');
                            $q->execute([$slug, $id]);
                            if (!$q->fetch()) break;
                            $slug = $baseSlug.'-'.$n++;
                        }

                        $q = $pdo->prepare('SELECT * FROM courses WHERE id=?');
                        $q->execute([$id]);
                        $old = $q->fetch();
                        $imagem = $old['imagem'] ?? 'assets/img/curso1.svg';
                        $presImage = $old['apresentacao_imagem'] ?? '';
                        if (isset($_FILES['imagem_arquivo']) && $_FILES['imagem_arquivo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            if ($_FILES['imagem_arquivo']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível receber a capa.');
                            $info = @getimagesize($_FILES['imagem_arquivo']['tmp_name']);
                            if (!$info) throw new RuntimeException('A capa enviada não é uma imagem válida.');
                            $w=(int)$info[0]; $h=(int)$info[1];
                            if ($w < 640 || $h < 360) throw new RuntimeException('A capa deve ter no mínimo 640 × 360 px. Tamanho recomendado: 1280 × 720 px (proporção 16:9).');
                            if (abs(($w/$h) - (16/9)) > 0.03) throw new RuntimeException('A capa deve usar proporção 16:9. Recomendado: 1280 × 720 px.');
                        }
                        $uploadedImage = upload_media('imagem_arquivo', 'courses', [
                            'jpg'=>['image/jpeg'], 'jpeg'=>['image/jpeg'], 'png'=>['image/png'], 'webp'=>['image/webp']
                        ], 8 * 1024 * 1024);
                        if ($uploadedImage) $imagem = $uploadedImage;
                        if (isset($_FILES['apresentacao_imagem_arquivo']) && $_FILES['apresentacao_imagem_arquivo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            if ($_FILES['apresentacao_imagem_arquivo']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível receber a imagem da apresentação.');
                            $info2 = @getimagesize($_FILES['apresentacao_imagem_arquivo']['tmp_name']);
                            if (!$info2) throw new RuntimeException('A imagem da apresentação não é válida.');
                            if ((int)$info2[0] < 900 || (int)$info2[1] < 500) throw new RuntimeException('A imagem da apresentação deve ter no mínimo 900 × 500 px. Recomendado: 1600 × 900 px.');
                        }
                        $uploadedPresentation = upload_media('apresentacao_imagem_arquivo', 'courses', [
                            'jpg'=>['image/jpeg'], 'jpeg'=>['image/jpeg'], 'png'=>['image/png'], 'webp'=>['image/webp']
                        ], 8 * 1024 * 1024);
                        if ($uploadedPresentation) $presImage = $uploadedPresentation;

                        if ($id) {
                            $pdo->prepare('UPDATE courses SET titulo=?,slug=?,categoria=?,descricao=?,carga_horaria=?,preco=?,infinitepay_link=?,imagem=?,status=?,certificado=?,apresentacao_badge=?,apresentacao_titulo=?,apresentacao_subtitulo=?,apresentacao_texto=?,apresentacao_destaques=?,apresentacao_publico=?,apresentacao_requisitos=?,apresentacao_imagem=?,cta_texto=?,cor_destaque=?,cor_secundaria=?,mostrar_destaques=?,mostrar_publico=?,mostrar_requisitos=?,mostrar_aulas=? WHERE id=?')
                                ->execute([$titulo,$slug,$cat,$desc,$horas,$preco,$infinitepayLink,$imagem,$status,$cert,$presBadge,$presTitle,$presSubtitle,$presText,$presHighlights,$presAudience,$presRequirements,$presImage,$cta,$accent,$secondary,$showHighlights,$showAudience,$showRequirements,$showLessons,$id]);
                        } else {
                            $pdo->prepare('INSERT INTO courses(titulo,slug,categoria,descricao,carga_horaria,preco,infinitepay_link,imagem,status,certificado,apresentacao_badge,apresentacao_titulo,apresentacao_subtitulo,apresentacao_texto,apresentacao_destaques,apresentacao_publico,apresentacao_requisitos,apresentacao_imagem,cta_texto,cor_destaque,cor_secundaria,mostrar_destaques,mostrar_publico,mostrar_requisitos,mostrar_aulas) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                                ->execute([$titulo,$slug,$cat,$desc,$horas,$preco,$infinitepayLink,$imagem,$status,$cert,$presBadge,$presTitle,$presSubtitle,$presText,$presHighlights,$presAudience,$presRequirements,$presImage,$cta,$accent,$secondary,$showHighlights,$showAudience,$showRequirements,$showLessons]);
                            $id = (int)$pdo->lastInsertId();
                        }
                        if ($uploadedImage && $old && !empty($old['imagem'])) delete_media_file($old['imagem']);
                        if ($uploadedPresentation && $old && !empty($old['apresentacao_imagem'])) delete_media_file($old['apresentacao_imagem']);
                        flash('success', $status === 'active' ? 'Curso salvo e publicado na página principal.' : 'Curso salvo como rascunho.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    $target = !empty($id) ? 'admin/curso-form.php?edit='.(int)$id : 'admin/curso-form.php';
    redirect($target);
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM courses WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
    if (!$edit) {
        flash('error', 'Curso não encontrado.');
        redirect('admin/cursos.php');
    }
}
$notice = flash();
?>
<?php layout_header($edit ? 'Editar curso' : 'Criar curso'); ?>
<?php if ($notice): ?><div class="admin-notice <?=e($notice['type'])?>"><strong><?= $notice['type']==='success' ? '✓ Concluído' : 'Atenção' ?></strong><span><?=e($notice['message'])?></span></div><?php endif; ?>
<div class="dash-head courses-admin-head">
  <div><span class="eyebrow">ADMINISTRAÇÃO / CURSOS</span><h1><?=$edit?'Editar curso':'Criar novo curso'?></h1><p>Configure as informações, capa e apresentação pública do curso.</p></div>
  <a class="btn ghost" href="<?=e(url('admin/cursos.php'))?>">← Voltar para cursos</a>
</div>
<div class="course-admin-nav" style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px;">
<a class="mini-btn" href="<?=e(url('admin/cursos.php'))?>">📚 Gerenciar cursos</a>
<a class="mini-btn" href="<?=e(url('admin/curso-form.php'))?>">＋ Criar curso</a>
<a class="mini-btn" href="<?=e(url('admin/aulas.php'))?>">▶ Aulas</a>
</div>

<div class="admin-form panel">
  <h2><?=$edit?'Editar curso':'Criar novo curso'?></h2>
  <form method="post" enctype="multipart/form-data">
    <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit['id']??0?>">
    <label>Nome do curso</label><input name="titulo" required value="<?=e($edit['titulo']??'')?>" placeholder="Ex.: Operador de Máquinas Pesadas">
    <label>Slug</label><input name="slug" value="<?=e($edit['slug']??'')?>" placeholder="Deixe vazio para gerar automaticamente">
    <label>Categoria</label><input name="categoria" value="<?=e($edit['categoria']??'Geral')?>">
    <label>Descrição do curso</label><textarea name="descricao" rows="4" placeholder="Explique o que o aluno aprenderá..."><?=e($edit['descricao']??'')?></textarea>
    <div class="two-col">
      <div><label>Carga horária</label><input type="number" min="1" name="carga_horaria" value="<?=e($edit['carga_horaria']??10)?>"></div>
      <div><label>Valor do curso (R$)</label><input name="preco" value="<?=e($edit['preco']??0)?>" placeholder="0,00"></div>
    </div>
    <label>Link de pagamento InfinitePay (opcional)</label>
    <input type="url" name="infinitepay_link" value="<?=e($edit['infinitepay_link']??'')?>" placeholder="https://checkout.infinitepay.io/...">
    <small class="form-help">Se preencher, este curso usará este link. Se deixar vazio, o HEAL gerará automaticamente um checkout InfinitePay para cada matrícula.</small>
    <label>Capa do curso</label><input type="file" name="imagem_arquivo" accept="image/jpeg,image/png,image/webp"><small class="form-help cover-help">📐 <strong>Tamanho recomendado: 1280 × 720 px (16:9)</strong><br>Formato JPG, PNG ou WebP · mínimo 640 × 360 px · máximo 8 MB.<br>Usar 1280 × 720 px ajuda todas as capas a ficarem padronizadas no catálogo.</small>
    <?php if(!empty($edit['imagem'])): ?><small class="form-help">Capa atual: <?=e($edit['imagem'])?></small><?php endif; ?>
    <div class="course-editor-divider"><span>APRESENTAÇÃO PERSONALIZADA</span><small>Todo o conteúdo abaixo controla a página pública de apresentação em <code>/aluno/curso.php</code>.</small></div>
    <label>Texto pequeno / selo</label><input name="apresentacao_badge" value="<?=e($edit['apresentacao_badge']??'CAPACITAÇÃO PROFISSIONAL')?>" placeholder="Ex.: FORMAÇÃO PROFISSIONAL">
    <label>Título da apresentação</label><input name="apresentacao_titulo" value="<?=e($edit['apresentacao_titulo']??'')?>" placeholder="Título grande exibido na apresentação">
    <label>Subtítulo</label><input name="apresentacao_subtitulo" value="<?=e($edit['apresentacao_subtitulo']??'')?>" placeholder="Uma frase curta para chamar atenção">
    <label>Texto principal da apresentação</label><textarea name="apresentacao_texto" rows="5" placeholder="Apresente o curso, benefícios e diferenciais..."><?=e($edit['apresentacao_texto']??'')?></textarea>
    <label>Destaques <small>(um por linha)</small></label><textarea name="apresentacao_destaques" rows="5" placeholder="Aulas práticas
Certificado reconhecido
Material de apoio
Acesso online"><?=e($edit['apresentacao_destaques']??'')?></textarea>
    <div class="two-col"><div><label>Para quem é <small>(um por linha)</small></label><textarea name="apresentacao_publico" rows="5"><?=e($edit['apresentacao_publico']??'')?></textarea></div><div><label>Requisitos <small>(um por linha)</small></label><textarea name="apresentacao_requisitos" rows="5"><?=e($edit['apresentacao_requisitos']??'')?></textarea></div></div>
    <label>Imagem da apresentação</label><input type="file" name="apresentacao_imagem_arquivo" accept="image/jpeg,image/png,image/webp"><small class="form-help cover-help">🖼️ <strong>Recomendado: 1600 × 900 px (16:9)</strong><br>JPG, PNG ou WebP · mínimo 900 × 500 px · máximo 8 MB.</small>
    <?php if(!empty($edit['apresentacao_imagem'])): ?><small class="form-help">Imagem atual: <?=e($edit['apresentacao_imagem'])?></small><?php endif; ?>
    <div class="two-col"><div><label>Cor principal da apresentação</label><input type="color" name="cor_destaque" value="<?=e($edit['cor_destaque']??'#0878ff')?>"></div><div><label>Cor secundária</label><input type="color" name="cor_secundaria" value="<?=e($edit['cor_secundaria']??'#ff1e2d')?>"></div></div>
    <label>Texto do botão de matrícula</label><input name="cta_texto" value="<?=e($edit['cta_texto']??'Quero me matricular')?>">
    <div class="presentation-switches"><label class="check"><input type="checkbox" name="mostrar_destaques" value="1" <?=(!isset($edit)||!empty($edit['mostrar_destaques']))?'checked':''?>> Mostrar destaques</label><label class="check"><input type="checkbox" name="mostrar_publico" value="1" <?=(!isset($edit)||!empty($edit['mostrar_publico']))?'checked':''?>> Mostrar “Para quem é”</label><label class="check"><input type="checkbox" name="mostrar_requisitos" value="1" <?=(!isset($edit)||!empty($edit['mostrar_requisitos']))?'checked':''?>> Mostrar requisitos</label><label class="check"><input type="checkbox" name="mostrar_aulas" value="1" <?=(!isset($edit)||!empty($edit['mostrar_aulas']))?'checked':''?>> Mostrar conteúdo/aulas</label></div>
    <label>Status de publicação</label>
    <select name="status"><option value="active" <?=($edit['status']??'active')==='active'?'selected':''?>>Publicado na página principal</option><option value="inactive" <?=($edit['status']??'')==='inactive'?'selected':''?>>Rascunho / não publicar</option></select>
    <label class="check"><input type="checkbox" name="certificado" value="1" <?=(!isset($edit) || $edit['certificado'])?'checked':''?>> Emitir certificado ao concluir</label>
    <button class="btn" type="submit">💾 <?=$edit?"Atualizar curso":"Criar curso"?></button>
  </form>
</div>
<?php layout_footer(); ?>
