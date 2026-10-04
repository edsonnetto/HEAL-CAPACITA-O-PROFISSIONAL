<?php
require_once __DIR__.'/config/bootstrap.php';

$u=require_login();
$cid=(int)($_GET['course']??0);
$s=db()->prepare('SELECT * FROM courses WHERE id=? AND status="active"');
$s->execute([$cid]);
$c=$s->fetch();
if(!$c) exit('Curso não encontrado.');
if((float)$c['preco']<=0) redirect('aluno/curso.php?id='.$cid);

$en=enrollment((int)$u['id'],$cid);
if(!$en){
    db()->prepare('INSERT INTO enrollments(user_id,course_id,status) VALUES(?,? ,"pending")')->execute([$u['id'],$cid]);
    $en=enrollment((int)$u['id'],$cid);
}

// Se o aluno voltou do checkout sem pagar ou o link antigo não funciona,
// ele pode solicitar uma nova cobrança. A matrícula continua sendo a mesma.
$novo=(int)($_GET['novo']??0)===1;
if($novo){
    db()->prepare('UPDATE payments SET status="cancelled",updated_at=NOW() WHERE enrollment_id=? AND status="pending"')
        ->execute([$en['id']]);
}

$s=db()->prepare('SELECT * FROM payments WHERE enrollment_id=? AND status="pending" ORDER BY id DESC LIMIT 1');
$s->execute([$en['id']]);
$pay=$s->fetch();

if(!$pay){
    db()->prepare('INSERT INTO payments(user_id,enrollment_id,amount,provider) VALUES(?,?,?,"infinitepay")')
        ->execute([$u['id'],$en['id'],$c['preco']]);
    $pid=(int)db()->lastInsertId();
    $s=db()->prepare('SELECT * FROM payments WHERE id=?');
    $s->execute([$pid]);
    $pay=$s->fetch();
}

$checkout=make_payment_preference($u,$c,$pay);
if($checkout){
    db()->prepare('UPDATE payments SET external_id=? WHERE id=?')->execute([$checkout,$pay['id']]);
}
?>
<?php layout_header('Pagamento'); ?>
<div class="auth-card">
    <h1>Pagamento</h1>
    <p class="subtitle">Finalize sua matrícula em <strong><?=e($c['titulo'])?></strong>.</p>
    <div class="price-box"><span>Total</span><strong><?=money((float)$c['preco'])?></strong></div>

    <?php if($checkout): ?>
        <a class="btn" href="<?=e($checkout)?>">Pagar com InfinitePay</a>
        <?php if (!empty($c['infinitepay_link'])): ?>
            <p class="login-hint">Este curso está usando o link de pagamento InfinitePay configurado pelo administrador.</p>
        <?php endif; ?>
        <p class="login-hint">Você pode pagar por Pix ou cartão. Se sair do checkout sem concluir o pagamento, volte para esta página e clique em <strong>Tentar pagar novamente</strong>.</p>
        <p style="margin-top:14px"><a class="btn ghost" href="<?=e(url('pagamento.php?course='.$cid.'&novo=1'))?>">Tentar pagar novamente</a></p>
        <p class="login-hint">O acesso ao curso só será liberado depois da confirmação do pagamento e da liberação manual pelo administrador.</p>
    <?php else: ?>
        <div class="notice warning">
            <strong>Não foi possível criar o checkout da InfinitePay.</strong><br>
            Confira sua InfiniteTag em <code>config/bootstrap.php</code> e se o Checkout Integrado está habilitado na sua conta InfinitePay.
        </div>
        <p>Se você já tentou pagar anteriormente, use o botão abaixo para gerar uma nova cobrança.</p>
        <p><a class="btn" href="<?=e(url('pagamento.php?course='.$cid.'&novo=1'))?>">Gerar novo pagamento</a></p>
        <p><a class="btn ghost" href="<?=e(url('aluno/area.php'))?>">Voltar à área do aluno</a></p>
    <?php endif; ?>
</div>
<?php layout_footer(); ?>
