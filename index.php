<?php

require_once __DIR__ . '/config/bootstrap.php';

$u = current_user();

/* =========================================================
   CURSOS PUBLICADOS
   ========================================================= */

$cursos = db()
    ->query("
        SELECT *
        FROM courses
        WHERE status = 'active'
        ORDER BY id
    ")
    ->fetchAll();

/* =========================================================
   CONTAR CURSOS DO ALUNO
   ========================================================= */

$meusCursos = 0;
$totalCursos = count($cursos);

if ($u && $u['role'] === 'student') {

    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM enrollments
        WHERE user_id = ?
        AND status IN ('active', 'completed')
    ");

    $stmt->execute([$u['id']]);

    $meusCursos = (int) $stmt->fetchColumn();
}

?>

<!doctype html>

<html lang="pt-BR">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= e(APP_NAME) ?></title>

    <!-- CSS principal -->
    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <!-- =====================================================
         TEMA
         ===================================================== -->

    <script>
        try {

            document.documentElement.dataset.theme =
                localStorage.getItem('nettocursos-theme') || 'dark';

        } catch (error) {

            document.documentElement.dataset.theme = 'dark';

        }
    </script>

</head>

<body>


<!-- =========================================================
     BOTÃO MODO CLARO / ESCURO
     ========================================================= -->

<button
    class="theme-toggle"
    type="button"
    aria-label="Alternar modo de cor"
    onclick="toggleTheme()"
>

    <span class="theme-icon">☀️</span>

    <span class="theme-label">
        Modo claro
    </span>

</button>


<!-- =========================================================
     MENU LATERAL
     ========================================================= -->

<aside class="sidebar">


    <!-- LOGO -->
    <div class="brand">

        <a href="<?= e(url()) ?>">

            <!-- Logo modo escuro -->
            <img
                class="brand-logo theme-logo theme-logo-dark"
                src="<?= e(url('assets/img/heal-logo.png')) ?>"
                alt="HEAL"
            >

            <!-- Logo modo claro -->
            <img
                class="brand-logo theme-logo theme-logo-light"
                src="<?= e(url('assets/img/heal-logo-light.png')) ?>"
                alt="HEAL"
            >

        </a>



    </div>


    <!-- =====================================================
         PESQUISA
         ===================================================== -->

    <div class="search-box">

        <div class="search">

            <span class="search-icon">⌕</span>

            <input
                id="courseSearch"
                type="search"
                placeholder="Pesquisar cursos..."
                autocomplete="off"
                aria-label="Pesquisar cursos"
            >

            <button
                type="button"
                id="clearSearch"
                class="search-clear"
                aria-label="Limpar pesquisa"
                hidden
            >
                ×
            </button>

        </div>

        <div
            id="searchResults"
            class="search-results"
            hidden
        ></div>

    </div>


    <!-- =====================================================
         NAVEGAÇÃO
         ===================================================== -->

    <nav class="side-nav">


        <!-- Página Inicial -->
        <a
            class="active"
            href="#inicio"
        >

            <i>⌂</i>

            <span>
                Página Inicial
            </span>

        </a>


        <!-- Cursos -->
        <a href="#cursos">

            <i>▤</i>

            <span>
                Cursos
            </span>

        </a>


        <!-- =================================================
             MEUS CURSOS
             Aparece somente para alunos matriculados.
             Possui o mesmo estilo dos outros botões.
             ================================================= -->

        <?php if (
            $u &&
            $u['role'] === 'student' &&
            $meusCursos > 0
        ): ?>

            <a href="<?= e(url('aluno/area.php')) ?>">

                <i>🎓</i>

                <span>
                    Meus cursos
                </span>

            </a>

        <?php endif; ?>


        <!-- Categorias -->
        <a href="#categorias">

            <i>◈</i>

            <span>
                Categorias
            </span>

        </a>


        <!-- Verificar certificado -->
        <a href="<?= e(url('aluno/verificar.php')) ?>">

            <i>✓</i>

            <span>
                Verificar certificado
            </span>

        </a>

    </nav>


    <!-- =====================================================
         LOGIN / CONTA
         ===================================================== -->

    <div class="side-auth">


        <!-- =================================================
             ALUNO
             ================================================= -->

        <?php if ($u && $u['role'] === 'student'): ?>

            <div class="side-auth-title">

                <span>
                    MINHA CONTA
                </span>

                <small>
                    <?= e($u['nome']) ?>
                </small>

            </div>


            <!-- Área do aluno -->
            <a
                class="side-auth-btn primary"
                href="<?= e(url('aluno/area.php')) ?>"
            >

                <i>▣</i>

                <span>
                    Área do aluno
                </span>

            </a>


            <!-- Sair -->
            <a
                class="side-auth-btn"
                href="<?= e(url('logout.php')) ?>"
            >

                <i>↪</i>

                <span>
                    Sair
                </span>

            </a>


        <!-- =================================================
             ADMINISTRADOR
             ================================================= -->

        <?php elseif ($u && $u['role'] === 'admin'): ?>

            <div class="side-auth-title">

                <span>
                    ADMINISTRAÇÃO
                </span>

                <small>
                    Painel administrativo
                </small>

            </div>


            <!-- Painel -->
            <a
                class="side-auth-btn primary"
                href="<?= e(url('admin/index.php')) ?>"
            >

                <i>⚙</i>

                <span>
                    Painel
                </span>

            </a>


            <!-- Sair -->
            <a
                class="side-auth-btn"
                href="<?= e(url('logout.php')) ?>"
            >

                <i>↪</i>

                <span>
                    Sair
                </span>

            </a>


        <!-- =================================================
             VISITANTE
             ================================================= -->

        <?php else: ?>

            <div class="side-auth-title">

                <span>
                    ACESSO DO ALUNO
                </span>

                <small>
                    Entre ou crie sua conta
                </small>

            </div>


            <div class="side-auth-actions">


                <!-- Entrar -->
                <a
                    class="side-auth-btn primary"
                    href="<?= e(url('aluno/login.php')) ?>"
                >

                    <i>→</i>

                    <span>
                        Entrar
                    </span>

                </a>


                <!-- Cadastrar -->
                <a
                    class="side-auth-btn register"
                    href="<?= e(url('aluno/cadastro.php')) ?>"
                >

                    <i>＋</i>

                    <span>
                        Cadastrar
                    </span>

                </a>


            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         USUÁRIO
         ===================================================== -->

    <div class="side-bottom">

        <div class="user-menu">

            <div class="user">


                <!-- Avatar -->
                <div class="avatar">

                    <?= e(
                        $u
                            ? strtoupper(substr($u['nome'], 0, 2))
                            : 'EN'
                    ) ?>

                </div>


                <!-- Informações -->
                <div class="user-info">

                    <strong>
                        <?= e(
                            $u
                                ? $u['nome']
                                : 'Visitante'
                        ) ?>
                    </strong>


                    <small>

                        <?= e(
                            $u
                                ? (
                                    $u['role'] === 'admin'
                                        ? 'Administrador'
                                        : 'Aluno'
                                )
                                : 'Não conectado'
                        ) ?>

                    </small>

                </div>

            </div>

        </div>

    </div>

</aside>


<!-- =========================================================
     CONTEÚDO PRINCIPAL
     ========================================================= -->

<main
    class="content"
    id="inicio"
>


    <!-- =====================================================
         HERO
         ===================================================== -->

    <section class="hero">

        <div class="hero-overlay"></div>


        <div class="hero-copy">


            <!-- Logo escura -->
            <img
                class="hero-logo theme-logo theme-logo-dark"
                src="<?= e(url('assets/img/heal-logo.png')) ?>"
                alt="HEAL"
            >


            <!-- Logo clara -->
            <img
                class="hero-logo theme-logo theme-logo-light"
                src="<?= e(url('assets/img/heal-logo-light.png')) ?>"
                alt="HEAL"
            >


            <span class="eyebrow">
                CAPACITAÇÃO PROFISSIONAL
            </span>


            <h1>
                Qualifique-se.<br>
                Prepare-se para o futuro.
            </h1>


            <h2>
                Aprenda. Qualifique-se. Evolua.
            </h2>


            <p>
                Tenha acesso aos seus cursos,
                acompanhe seu progresso e
                conquiste sua certificação.
            </p>


            <div class="hero-actions">


                <!-- Explorar cursos -->
                <a
                    class="btn"
                    href="#cursos"
                >
                    Explorar cursos
                </a>


                <!-- Usuário não conectado -->
                <?php if (!$u): ?>

                    <a
                        class="btn ghost"
                        href="<?= e(url('aluno/cadastro.php')) ?>"
                    >
                        Criar cadastro
                    </a>


                <!-- Usuário conectado -->
                <?php else: ?>

                    <a
                        class="btn ghost"
                        href="<?= e(
                            url(
                                $u['role'] === 'admin'
                                    ? 'admin/index.php'
                                    : 'aluno/area.php'
                            )
                        ) ?>"
                    >
                        Minha área
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <div class="hero-student-badges" aria-label="Destaques da plataforma">
            <div><strong><?= $totalCursos ?></strong><span>cursos disponíveis</span></div>
            <div><strong>24/7</strong><span>estude quando quiser</span></div>
            <div><strong>100%</strong><span>online e organizado</span></div>
        </div>

    </section>

    <section class="student-welcome">
        <div class="student-welcome-inner">
            <div class="student-welcome-icon">🎓</div>
            <div>
                <span class="eyebrow">SUA JORNADA COMEÇA AQUI</span>
                <h2>Aprender ficou mais simples.</h2>
                <p>Escolha uma formação, acompanhe suas aulas e avance no seu ritmo.</p>
            </div>
            <a class="student-welcome-link" href="#cursos">Começar agora <span>→</span></a>
        </div>
    </section>

    <!-- =====================================================
         CATÁLOGO DE CURSOS
         ===================================================== -->

    <section
        class="page-section"
        id="cursos"
    >

        <div class="section-head">

            <div>

                <span class="eyebrow">
                    CATÁLOGO
                </span>

                <h2>
                    Explore nossos cursos
                </h2>

                <p class="section-note">
                    Escolha sua próxima formação e descubra tudo o que o curso oferece.
                </p>

            </div>

        </div>


        <div class="course-grid">


            <?php foreach ($cursos as $curso): ?>

                <a
                    class="course course-link"
                    href="<?= e(
                        url(
                            'aluno/curso.php?id=' .
                            $curso['id']
                        )
                    ) ?>"
                >


                    <!-- Imagem do curso -->
                    <div class="course-image">

                        <img
                            src="<?= e(url($curso['imagem'])) ?>"
                            alt="<?= e($curso['titulo']) ?>"
                        >

                        <span>
                            <?= e($curso['carga_horaria']) ?>h
                        </span>

                    </div>


                    <!-- Informações -->
                    <div class="course-body">

                        <small>
                            <?= e($curso['categoria']) ?>
                        </small>


                        <h3>
                            <?= e($curso['titulo']) ?>
                        </h3>


                        <p>
                            <?= e(
                                $curso['descricao']
                                    ?: 'Curso online com conteúdo organizado e certificado.'
                            ) ?>
                        </p>

                        <div class="course-student-meta">
                            <span>⏱ <?= e($curso['carga_horaria']) ?>h</span>
                            <span>🎓 <?= !empty($curso['certificado']) ? 'Certificado' : 'Formação online' ?></span>
                        </div>

                        <!-- Preço -->
                        <strong>

                            <?php if ((float) $curso['preco'] > 0): ?>

                                <?= money((float) $curso['preco']) ?>

                            <?php else: ?>

                                Gratuito

                            <?php endif; ?>

                        </strong>


                        <span class="course-cta">
                            Ver apresentação →
                        </span>

                    </div>

                </a>

            <?php endforeach; ?>


            <!-- Nenhum curso -->
            <?php if (!$cursos): ?>

                <div class="empty">

                    Nenhum curso publicado
                    no catálogo no momento.

                </div>

            <?php endif; ?>


        </div>

    </section>


    <section class="study-paths">
        <div class="study-paths-head">
            <div>
                <span class="eyebrow">APRENDA DO SEU JEITO</span>
                <h2>Uma experiência feita para estudantes</h2>
                <p>Organize sua rotina, acompanhe seu progresso e encontre rapidamente a formação que combina com seus objetivos.</p>
            </div>
        </div>
        <div class="study-path-grid">
            <article><span>01</span><div><h3>Escolha</h3><p>Encontre um curso no catálogo e veja a apresentação completa.</p></div></article>
            <article><span>02</span><div><h3>Estude</h3><p>Acesse suas aulas e materiais dentro da área do aluno.</p></div></article>
            <article><span>03</span><div><h3>Evolua</h3><p>Acompanhe seu avanço e conclua sua formação.</p></div></article>
        </div>
    </section>

    <!-- =====================================================
         CATEGORIAS
         ===================================================== -->

    <section
        class="page-section"
        id="categorias"
    >

        <div class="section-head">

            <div>

                <span class="eyebrow">
                    ENCONTRE SEU CAMINHO
                </span>

                <h2>
                    Categorias
                </h2>

            </div>

        </div>


        <div class="category-grid">


            <div>

                <span>⚙</span>

                <b>
                    Operação
                </b>

                <span>
                    Máquinas e equipamentos
                </span>

            </div>


            <div>

                <span>◉</span>

                <b>
                    Segurança
                </b>

                <span>
                    Normas e prevenção
                </span>

            </div>


            <div>

                <span>▣</span>

                <b>
                    Manutenção
                </b>

                <span>
                    Conhecimentos técnicos
                </span>

            </div>


        </div>

    </section>


    <!-- =====================================================
         CONTATO
         ===================================================== -->

    <section
        class="page-section contact-section"
        id="contato"
    >

        <div class="section-head">

            <div>

                <span class="eyebrow">
                    FALE CONOSCO
                </span>

                <h2>
                    Contato
                </h2>

                <p>
                    Precisa de ajuda com matrícula,
                    cursos ou certificados?
                    Entre em contato com a administração.
                </p>

            </div>

        </div>


        <div class="contact-grid">


            <!-- Atendimento -->
            <div class="contact-card">

                <span class="contact-icon">
                    ✉
                </span>

                <div>

                    <strong>
                        Atendimento
                    </strong>

                    <p>
                        Configure o e-mail de atendimento
                        em
                        <code>
                            config/bootstrap.php
                        </code>
                        para receber as mensagens
                        da plataforma.
                    </p>

                </div>

            </div>


            <!-- Certificados -->
            <div class="contact-card">

                <span class="contact-icon">
                    ✓
                </span>

                <div>

                    <strong>
                        Certificados
                    </strong>

                    <p>
                        Já possui um certificado?
                        Consulte a autenticidade
                        diretamente na plataforma.
                    </p>


                    <a
                        class="mini-btn"
                        href="<?= e(url('aluno/verificar.php')) ?>"
                    >
                        Verificar certificado
                    </a>

                </div>

            </div>


        </div>

    </section>


    <!-- =====================================================
         RODAPÉ
         ===================================================== -->

    <footer class="site-footer">

        <div>

            <strong>
                <?= e(APP_NAME) ?>
            </strong>

            <span>
                Capacitação profissional online.
            </span>

        </div>


        <a href="#inicio">
            Voltar ao topo ↑
        </a>

    </footer>

