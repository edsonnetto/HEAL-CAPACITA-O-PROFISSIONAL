CONFIGURAÇÃO DE UPLOADS — XAMPP

O sistema de aulas permite ao administrador:
1. Enviar PDF (até 100 MB pela aplicação);
2. Enviar vídeo MP4/WebM/OGG/MOV/M4V (até 500 MB pela aplicação);
3. Usar um vídeo do YouTube colando a URL.

IMPORTANTE NO XAMPP:
O PHP precisa permitir arquivos maiores que o limite padrão.

Edite:
C:\xampp\php\php.ini

Recomendação para vídeos:
upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 600
max_input_time = 600
memory_limit = 512M

Depois de salvar o php.ini, reinicie o Apache pelo XAMPP.

Para PDFs de até 100 MB, as mesmas configurações acima também funcionam.

O arquivo enviado fica em:
/uploads/videos/  (vídeos)
/uploads/pdfs/    (PDFs)

O sistema valida a extensão e o MIME real do arquivo antes de salvar.
