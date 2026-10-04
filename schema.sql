CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 senha VARCHAR(255) NOT NULL,
 role ENUM('student','admin') NOT NULL DEFAULT 'student',
 status ENUM('active','blocked') NOT NULL DEFAULT 'active',
 data_nascimento DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS remember_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS password_resets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS courses (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(180) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 categoria VARCHAR(100) NOT NULL DEFAULT 'Geral',
 descricao TEXT NULL,
 carga_horaria INT UNSIGNED NOT NULL DEFAULT 10,
 preco DECIMAL(10,2) NOT NULL DEFAULT 0,
 imagem VARCHAR(255) NULL,
 apresentacao_badge VARCHAR(100) NOT NULL DEFAULT 'CAPACITAÇÃO PROFISSIONAL',
 apresentacao_titulo VARCHAR(220) NULL,
 apresentacao_subtitulo VARCHAR(255) NULL,
 apresentacao_texto TEXT NULL,
 apresentacao_destaques TEXT NULL,
 apresentacao_publico TEXT NULL,
 apresentacao_requisitos TEXT NULL,
 apresentacao_imagem VARCHAR(255) NULL,
 cta_texto VARCHAR(100) NOT NULL DEFAULT 'Quero me matricular',
 cor_destaque VARCHAR(7) NOT NULL DEFAULT '#0878ff',
 cor_secundaria VARCHAR(7) NOT NULL DEFAULT '#ff1e2d',
 mostrar_destaques TINYINT(1) NOT NULL DEFAULT 1,
 mostrar_publico TINYINT(1) NOT NULL DEFAULT 1,
 mostrar_requisitos TINYINT(1) NOT NULL DEFAULT 1,
 mostrar_aulas TINYINT(1) NOT NULL DEFAULT 1,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 certificado TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS lessons (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 course_id INT UNSIGNED NOT NULL,
 titulo VARCHAR(180) NOT NULL,
 descricao TEXT NULL,
 conteudo LONGTEXT NULL,
 video_url VARCHAR(500) NULL,
 material_url VARCHAR(500) NULL,
 ordem INT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
 INDEX(course_id,ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS enrollments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 course_id INT UNSIGNED NOT NULL,
 status ENUM('pending','active','blocked','completed') NOT NULL DEFAULT 'pending',
 enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 paid_at DATETIME NULL,
 admin_notified_at DATETIME NULL,
 student_paid_notified_at DATETIME NULL,
 student_released_notified_at DATETIME NULL,
 completed_at DATETIME NULL,
 UNIQUE KEY user_course(user_id,course_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS lesson_progress (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 lesson_id INT UNSIGNED NOT NULL,
 completed TINYINT(1) NOT NULL DEFAULT 0,
 completed_at DATETIME NULL,
 UNIQUE KEY user_lesson(user_id,lesson_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 enrollment_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(40) NOT NULL DEFAULT 'infinitepay',
 external_id VARCHAR(190) NULL,
 order_nsu VARCHAR(190) NULL,
 transaction_nsu VARCHAR(190) NULL,
 invoice_slug VARCHAR(190) NULL,
 payment_method VARCHAR(40) NULL,
 receipt_url VARCHAR(500) NULL,
 status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
 amount DECIMAL(10,2) NOT NULL,
 raw_response LONGTEXT NULL,
 paid_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS certificates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 course_id INT UNSIGNED NOT NULL,
 enrollment_id BIGINT UNSIGNED NOT NULL,
 codigo VARCHAR(32) NOT NULL UNIQUE,
 issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 validade DATE NULL,
 status ENUM('active','revoked') NOT NULL DEFAULT 'active',
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
 FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS settings (
 `key` VARCHAR(80) PRIMARY KEY,
 `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO settings(`key`,`value`) VALUES('certificate_validity_days','365') ON DUPLICATE KEY UPDATE `key`=`key`;
INSERT INTO courses(titulo,slug,categoria,descricao,carga_horaria,preco,imagem,status,certificado) VALUES
('Operador de Máquinas Pesadas','operador-de-maquinas-pesadas','Segurança e Operação','Curso online com fundamentos de operação segura de máquinas pesadas.',40,0, 'assets/img/cursos/operador-de-maquinas-pesadas.png','active',1),
('NR 20 - Inflamáveis e Combustíveis','nr-20-inflamaveis-e-combustiveis','Normas Regulamentadoras','Formação introdutória sobre segurança com inflamáveis e combustíveis.',20,0, 'assets/img/cursos/nr20-inflamaveis-e-combustiveis.png','active',1),
('Segurança no Trabalho','seguranca-no-trabalho','Saúde e Segurança','Boas práticas de prevenção e segurança no ambiente profissional.',30,0, 'assets/img/cursos/seguranca-no-trabalho.png','active',1),
('Mecânica Básica','mecanica-basica','Manutenção','Noções fundamentais de manutenção e mecânica.',30,0, 'assets/img/cursos/mecanica-basica.png','active',1)
ON DUPLICATE KEY UPDATE titulo=VALUES(titulo);
INSERT INTO lessons(course_id,titulo,descricao,conteudo,ordem,status)
SELECT c.id,'Boas-vindas','Introdução ao curso','Apresentação do curso, objetivos, metodologia e orientações de estudo.',1,'active' FROM courses c WHERE c.slug='operador-de-maquinas-pesadas' AND NOT EXISTS (SELECT 1 FROM lessons l WHERE l.course_id=c.id);
INSERT INTO lessons(course_id,titulo,descricao,conteudo,ordem,status)
SELECT c.id,'Segurança e prevenção','Princípios básicos','Regras gerais de segurança, identificação de riscos e prevenção de acidentes.',2,'active' FROM courses c WHERE c.slug='operador-de-maquinas-pesadas' AND (SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id)<2;