</main>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script>

/* =========================================================
   ATUALIZAR BOTÃO DO TEMA
   ========================================================= */

function updateThemeButton() {

    const isLight =
        document.documentElement.dataset.theme === 'light';

    const icon =
        document.querySelector('.theme-icon');

    const label =
        document.querySelector('.theme-label');


    if (icon) {

        icon.textContent =
            isLight ? '🌙' : '☀️';

    }


    if (label) {

        label.textContent =
            isLight
                ? 'Modo escuro'
                : 'Modo claro';

    }

}


/* =========================================================
   ALTERAR TEMA
   ========================================================= */

function toggleTheme() {

    const currentTheme =
        document.documentElement.dataset.theme;

    const nextTheme =
        currentTheme === 'light'
            ? 'dark'
            : 'light';


    document.documentElement.dataset.theme =
        nextTheme;


    try {

        localStorage.setItem(
            'nettocursos-theme',
            nextTheme
        );

    } catch (error) {

        console.warn(
            'Não foi possível salvar o tema.',
            error
        );

    }


    updateThemeButton();

}


/* =========================================================
   PESQUISA PROFISSIONAL DE CURSOS
   ========================================================= */

const searchInput = document.getElementById('courseSearch');
const searchResults = document.getElementById('searchResults');
const clearSearch = document.getElementById('clearSearch');

