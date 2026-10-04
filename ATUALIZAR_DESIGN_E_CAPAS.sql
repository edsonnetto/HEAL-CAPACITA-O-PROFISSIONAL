-- NettoCursos: atualização visual das capas 16:9
-- Execute no banco do projeto já existente.
UPDATE courses SET imagem='assets/img/cursos/operador-de-maquinas-pesadas.png' WHERE slug='operador-de-maquinas-pesadas';
UPDATE courses SET imagem='assets/img/cursos/nr-20-inflamaveis-e-combustiveis.png' WHERE slug='nr-20-inflamaveis-e-combustiveis';
UPDATE courses SET imagem='assets/img/cursos/seguranca-no-trabalho.png' WHERE slug='seguranca-no-trabalho';
UPDATE courses SET imagem='assets/img/cursos/mecanica-basica.png' WHERE slug='mecanica-basica';

INSERT INTO courses
(titulo,slug,categoria,descricao,carga_horaria,preco,imagem,status,certificado)
SELECT 'Noções Básicas de NR35','nocoes-basicas-nr35','Geral',
'Formação introdutória sobre segurança para trabalho em altura e prevenção de riscos.',
20,0,'assets/img/cursos/nr35.png','active',1
WHERE NOT EXISTS (SELECT 1 FROM courses WHERE slug='nocoes-basicas-nr35');
