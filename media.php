<?php
/**
 * Uploads de mídia da plataforma.
 * Compatível com XAMPP/Windows, inclusive quando o fileinfo identifica
 * alguns vídeos como application/octet-stream.
 */
function upload_media(string $field, string $folder, array $allowed, int $maxBytes = 524288000): ?string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'O vídeo excede o limite upload_max_filesize definido no PHP do XAMPP.',
        UPLOAD_ERR_FORM_SIZE  => 'O vídeo excede o limite máximo definido pelo formulário.',
        UPLOAD_ERR_PARTIAL    => 'O upload foi interrompido antes de terminar. Tente novamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'O PHP não encontrou a pasta temporária de upload.',
        UPLOAD_ERR_CANT_WRITE => 'O PHP não conseguiu gravar o arquivo temporário.',
        UPLOAD_ERR_EXTENSION  => 'Uma extensão do PHP interrompeu o upload.',
    ];

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($messages[$error] ?? 'Falha no upload. Código: '.$error.'.');
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('O arquivo enviado não foi recebido corretamente pelo PHP.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('O arquivo enviado está vazio.');
    }
    if ($size > $maxBytes) {
        throw new RuntimeException('O arquivo excede o limite desta aplicação de '.format_bytes($maxBytes).'.');
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($ext === '' || !isset($allowed[$ext])) {
        throw new RuntimeException('Formato de arquivo não permitido.');
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $detector = new finfo(FILEINFO_MIME_TYPE);
        $detected = $detector->file($file['tmp_name']);
        if (is_string($detected) && $detected !== '') {
            $mime = strtolower($detected);
        }
    }

    $expected = array_map('strtolower', (array)$allowed[$ext]);
    $fallbackMimes = [
        'mp4' => ['application/octet-stream', 'application/mp4', 'video/mp4'],
        'webm' => ['application/octet-stream', 'video/webm'],
        'ogg' => ['application/octet-stream', 'video/ogg', 'application/ogg'],
        'mov' => ['application/octet-stream', 'video/quicktime'],
        'm4v' => ['application/octet-stream', 'video/x-m4v', 'video/mp4'],
        'pdf' => ['application/octet-stream', 'application/pdf', 'application/x-pdf'],
    ];

    $accepted = array_unique(array_merge($expected, $fallbackMimes[$ext] ?? []));
    if (!in_array($mime, $accepted, true)) {
        throw new RuntimeException('O PHP identificou o arquivo como "'.$mime.'". Verifique se o arquivo realmente é um '.$ext.'.');
    }

    $root = dirname(__DIR__);
    $relativeDir = 'uploads/'.trim($folder, '/');
    $absoluteDir = $root.'/'.$relativeDir;

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true)) {
        throw new RuntimeException('Não foi possível criar a pasta de upload.');
    }
    if (!is_writable($absoluteDir)) {
        throw new RuntimeException('A pasta '.$relativeDir.' não possui permissão de escrita.');
    }

    $name = bin2hex(random_bytes(16)).'.'.$ext;
    $target = $absoluteDir.'/'.$name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('O PHP recebeu o arquivo, mas não conseguiu salvá-lo em '.$relativeDir.'.');
    }

    @chmod($target, 0644);
    return $relativeDir.'/'.$name;
}

function format_bytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1).' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1).' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1).' KB';
    return $bytes.' bytes';
}

function delete_media_file(?string $relativePath): void {
    if (!$relativePath || str_contains($relativePath, '..')) return;
    if (!str_starts_with($relativePath, 'uploads/')) return;
    $file = dirname(__DIR__).'/'.$relativePath;
    if (is_file($file)) @unlink($file);
}