const courses = <?= json_encode(
    array_map(
        function ($curso) {
            return [
                'id' => (int) $curso['id'],
                'titulo' => $curso['titulo'] ?? '',
                'categoria' => $curso['categoria'] ?? '',
                'descricao' => $curso['descricao'] ?? '',
                'imagem' => $curso['imagem'] ?? '',
                'preco' => (float) ($curso['preco'] ?? 0),
            ];
        },
        $cursos
    ),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

function escapeSearchHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatSearchPrice(price) {
    if (Number(price) <= 0) return 'Gratuito';

    return Number(price).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
}

function showSearchResults(value) {
    const term = String(value || '').toLowerCase().trim();

    if (!term) {
        searchResults.hidden = true;
        searchResults.innerHTML = '';
        clearSearch.hidden = true;
        return;
    }

    clearSearch.hidden = false;

    const results = courses.filter(function (course) {
        const title = String(course.titulo).toLowerCase();
        const category = String(course.categoria).toLowerCase();
        const description = String(course.descricao).toLowerCase();

        return title.includes(term) ||
               category.includes(term) ||
               description.includes(term);
    }).slice(0, 8);

    searchResults.hidden = false;

    if (!results.length) {
        searchResults.innerHTML = `
            <div class="search-no-results">
                <span class="search-no-results-icon">🔎</span>
                <strong>Nenhum curso encontrado</strong>
                <span>Tente outro nome, categoria ou palavra-chave.</span>
            </div>
        `;
        return;
    }

    let html = `
        <div class="search-results-header">
            ${results.length === 1 ? '1 curso encontrado' : results.length + ' cursos encontrados'}
        </div>
    `;

    results.forEach(function (course) {
        const courseUrl = <?= json_encode(url('aluno/curso.php')) ?> + '?id=' + encodeURIComponent(course.id);
        const imageUrl = <?= json_encode(url('')) ?> + String(course.imagem || '');

        html += `
            <a class="search-result" href="${courseUrl}">
                <div class="search-result-image">
                    <img src="${escapeSearchHtml(imageUrl)}" alt="${escapeSearchHtml(course.titulo)}">
                </div>
                <div class="search-result-info">
                    <span class="search-result-title">${escapeSearchHtml(course.titulo)}</span>
                    <div class="search-result-meta">
                        <span class="search-result-category">🏷️ ${escapeSearchHtml(course.categoria || 'Curso')}</span>
                        <span class="search-result-separator">•</span>
                        <span class="search-result-price">${formatSearchPrice(course.preco)}</span>
                    </div>
                </div>
                <span class="search-result-arrow">→</span>
            </a>
        `;
    });

    searchResults.innerHTML = html;
}

if (searchInput) {
    searchInput.addEventListener('input', function () {
        showSearchResults(this.value);
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            searchResults.hidden = true;
            this.blur();
        }
    });
}

if (clearSearch) {
    clearSearch.addEventListener('click', function () {
        searchInput.value = '';
        searchResults.innerHTML = '';
        searchResults.hidden = true;
        clearSearch.hidden = true;
        searchInput.focus();
    });
}

document.addEventListener('click', function (event) {
    const searchBox = document.querySelector('.search-box');

    if (searchBox && !searchBox.contains(event.target)) {
        searchResults.hidden = true;
    }
});

/* =========================================================
   INICIALIZAÇÃO
   ========================================================= */

updateThemeButton();

</script>


</body>

</html>