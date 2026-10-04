<?php
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_base(): string { static $base = null; if ($base !== null) return $base; $base = detect_app_base(); return $base; }
function url(string $path=''): string { $base=app_base(); return ($base ? rtrim($base,'/') : '').'/'.ltrim($path,'/'); }
function redirect(string $path): never { header('Location: '.(preg_match('#^https?://#',$path)?$path:url($path))); exit; }
function flash(?string $type=null, ?string $message=null): ?array {
    if ($message !== null) { $_SESSION['_flash'] = ['type'=>$type,'message'=>$message]; return null; }
    $f = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return $f;
}
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Sessão expirada. Volte e tente novamente.'); } }
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $u = false; if ($u !== false) return $u;
    $st=db()->prepare('SELECT id,nome,email,role,status,created_at FROM users WHERE id=? LIMIT 1'); $st->execute([$_SESSION['user_id']]);
    $u=$st->fetch() ?: null; return $u;
}
function login_user(int $id): void { session_regenerate_id(true); $_SESSION['user_id']=$id; }
function logout_user(): void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']); } session_destroy(); setcookie(REMEMBER_COOKIE,'',time()-3600,'/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off', true); }
function require_login(): array { $u=current_user(); if (!$u) { $_SESSION['after_login']=$_SERVER['REQUEST_URI']??url('aluno/area.php'); flash('error','Entre na sua conta para continuar.'); redirect('aluno/login.php'); } if ($u['status']!=='active') { logout_user(); exit('Sua conta está bloqueada.'); } return $u; }
function require_admin(): array { $u=current_user(); if (!$u || $u['role']!=='admin') { flash('error','Acesso restrito ao administrador.'); redirect('admin/login.php'); } return $u; }
function remember_login(int $userId): void {
    $raw=bin2hex(random_bytes(32)); $hash=hash('sha256',$raw); $expires=date('Y-m-d H:i:s',time()+REMEMBER_DAYS*86400);
    db()->prepare('DELETE FROM remember_tokens WHERE user_id=?')->execute([$userId]);
    db()->prepare('INSERT INTO remember_tokens(user_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$userId,$hash,$expires]);
    setcookie(REMEMBER_COOKIE,$raw,time()+REMEMBER_DAYS*86400, '/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off', true);
}
function auto_login(): void {
    if (current_user() || empty($_COOKIE[REMEMBER_COOKIE])) return;
    $hash=hash('sha256',$_COOKIE[REMEMBER_COOKIE]);
    $st=db()->prepare('SELECT u.* FROM remember_tokens r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.expires_at>NOW() AND u.status="active" LIMIT 1'); $st->execute([$hash]);
    if ($u=$st->fetch()) { login_user((int)$u['id']); }
}
auto_login();
function money(float $v): string { return 'R$ '.number_format($v,2,',','.'); }
function random_code(int $length=11): string { $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $out=''; for($i=0;$i<$length;$i++) $out.=$alphabet[random_int(0,strlen($alphabet)-1)]; return $out; }
function app_base_url(): string { $https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'; $host=$_SERVER['HTTP_HOST']??'localhost'; return ($https?'https':'http').'://'.$host.app_base(); }
function smtp_read($socket): string {
    $data = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) break;
        $data .= $line;
        if (preg_match('/^\d{3} /', $line)) break;
    }
    return $data;
}

function smtp_expect($socket, array $codes): void {
    $response = smtp_read($socket);
    $code = (int)substr(trim($response), 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP: resposta inesperada '.$response);
    }
}

function smtp_command($socket, string $command, array $codes): void {
    fwrite($socket, $command."\r\n");
    smtp_expect($socket, $codes);
}

/**
 * Envia e-mail por SMTP diretamente, sem depender da configuracao
 * do mail() do XAMPP.
 *
 * Para Gmail, use uma senha de aplicativo na constante SMTP_PASS.
 */
