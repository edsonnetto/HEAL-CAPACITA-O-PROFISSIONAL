CORREÇÃO DE UPLOAD DE VÍDEOS - XAMPP
====================================

Esta versão corrige o fluxo de vídeo das aulas.

O sistema agora:
- aceita MP4, WebM, OGG, MOV e M4V;
- aceita alguns vídeos que o Windows/XAMPP identifica como application/octet-stream;
- só apaga o vídeo anterior depois que o novo upload foi salvo com sucesso;
- mostra uma mensagem específica quando o PHP bloqueia o arquivo;
- grava o caminho em lessons.video_url;
- salva os arquivos em uploads/videos/;
- reproduz o arquivo enviado em /aluno/aula.php.

ATENÇÃO: o PHP pode bloquear arquivos antes que o sistema receba o upload.
No XAMPP, abra:

C:\xampp\php\php.ini

Ajuste, por exemplo:

upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 600
max_input_time = 600

Salve e reinicie o Apache no XAMPP.

O limite de 500 MB é o limite da aplicação; o PHP precisa ter um limite igual
ou maior para que arquivos grandes cheguem ao código.
