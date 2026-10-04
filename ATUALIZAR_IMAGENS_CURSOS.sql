-- Atualização das capas e catálogo de cursos do NettoCursos
-- Execute este arquivo no phpMyAdmin dentro do banco usado pelo site.
-- As imagens devem estar em: htdocs/assets/img/cursos/

UPDATE courses SET
    imagem='assets/img/cursos/nr35.png',
    categoria='Geral',
    descricao='Curso introdutório sobre segurança para trabalho em altura e fundamentos da NR35.'
WHERE slug='nocoes-basicas-de-nr35';

UPDATE courses SET
    imagem='assets/img/cursos/seguranca-no-trabalho.png',
    categoria='Saúde e Segurança',
    descricao='Boas práticas de prevenção e segurança no ambiente profissional.'
WHERE slug='seguranca-no-trabalho';

UPDATE courses SET
    imagem='assets/img/cursos/mecanica-basica.png',
    categoria='Manutenção',
    descricao='Noções fundamentais de manutenção e mecânica.'
WHERE slug='mecanica-basica';

UPDATE courses SET
    imagem='assets/img/cursos/nr20-inflamaveis-e-combustiveis.png',
    categoria='Normas Regulamentadoras',
    descricao='Formação introdutória sobre segurança com inflamáveis e combustíveis.'
WHERE slug='nr-20-inflamaveis-e-combustiveis';

UPDATE courses SET
    imagem='assets/img/cursos/operador-de-maquinas-pesadas.png',
    categoria='Segurança e Operação',
    descricao='Curso online com fundamentos de operação segura de máquinas pesadas.'
WHERE slug='operador-de-maquinas-pesadas';

INSERT INTO courses
(titulo,slug,categoria,descricao,carga_horaria,preco,imagem,status,certificado)
SELECT
'Noções Básicas de NR35',
'nocoes-basicas-de-nr35',
'Geral',
'Curso introdutório sobre segurança para trabalho em altura e fundamentos da NR35.',
20,0,'assets/img/cursos/nr35.png','active',1
WHERE NOT EXISTS (
    SELECT 1 FROM courses WHERE slug='nocoes-basicas-de-nr35'
);
