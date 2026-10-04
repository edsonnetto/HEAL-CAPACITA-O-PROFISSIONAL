INTEGRAÇÃO DAS IMAGENS DOS CURSOS

As 5 capas foram adicionadas em:
assets/img/cursos/

Arquivos:
- nr35.png
- seguranca-no-trabalho.png
- mecanica-basica.png
- nr20-inflamaveis-e-combustiveis.png
- operador-de-maquinas-pesadas.png

PARA UM BANCO NOVO:
Use database/schema.sql normalmente. Ele já cria os 5 cursos com as novas capas.

PARA UM SITE QUE JÁ POSSUI BANCO:
1. Copie/substitua a pasta htdocs do pacote.
2. Mantenha seu config/bootstrap.php com as credenciais do seu banco.
3. Abra o phpMyAdmin e selecione o banco usado pelo site.
4. Importe/excute database/ATUALIZAR_IMAGENS_CURSOS.sql.
5. Abra o site e atualize com Ctrl+F5.

A atualização não apaga aulas, matrículas, usuários ou progresso.