function send_smtp_email(string $to, string $subject, string $body): bool {
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $socket = stream_socket_client(
            'tcp://'.SMTP_HOST.':'.SMTP_PORT,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new RuntimeException("Não foi possível conectar ao SMTP: {$errstr} ({$errno})");
        }

        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO localhost', [250]);

        smtp_command($socket, 'STARTTLS', [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Não foi possível ativar TLS no SMTP.');
        }

        smtp_command($socket, 'EHLO localhost', [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode(SMTP_USER), [334]);
        smtp_command($socket, base64_encode(SMTP_PASS), [235]);

        smtp_command($socket, 'MAIL FROM:<'.SMTP_USER.'>', [250]);
        smtp_command($socket, 'RCPT TO:<'.$to.'>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $headers = [
            'From: '.MAIL_FROM_NAME.' <'.SMTP_USER.'>',
            'To: <'.$to.'>',
            'Subject: '.$encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        // "." no inicio de uma linha precisa ser escapado no SMTP.
        $safeBody = preg_replace('/^\./m', '..', $body);
        $message = implode("\r\n", $headers)."\r\n\r\n".$safeBody."\r\n.";
        fwrite($socket, $message."\r\n");
        smtp_expect($socket, [250]);

        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('[NettoCursos] Falha ao enviar e-mail: '.$e->getMessage());
        if (isset($socket) && is_resource($socket)) fclose($socket);
        return false;
    }
}

function notify_admin_new_student(int $userId): bool {
    $s = db()->prepare('SELECT id,nome,email,data_nascimento,created_at FROM users WHERE id=? LIMIT 1');
    $s->execute([$userId]);
    $row = $s->fetch();

    if (!$row || trim(ADMIN_EMAIL)==='' || ADMIN_EMAIL==='SEU_EMAIL_AQUI@gmail.com') {
        return false;
    }

    $subject = 'Novo aluno cadastrado - '.APP_NAME;
    $nascimento = !empty($row['data_nascimento']) ? date('d/m/Y', strtotime($row['data_nascimento'])) : 'Não informado';
    $dataCadastro = !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : date('d/m/Y H:i');

    $body =
        "Olá, administrador!\n\n".
        "Um novo aluno foi cadastrado no sistema HEAL.\n\n".
        "DADOS DO ALUNO\n".
        "Nome: ".$row['nome']."\n".
        "E-mail: ".$row['email']."\n".
        "Data de nascimento: ".$nascimento."\n".
        "Data do cadastro: ".$dataCadastro."\n\n".
        "Acesse o painel administrativo para consultar os dados do aluno.";

    return send_smtp_email(ADMIN_EMAIL, $subject, $body);
}

function send_reset_email(string $email,string $name,string $link): bool {
    $subject='Redefinição de senha - '.APP_NAME;
    $body="Olá {$name},\n\nUse o link abaixo para redefinir sua senha:\n{$link}\n\nSe você não solicitou isso, ignore esta mensagem.";
    return send_smtp_email($email, $subject, $body);
}

function notify_admin_new_enrollment(int $enrollmentId): bool {
    $pdo = db();

    $s = $pdo->prepare('
        SELECT e.id,e.status,e.paid_at,u.nome AS aluno_nome,u.email AS aluno_email,
               c.titulo AS curso,c.preco
        FROM enrollments e
        JOIN users u ON u.id=e.user_id
        JOIN courses c ON c.id=e.course_id
        WHERE e.id=?
        LIMIT 1
    ');
    $s->execute([$enrollmentId]);
    $row = $s->fetch();

    if (!$row || trim(ADMIN_EMAIL)==='' || ADMIN_EMAIL==='SEU_EMAIL_AQUI@gmail.com') {
        return false;
    }

    $subject = 'Pedido de liberação - matrícula paga - '.APP_NAME;
    $valor = money((float)$row['preco']);
    $status = $row['status'] === 'active' ? 'Liberada/ativa' : 'Aguardando liberação';

    $body =
        "Olá, administrador!\n\n".
        "Um aluno solicitou matrícula em um curso pago e está aguardando a liberação do acesso.\n\n".
        "ALUNO\n".
        "Nome: ".$row['aluno_nome']."\n".
        "E-mail: ".$row['aluno_email']."\n\n".
        "CURSO\n".
        "Curso: ".$row['curso']."\n".
        "Valor: ".$valor."\n".
        "Status da matrícula: ".$status."\n".
        "Data da solicitação: ".date('d/m/Y H:i')."\n\n".
        "AÇÃO NECESSÁRIA\n".
        "Após confirmar o pagamento, acesse o painel administrativo e clique em 'Liberar acesso' para permitir que o aluno veja as aulas.\n\n".
        "Painel: ".url('admin/pagamentos.php');

    return send_smtp_email(ADMIN_EMAIL, $subject, $body);
}

function notify_student_payment_pending(int $enrollmentId): bool {
    $s = db()->prepare('SELECT e.id,e.status,e.paid_at,u.nome AS aluno_nome,u.email AS aluno_email,c.titulo AS curso,c.preco FROM enrollments e JOIN users u ON u.id=e.user_id JOIN courses c ON c.id=e.course_id WHERE e.id=? LIMIT 1');
    $s->execute([$enrollmentId]);
    $row = $s->fetch();
    if (!$row || trim((string)$row['aluno_email']) === '') return false;

    $subject = 'Matrícula recebida - aguarde a liberação - '.APP_NAME;
    $valor = money((float)$row['preco']);
    $body =
        "Olá, ".$row['aluno_nome']."!\n\n".
        "Recebemos sua matrícula no curso pago \"".$row['curso']."\".\n\n".
        "INFORMAÇÕES DA MATRÍCULA\n".
        "Curso: ".$row['curso']."\n".
        "Valor: ".$valor."\n".
        "Status: pagamento confirmado — aguardando liberação\n\n".
        "Sua matrícula foi recebida e o pagamento foi confirmado. Neste momento, seu acesso às aulas ainda está bloqueado.\n\n".
        "A equipe da HEAL foi notificada e fará a liberação do seu acesso. Por favor, aguarde.\n\n".
        "Quando a liberação for concluída, você receberá um novo e-mail informando que já pode acessar o curso.\n\n".
        "Atenciosamente,\n".APP_NAME;

    return send_smtp_email($row['aluno_email'], $subject, $body);
}

function notify_student_payment_pending_once(int $enrollmentId): void {
    $pdo = db();
    $s = $pdo->prepare('SELECT student_paid_notified_at FROM enrollments WHERE id=? LIMIT 1');
    $s->execute([$enrollmentId]);
    if ($s->fetchColumn()) return;

    if (notify_student_payment_pending($enrollmentId)) {
        $pdo->prepare('UPDATE enrollments SET student_paid_notified_at=NOW() WHERE id=? AND student_paid_notified_at IS NULL')->execute([$enrollmentId]);
    }
}

function notify_student_access_released(int $enrollmentId): bool {
    $s = db()->prepare('SELECT e.id,e.status,u.nome AS aluno_nome,u.email AS aluno_email,c.titulo AS curso FROM enrollments e JOIN users u ON u.id=e.user_id JOIN courses c ON c.id=e.course_id WHERE e.id=? LIMIT 1');
    $s->execute([$enrollmentId]);
    $row = $s->fetch();
    if (!$row || trim((string)$row['aluno_email']) === '') return false;

    $subject = 'Acesso liberado - '.$row['curso'].' - '.APP_NAME;
    $body =
        "Olá, ".$row['aluno_nome']."!\n\n".
        "Seu acesso ao curso \"".$row['curso']."\" foi liberado pela equipe da HEAL.\n\n".
        "Você já pode entrar na sua área do aluno e acessar as aulas e materiais do curso.\n\n".
        "Acesse: ".url('aluno/area.php')."\n\n".
        "Atenciosamente,\n".APP_NAME;

    return send_smtp_email($row['aluno_email'], $subject, $body);
}

function notify_student_access_released_once(int $enrollmentId): void {
    $pdo = db();
    $s = $pdo->prepare('SELECT student_released_notified_at FROM enrollments WHERE id=? LIMIT 1');
    $s->execute([$enrollmentId]);
    if ($s->fetchColumn()) return;

    if (notify_student_access_released($enrollmentId)) {
        $pdo->prepare('UPDATE enrollments SET student_released_notified_at=NOW() WHERE id=? AND student_released_notified_at IS NULL')->execute([$enrollmentId]);
    }
}

function notify_admin_enrollment_once(int $enrollmentId): void {
    $pdo = db();

    $s = $pdo->prepare('SELECT admin_notified_at FROM enrollments WHERE id=? LIMIT 1');
    $s->execute([$enrollmentId]);
    $already = $s->fetchColumn();

    if ($already) return;

    if (notify_admin_new_enrollment($enrollmentId)) {
        $pdo->prepare('UPDATE enrollments SET admin_notified_at=NOW() WHERE id=? AND admin_notified_at IS NULL')
            ->execute([$enrollmentId]);
    }
}
function enrollment(int $userId,int $courseId): ?array { $s=db()->prepare('SELECT e.*,c.titulo,c.carga_horaria,c.preco,c.certificado FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.user_id=? AND e.course_id=?'); $s->execute([$userId,$courseId]); return $s->fetch()?:null; }
function course_progress(int $userId,int $courseId): int { $s=db()->prepare('SELECT COUNT(*) total, SUM(CASE WHEN lp.completed=1 THEN 1 ELSE 0 END) done FROM lessons l LEFT JOIN lesson_progress lp ON lp.lesson_id=l.id AND lp.user_id=? WHERE l.course_id=? AND l.status="active"'); $s->execute([$userId,$courseId]); $r=$s->fetch(); return (int)$r['total'] ? (int)round(((int)$r['done']/(int)$r['total'])*100) : 0; }
function infinitepay_create_checkout(array $user,array $course,array $payment): ?string {
    if (!INFINITEPAY_ENABLED || trim(INFINITEPAY_HANDLE)==='') return null;

    $paymentId=(int)$payment['id'];
    $orderNsu='HEAL-PAY-'.$paymentId;
    $amountCents=(int)round(((float)$course['preco'])*100);
    $payload=[
        'handle'=>trim(INFINITEPAY_HANDLE),
        'redirect_url'=>app_base_url().'/pagamento/retorno.php',
        'webhook_url'=>app_base_url().'/pagamento/webhook.php',
        'order_nsu'=>$orderNsu,
        'items'=>[[
            'quantity'=>1,
            'price'=>$amountCents,
            'description'=>$course['titulo'],
        ]],
        'customer'=>[
            'name'=>$user['nome'],
            'email'=>$user['email'],
        ],
    ];

    $ch=curl_init('https://api.checkout.infinitepay.io/links');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT=>30,
    ]);
    $res=curl_exec($ch);
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);

    if($code<200||$code>=300||!$res) return null;
    $data=json_decode($res,true);
    $url=$data['url']??null;
    if(!$url) return null;

    db()->prepare('UPDATE payments SET provider="infinitepay",external_id=?,order_nsu=?,raw_response=?,updated_at=NOW() WHERE id=?')
        ->execute([$orderNsu,$orderNsu,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$paymentId]);
    return $url;
}

