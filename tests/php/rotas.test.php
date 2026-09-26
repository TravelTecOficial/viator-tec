<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto', 'grade', 'rotas' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );

vtec_rotas();
$regras = $GLOBALS['vt_regras'];
igual( 'index.php?vtec_pagina=destinos', $regras['^passeios/?$'], 'regra /passeios/' );
$regra_produto = '^passeios/p/([A-Za-z0-9_]+)(?:-[^/]*)?/?$';
ok( isset( $regras[ $regra_produto ] ), 'regra do produto' );
ok( 1 === preg_match( '#' . $regra_produto . '#', 'passeios/p/56549P1-passeio-a-ilha-mar/', $m ) && '56549P1' === $m[1], 'regex pega o código com qualquer slug' );
ok( 1 === preg_match( '#' . $regra_produto . '#', 'passeios/p/56549P1/' ), 'regex aceita sem slug' );
ok( isset( $regras['^passeios/([^/]+)/?$'] ), 'regra do destino' );
igual( array( '^passeios-sitemap\.xml$', '^passeios/?$', $regra_produto, '^passeios/([^/]+)/?$' ), array_keys( $regras ), 'sitemap, lista, produto e destino, nessa ordem' );

$r = vtec_resolver_pagina( 'destino', 'nao-existe', '', '' );
igual( 404, $r['status'], 'destino fora da lista = 404' );
igual( 404, vtec_resolver_pagina( 'produto', '', '../x', '' )['status'], 'código inválido = 404' );
resposta_falsa( 400, array( 'message' => 'Invalid product code: ZZZ999' ) );
igual( 404, vtec_resolver_pagina( 'produto', '', 'ZZZ999', '' )['status'], 'código inexistente na Viator = 404' );

resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_resolver_pagina( 'destino', 'las-vegas', '', 'preco' );
igual( 200, $r['status'], 'destino ok' );
contem( 'Passeios em Las Vegas', $r['html'], 'html do destino' );
igual( 'Passeios em Las Vegas', $r['titulo'], 'título SEO' );
igual( 'https://exemplo.test/passeios/las-vegas/', $r['canonica'], 'canônica sem ?ordem' );

resposta_falsa( 500, '{}' );
vtec_limpar_cache();
$r = vtec_resolver_pagina( 'destino', 'las-vegas', '', '' );
igual( 200, $r['status'], 'API fora sem cache: 200 com aviso' );
contem( 'indisponíveis no momento', $r['html'], 'mensagem de indisponível' );

resposta_falsa( 200, fixture( 'product' ) );
resposta_falsa( 200, fixture( 'schedules' ) );
resposta_falsa( 200, fixture( 'exchange' ) );
$r = vtec_resolver_pagina( 'produto', '', '56549P1', '' );
igual( 200, $r['status'], 'produto ok' );
contem( 'Reservar na Viator', $r['html'], 'html do produto' );
igual( 'https://exemplo.test/passeios/p/56549P1-vale-privado-do-fogo-caminhadas-e-aventura/', $r['canonica'], 'canônica do produto' );
ok( strlen( $r['descricao'] ) > 50 && strlen( $r['descricao'] ) <= 170, 'descrição SEO curta' );

$robots = vtec_robots( "User-agent: *\nDisallow: /wp-admin/\n" );
contem( 'Disallow: /wp-json/viator-tec/v1/protegido', $robots, 'robots bloqueia a REST protegida' );
contem( 'Disallow: /wp-content/plugins/viator-tec/assets/protegido.js', $robots, 'robots bloqueia o JS protegido' );

igual( '<link rel="canonical" href="https://exemplo.test/passeios/las-vegas/" />' . "\n", vtec_tag_canonica( 'https://exemplo.test/passeios/las-vegas/' ), 'tag canônica' );
igual( '', vtec_tag_canonica( '' ), 'sem canônica não imprime nada' );

update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'modelos' => array( 'destino' => 41, 'produto' => 42 ), 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );
vtec_limpar_cache();
resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_resolver_pagina( 'destino', 'las-vegas', '', 'preco' );
igual( 41, $r['modelo'], 'destino usa o modelo escolhido' );
igual( 'Las Vegas', $r['contexto']['destino']['nome'], 'contexto do destino' );
igual( 'preco', $r['contexto']['ordem'], 'contexto leva a ordem' );
$GLOBALS['vtec_renderizador'] = function ( $m, $css = false ) { $c = vtec_contexto(); return "[modelo $m:" . $c['destino']['nome'] . ( $css ? ':css' : '' ) . ']'; };
igual( '[modelo 41:Las Vegas:css]', vtec_render_pagina( $r ), 'página renderizada pelo modelo, com CSS' );
$GLOBALS['vtec_renderizador'] = function () { return ''; };
contem( 'Passeios em Las Vegas', vtec_render_pagina( $r ), 'modelo vazio: volta ao HTML da fase 1' );
$r['modelo'] = 0;
contem( 'Passeios em Las Vegas', vtec_render_pagina( $r ), 'sem modelo: HTML da fase 1' );
resposta_falsa( 200, fixture( 'product' ) );
resposta_falsa( 200, fixture( 'schedules' ) );
resposta_falsa( 200, fixture( 'exchange' ) );
$r = vtec_resolver_pagina( 'produto', '', '56549P1', '' );
igual( 42, $r['modelo'], 'produto usa o modelo escolhido' );
igual( 'R$ 954,87', $r['contexto']['produto']['preco'], 'contexto do produto com preço' );
