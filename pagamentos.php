<?php
require_once __DIR__.'/../config/bootstrap.php';
require_admin();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $id=(int)$_POST['id'];
    $action=$_POST['action']??'';
    if($action==='approve') mark_enrollment_paid($id,'approved');
    elseif($action==='reject') mark_enrollment_paid($id,'rejected');
    elseif($action==='release'){
        $s=db()->prepare('SELECT enrollment_id FROM payments WHERE id=? LIMIT 1');
        $s->execute([$id]);
        $enrollmentId=(int)$s->fetchColumn();
        if(release_enrollment($enrollmentId)) flash('success','Acesso ao curso liberado para o aluno.');
        else flash('error','Não foi possível liberar: confirme se o pagamento está aprovado.');
        redirect('admin/pagamentos.php');
    }
    flash('success','Pagamento atualizado.');
    redirect('admin/pagamentos.php');
}
$rows=db()->query('SELECT p.*,u.nome,c.titulo,e.status AS enrollment_status FROM payments p JOIN users u ON u.id=p.user_id JOIN enrollments e ON e.id=p.enrollment_id JOIN courses c ON c.id=e.course_id ORDER BY p.created_at DESC')->fetchAll();
$flash=flash();
?>
<?php layout_header('Pagamentos'); ?>
<div class="dash-head"><div><span class="eyebrow">ADMIN</span><h1>Pagamentos</h1><p>Acompanhe os pagamentos da InfinitePay e libere manualmente o acesso aos cursos.</p></div></div>
<?php if($flash): ?><div class="notice <?=$flash['type']==='error'?'error':'success'?>"><?=e($flash['message'])?></div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Aluno</th><th>Curso</th><th>Gateway</th><th>Valor</th><th>Status</th><th>Data</th><th>Ação</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?=e($r['nome'])?></td><td><?=e($r['titulo'])?></td><td><?=e(strtoupper($r['provider']??'INFINITEPAY'))?></td><td><?=money((float)$r['amount'])?></td>
<td><span class="badge <?=e($r['status'])?>"><?=e($r['status'])?></span></td><td><?=e(date('d/m/Y H:i',strtotime($r['created_at'])))?></td>
<td>
<?php if($r['status']==='pending'): ?>
<form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="action" value="approve"><button class="mini-btn" type="submit">Confirmar pagamento</button></form>
<form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="action" value="reject"><button class="mini-btn danger" type="submit">Rejeitar</button></form>
<?php elseif($r['status']==='approved' && $r['enrollment_status']!=='active'): ?>
<form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="action" value="release"><button class="mini-btn" type="submit">Liberar acesso</button></form>
<?php elseif($r['enrollment_status']==='active'): ?><span class="badge active">Acesso liberado</span>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php layout_footer(); ?>