function infinitepay_payment_check(array $payment,string $transactionNsu,string $slug): ?array {
    if (!INFINITEPAY_ENABLED || trim(INFINITEPAY_HANDLE)==='' || trim($transactionNsu)==='' || trim($slug)==='') return null;
    $payload=[
        'handle'=>trim(INFINITEPAY_HANDLE),
        'order_nsu'=>(string)($payment['order_nsu']??''),
        'transaction_nsu'=>$transactionNsu,
        'slug'=>$slug,
    ];
    if($payload['order_nsu']==='') return null;

    $ch=curl_init('https://api.checkout.infinitepay.io/payment_check');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT=>20,
    ]);
    $res=curl_exec($ch);
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($code<200||$code>=300||!$res) return null;
    $data=json_decode($res,true);
    return is_array($data)?$data:null;
}

function make_payment_preference(array $user,array $course,array $payment): ?string {
    // Se o administrador cadastrou um link fixo para este curso, usa-o.
    // Caso contrário, gera um checkout individual pela API InfinitePay.
    $custom = trim((string)($course['infinitepay_link'] ?? ''));
    if ($custom !== '' && filter_var($custom, FILTER_VALIDATE_URL)) {
        db()->prepare('UPDATE payments SET provider="infinitepay",external_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$custom, (int)$payment['id']]);
        return $custom;
    }
    return infinitepay_create_checkout($user,$course,$payment);
}

function mark_enrollment_paid(int $paymentId,string $status='approved'): void {
    $pdo=db();
    $enrollmentId=null;

    $pdo->beginTransaction();
    try {
        $p=$pdo->prepare('SELECT * FROM payments WHERE id=? FOR UPDATE');
        $p->execute([$paymentId]);
        $pay=$p->fetch();

        if(!$pay){
            $pdo->rollBack();
            return;
        }

        $pdo->prepare('UPDATE payments SET status=?,paid_at=IF(?="approved",NOW(),paid_at),updated_at=NOW() WHERE id=?')
            ->execute([$status,$status,$paymentId]);

        if($status==='approved'){
            $enrollmentId=(int)$pay['enrollment_id'];

            // O pagamento pode estar aprovado, mas o acesso continua bloqueado
            // até o administrador clicar em "Liberar acesso".
            $pdo->prepare('UPDATE enrollments SET paid_at=NOW() WHERE id=?')
                ->execute([$enrollmentId]);
        }

        $pdo->commit();
        if ($status === 'approved' && $enrollmentId) {
            // Após a confirmação do pagamento, notifica o administrador
            // e informa ao aluno que ele deve aguardar a liberação.
            notify_admin_enrollment_once($enrollmentId);
            notify_student_payment_pending_once($enrollmentId);
        }
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

}

function release_enrollment(int $enrollmentId): bool {
    $pdo=db();
    $s=$pdo->prepare('SELECT e.id,e.status,p.status AS payment_status
        FROM enrollments e
        LEFT JOIN payments p ON p.enrollment_id=e.id
        WHERE e.id=?
        ORDER BY p.id DESC
        LIMIT 1');
    $s->execute([$enrollmentId]);
    $row=$s->fetch();
    if(!$row || $row['status']==='active') return false;
    if($row['payment_status']!=='approved') return false;

    $u=$pdo->prepare('UPDATE enrollments SET status="active",paid_at=COALESCE(paid_at,NOW()) WHERE id=?');
    $u->execute([$enrollmentId]);
    $released = $u->rowCount()>0;
    if ($released) {
        notify_student_access_released_once($enrollmentId);
    }
    return $released;
}
function layout_header(string $title): void {
    $u = current_user();
    $home = url('index.php');
    $logo = url('assets/img/heal-logo.png');
    $logoLight = url('assets/img/heal-logo-light.png');
    $css = url('assets/css/style.css');
    $area = $u ? url($u['role'] === 'admin' ? 'admin/index.php' : 'aluno/area.php') : '';
    $logout = url('logout.php');
    $login = url('aluno/login.php');
    $register = url('aluno/cadastro.php');

    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).' - '.e(APP_NAME).'</title>';
    echo '<link rel="stylesheet" href="'.e($css).'">';
    echo '<script>try{document.documentElement.dataset.theme=localStorage.getItem("nettocursos-theme")||"dark";}catch(e){document.documentElement.dataset.theme="dark";}</script>';
    echo '</head><body><button class="theme-toggle" type="button" aria-label="Alternar modo de cor" onclick="toggleTheme()"><span class="theme-icon">☀️</span><span class="theme-label">Modo claro</span></button><div class="app-shell">';
    echo '<header class="topbar">';
    echo '<a href="'.e($home).'" class="top-brand"><img class="theme-logo theme-logo-dark" src="'.e($logo).'" alt="HEAL"><img class="theme-logo theme-logo-light" src="'.e($logoLight).'" alt="HEAL"></a>';
    echo '<div class="top-actions">';
    if ($u) {
        echo '<span class="top-user">'.e($u['nome']).'</span>';
        echo '<a href="'.e($area).'" class="mini-btn">Minha área</a>';
        echo '<a href="'.e($logout).'" class="mini-btn danger">Sair</a>';
    } else {
        echo '<a href="'.e($login).'" class="mini-btn">Entrar</a>';
        echo '<a href="'.e($register).'" class="mini-btn primary">Cadastrar</a>';
    }
    echo '</div></header><main class="app-main"><div class="page-wrap">';
}

function layout_footer(): void {
    echo '</div></main></div>';
    echo '<script>function updateThemeButton(){const light=document.documentElement.dataset.theme==="light";const i=document.querySelector(".theme-icon"),l=document.querySelector(".theme-label");if(i)i.textContent=light?"🌙":"☀️";if(l)l.textContent=light?"Modo escuro":"Modo claro";} function toggleTheme(){const next=document.documentElement.dataset.theme==="light"?"dark":"light";document.documentElement.dataset.theme=next;try{localStorage.setItem("nettocursos-theme",next);}catch(e){} updateThemeButton();} updateThemeButton();</script></body></html>';
}
