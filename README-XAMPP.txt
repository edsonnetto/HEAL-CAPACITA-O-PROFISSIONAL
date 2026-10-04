NETTOCURSOS - PLATAFORMA EAD PARA XAMPP

1) Coloque a pasta NettoCursos-XAMPP em C:\xampp\htdocs\
2) Ligue Apache e MySQL no XAMPP.
3) Abra http://localhost/NettoCursos-XAMPP/
4) O banco nettocursos é criado automaticamente na primeira visita (usuário root sem senha, padrão do XAMPP).
5) Admin inicial: admin@localhost / admin123. Troque a senha depois.

RECURSOS IMPLEMENTADOS
- MySQL e criação automática do banco/tabelas
- Cadastro real de alunos
- Login com senha hash e sessão
- Login persistente por 30 dias
- Área do aluno
- Cursos e aulas
- Controle de progresso por aula
- Painel administrativo
- Certificados automáticos ao concluir curso
- Código único + QR Code de verificação
- Consulta pública de certificados
- Pagamentos preparados para Mercado Pago
- Webhook de pagamento
- Recuperação de senha por token
- Segurança básica: PDO prepared statements, CSRF, sessão segura, permissões por função e proteção de pastas.

PAGAMENTOS REAIS
Edite config/bootstrap.php e preencha MERCADOPAGO_ACCESS_TOKEN com seu Access Token. Depois publique a URL de webhook em:
http(s)://SEU-DOMINIO/NettoCursos-XAMPP/pagamento/webhook.php

RECUPERAÇÃO DE SENHA
Em produção, configure SMTP/mail do servidor. No XAMPP local, se o e-mail não estiver configurado, o link de redefinição aparece apenas em ambiente APP_ENV=local para teste.

CERTIFICADO
A emissão usa uma página de certificado pronta para impressão/"Salvar como PDF" do navegador e QR Code de consulta pública. Para gerar PDF 100% automático no servidor, instale uma biblioteca como Dompdf/TCPDF no projeto.
