# HEAL — Capacitação Profissional

Sistema PHP/MySQL para gerenciamento e disponibilização de cursos online.

## Estrutura

- `htdocs/` — aplicação PHP para XAMPP/Apache.
- `htdocs/admin/` — painel administrativo.
- `htdocs/aluno/` — área do aluno.
- `htdocs/config/` — configuração e funções do sistema.
- `htdocs/database/` — schema e scripts SQL.
- `htdocs/uploads/` — diretórios para arquivos enviados pelo sistema.

## Instalação local

1. Instale o XAMPP com Apache, PHP e MySQL.
2. Copie o conteúdo de `htdocs/` para o diretório `htdocs` do XAMPP ou para uma subpasta do Apache.
3. Ligue Apache e MySQL.
4. Revise `htdocs/config/bootstrap.php` e configure e-mail, InfinitePay e demais integrações antes de usar em produção.
5. Abra a aplicação pelo navegador. O sistema cria/atualiza o banco conforme o schema configurado.

## Uploads de vídeo

Os vídeos enviados pelos cursos **não fazem parte deste repositório** para evitar arquivos grandes no GitHub. Os diretórios de upload permanecem na estrutura do projeto com `.gitkeep`.

Depois de clonar/instalar o projeto, os vídeos devem ser enviados pelo próprio sistema ou copiados para `htdocs/uploads/videos/` conforme a configuração da aplicação.

## Banco de dados

O arquivo principal está em `htdocs/database/schema.sql`. Há também scripts SQL de atualização para recursos específicos.

## Importante para produção

Não publique senhas SMTP, tokens, chaves de API ou credenciais reais no GitHub. Configure esses dados no ambiente de produção e mantenha-os fora do controle de versão.
