<?php
require_once __DIR__.'/../config/bootstrap.php';

$orderNsu=trim((string)($_GET['order_nsu']??''));
$transactionNsu=trim((string)($_GET['transaction_nsu']??''));
$slug=trim((string)($_GET['slug']??''));

if($orderNsu!=='' && preg_match('/^HEAL-PAY-(\\d+)$/',$orderNsu,$m)){
    $paymentId=(int)$m[1];
    $s=db()->prepare('SELECT * FROM payments WHERE id=? AND provider="infinitepay" LIMIT 1');
    $s->execute([$paymentId]);
    $pay=$s->fetch();
    if($pay && $transactionNsu!=='' && $slug!==''){
        $check=infinitepay_payment_check($pay,$transactionNsu,$slug);
        if(is_array($check) && !empty($check['success']) && !empty($check['paid'])){
            $expected=(int)round(((float)$pay['amount'])*100);
            if(!isset($check['amount']) || (int)$check['amount']===$expected){
                db()->prepare('UPDATE payments SET order_nsu=?,transaction_nsu=?,invoice_slug=?,payment_method=?,receipt_url=?,raw_response=?,updated_at=NOW() WHERE id=?')
                    ->execute([
                        $orderNsu,$transactionNsu,$slug,
                        (string)($check['capture_method']??''),
                        (string)($_GET['receipt_url']??''),
                        json_encode($check,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                        $paymentId
                    ]);
                mark_enrollment_paid($paymentId,'approved');
                flash('success','Pagamento confirmado pela InfinitePay. Seu acesso está aguardando a liberação do administrador.');
                redirect('aluno/area.php');
            }
        }
    }
}

flash('warning','Pagamento recebido. Estamos aguardando a confirmação final da InfinitePay. O acesso será liberado somente após a confirmação e a aprovação do administrador.');
redirect('aluno/area.php');
