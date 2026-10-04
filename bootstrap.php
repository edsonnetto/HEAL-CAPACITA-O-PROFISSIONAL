<?php
// Bootstrap centralizado para XAMPP + MySQL
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

const DB_HOST = '127.0.0.1';
const DB_NAME = 'nettocursos';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'HEAL | Capacitação Profissional';
// Detecta automaticamente se o projeto está na raiz do htdocs ou em uma subpasta.
function detect_app_base(): string {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRoot = realpath(dirname(__DIR__)) ?: '';
    if ($documentRoot !== '' && $projectRoot !== '') {
        $doc = rtrim(str_replace('\\','/',$documentRoot), '/');
        $proj = rtrim(str_replace('\\','/',$projectRoot), '/');
        if (strcasecmp($doc, $proj) === 0) return '';
        $prefix = str_replace('\\','/',$doc);
        if (stripos($proj, $prefix . '/') === 0) {
            return substr($proj, strlen($prefix));
        }
    }
    return '/NettoCursos-XAMPP';
}
const APP_BASE = '';

const REMEMBER_COOKIE = 'heal_remember';
const REMEMBER_DAYS = 30;
const INFINITEPAY_HANDLE = ''; // Sua InfiniteTag, sem o símbolo $.
const INFINITEPAY_ENABLED = true;
// E-mail de notificacao de novas matriculas.
const ADMIN_EMAIL = 'edsonnetto964@gmail.com';

// SMTP usado para enviar os avisos. Nao coloque sua senha aqui publicamente.
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'edsonnetto964@gmail.com';
const SMTP_PASS = 'COLE_SUA_SENHA_DE_APP_AQUI';
const MAIL_FROM = SMTP_USER;
const MAIL_FROM_NAME = APP_NAME;
const APP_ENV = 'local';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    try {
        $server = new PDO('mysql:host='.DB_HOST.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $server->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        install_schema($pdo);
        ensure_course_presentation_schema($pdo);
        ensure_lesson_media_schema($pdo);
        ensure_enrollment_notification_schema($pdo);
        ensure_payment_gateway_schema($pdo);
        seed_defaults($pdo);
        return $pdo;
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Não foi possível conectar ao MySQL. Verifique se o MySQL do XAMPP está ligado. Detalhe: '.htmlspecialchars($e->getMessage()));
    }
}


function ensure_course_presentation_schema(PDO $pdo): void {
    $columns = [
        'apresentacao_badge' => "VARCHAR(100) NOT NULL DEFAULT 'CAPACITAÇÃO PROFISSIONAL'",
        'apresentacao_titulo' => "VARCHAR(220) NULL",
        'apresentacao_subtitulo' => "VARCHAR(255) NULL",
        'apresentacao_texto' => "TEXT NULL",
        'apresentacao_destaques' => "TEXT NULL",
        'apresentacao_publico' => "TEXT NULL",
        'apresentacao_requisitos' => "TEXT NULL",
        'apresentacao_imagem' => "VARCHAR(255) NULL",
        'cta_texto' => "VARCHAR(100) NOT NULL DEFAULT 'Quero me matricular'",
        'cor_destaque' => "VARCHAR(7) NOT NULL DEFAULT '#0878ff'",
        'cor_secundaria' => "VARCHAR(7) NOT NULL DEFAULT '#ff1e2d'",
        'mostrar_destaques' => "TINYINT(1) NOT NULL DEFAULT 1",
        'mostrar_publico' => "TINYINT(1) NOT NULL DEFAULT 1",
        'mostrar_requisitos' => "TINYINT(1) NOT NULL DEFAULT 1",
        'mostrar_aulas' => "TINYINT(1) NOT NULL DEFAULT 1",
        'infinitepay_link' => "VARCHAR(500) NULL",
    ];
    $existing = [];
    foreach ($pdo->query("SHOW COLUMNS FROM courses") as $row) $existing[$row['Field']] = true;
    foreach ($columns as $name=>$definition) {
        if (!isset($existing[$name])) {
            $pdo->exec("ALTER TABLE courses ADD COLUMN `$name` $definition");
        }
    }
}

function ensure_lesson_media_schema(PDO $pdo): void {
    $columns = [
        'material_url' => "VARCHAR(500) NULL",
    ];
    $existing = [];
    foreach ($pdo->query("SHOW COLUMNS FROM lessons") as $row) $existing[$row['Field']] = true;
    foreach ($columns as $name => $definition) {
        if (!isset($existing[$name])) {
            $pdo->exec("ALTER TABLE lessons ADD COLUMN `$name` $definition");
        }
    }
}

function ensure_enrollment_notification_schema(PDO $pdo): void {
    $existing = [];
    foreach ($pdo->query("SHOW COLUMNS FROM enrollments") as $row) {
        $existing[$row['Field']] = true;
    }
    if (!isset($existing['admin_notified_at'])) {
        $pdo->exec("ALTER TABLE enrollments ADD COLUMN admin_notified_at DATETIME NULL AFTER paid_at");
    }
    if (!isset($existing['student_paid_notified_at'])) {
        $pdo->exec("ALTER TABLE enrollments ADD COLUMN student_paid_notified_at DATETIME NULL AFTER admin_notified_at");
    }
    if (!isset($existing['student_released_notified_at'])) {
        $pdo->exec("ALTER TABLE enrollments ADD COLUMN student_released_notified_at DATETIME NULL AFTER student_paid_notified_at");
    }
}

function ensure_payment_gateway_schema(PDO $pdo): void {
    $columns = [
        'order_nsu' => "VARCHAR(190) NULL",
        'transaction_nsu' => "VARCHAR(190) NULL",
        'invoice_slug' => "VARCHAR(190) NULL",
        'payment_method' => "VARCHAR(40) NULL",
        'receipt_url' => "VARCHAR(500) NULL",
    ];
    $existing = [];
    foreach ($pdo->query("SHOW COLUMNS FROM payments") as $row) $existing[$row['Field']] = true;
    foreach ($columns as $name=>$definition) {
        if (!isset($existing[$name])) $pdo->exec("ALTER TABLE payments ADD COLUMN `$name` $definition");
    }
}

function seed_defaults(PDO $pdo): void {
    $count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if($count===0){
        $hash=password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users(nome,email,senha,role,status) VALUES(?,?,?,?,?)")->execute(['Administrador','admin@localhost',$hash,'admin','active']);
    }
}

function install_schema(PDO $pdo): void {
    $sql = file_get_contents(__DIR__.'/../database/schema.sql');
    if ($sql === false) return;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $pdo->exec($statement);
    }
}

require_once __DIR__.'/functions.php';
// Inicializa o banco em todas as páginas para o usuário não precisar importar SQL manualmente.
db();
