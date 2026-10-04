<?php
require_once __DIR__.'/../config/bootstrap.php';

$payload=json_decode(file_get_contents('php://input'),true)?:$_POST;
$orderNsu=trim((string)($payload['order_nsu']??''));
$transactionNsu=trim((string)($payload['transaction_nsu']??''));
$slug=trim((string)($payload['invoice_slug']??''));

if($orderNsu==='' || !preg_match('/^HEAL-PAY-(\\d+)$/',$orderNsu,$m)){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Pedido não encontrado']);
    exit;
}

$paymentId=(int)$m[1];
$s=db()->prepare('SELECT * FROM payments WHERE id=? AND provider="infinitepay" LIMIT 1');
$s->execute([$paymentId]);
$pay=$s->fetch();
if(!$pay){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Pagamento não encontrado']);
    exit;
}

$expected=(int)round(((float)$pay['amount'])*100);
$received=isset($payload['amount'])?(int)$payload['amount']:$expected;
if($received!==$expected){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Valor do pedido não confere']);
    exit;
}

$db=db();
$db->prepare('UPDATE payments SET external_id=?,order_nsu=?,transaction_nsu=?,invoice_slug=?,payment_method=?,receipt_url=?,raw_response=?,updated_at=NOW() WHERE id=?')
   ->execute([
       $orderNsu,$orderNsu,$transactionNsu,$slug,
       (string)($payload['capture_method']??''),
       (string)($payload['receipt_url']??''),
       json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
       $paymentId
   ]);

if($transactionNsu!=='' && $slug!==''){
    $check=infinitepay_payment_check($pay,$transactionNsu,$slug);
    if(is_array($check) && !empty($check['success']) && !empty($check['paid'])){
        if(isset($check['amount']) && (int)$check['amount']!==$expected){
            http_response_code(400);
            echo json_encode(['success'=>false,'message'=>'Valor confirmado não confere']);
            exit;
        }
        mark_enrollment_paid($paymentId,'approved');
    }
}

http_response_code(200);
echo json_encode(['success'=>true,'message'=>null]);
