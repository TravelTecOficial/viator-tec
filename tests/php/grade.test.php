<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto', 'grade' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'por_pagina' => 3, 'destinos' => array(
	array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => 'https://x.test/lv.jpg' ),
	array( 'id' => 712, 'nome' => 'Rio de Janeiro', 'slug' => 'rio-de-janeiro', 'foto' => 'https://x.test/rj.jpg' ),
) ) );

// renderizador falso: devolve o título do contexto no momento do render
$GLOBALS['vtec_renderizador'] = function ( $modelo ) {
	$c = vtec_contexto();
	return '[m' . $modelo . ':' . ( $c['produto'] ? $c['produto']['titulo'] : $c['destino']['nome'] ) . ']';
};

$r = vtec_itens_grade( 'destinos', '', null, '', 1, 10 );
igual( 2, $r['total'], 'destinos: total' );
igual( 'Rio de Janeiro', $r['itens'][1]['destino']['nome'], 'destinos: contexto de destino' );
igual( 1, count( vtec_itens_grade( 'destinos', '', null, '', 2, 10 )['itens'] ), 'destinos: paginação' );

resposta_falsa( 200, fixture( 'search' ) );
$pagina = array( 'destino' => vtec_destino_por_slug( 'las-vegas' ), 'produto' => null, 'ordem' => 'preco' );
$r = vtec_itens_grade( 'destino_atual', '', $pagina, 'preco', 1, 3 );
igual( 1309, $r['total'], 'destino atual: total da Viator' );
igual( '56549P1', $r['itens'][0]['produto']['codigo'], 'destino atual: contexto de passeio' );
igual( 'Las Vegas', $r['itens'][0]['destino']['nome'], 'passeio leva o destino junto' );
igual( array(), vtec_itens_grade( 'destino_atual', '', null, 'preco', 1, 3 )['itens'], 'sem destino atual: vazio' );
igual( array(), vtec_itens_grade( 'destino', 'xx', null, 'preco', 1, 3 )['itens'], 'destino fora da lista: vazio' );

vtec_definir_contexto( $pagina );
$h = vtec_html_grade( $r['itens'], $r['total'], 9, array( 'fonte' => 'destino', 'destino' => 'las-vegas', 'ordem' => 'preco', 'qtd' => 3, 'mais_texto' => 'Carregar <mais>' ) );
contem( '<div class="vtec-grade-el">', $h, 'grade' );
igual( 3, substr_count( $h, '<div class="vtec-grade-item">' ), 'um item por passeio' );
contem( '[m9:Vale Privado do Fogo Caminhadas e Aventura]', $h, 'card renderizado com o contexto do passeio' );
igual( $pagina, vtec_contexto(), 'contexto da página restaurado depois dos cards' );
contem( 'data-inicio="4"', $h, 'carregar mais começa depois dos 3' );
contem( 'data-modelo="9"', $h, 'botão sabe o modelo' );
contem( 'data-fonte="destino"', $h, 'botão sabe a fonte' );
contem( 'Carregar &lt;mais&gt;', $h, 'texto do botão escapado' );
nao_contem( 'vtec-mais', vtec_html_grade( $r['itens'], 3, 9, array( 'fonte' => 'destino', 'destino' => 'x', 'ordem' => 'preco', 'qtd' => 3, 'mais_texto' => 'x' ) ), 'sem botão quando cabe tudo' );
contem( 'Nenhum passeio', vtec_html_grade( array(), 0, 9, array( 'fonte' => 'destino', 'destino' => 'x', 'ordem' => 'preco', 'qtd' => 3, 'mais_texto' => 'x' ) ), 'grade vazia' );

// sem modelo (ou modelo que não renderiza nada) usa o card padrão da fase 1
contem( 'vtec-card', vtec_render_card( 0, $r['itens'][0] ), 'card padrão de passeio' );
$GLOBALS['vtec_renderizador'] = function () { return '   '; };
contem( 'vtec-card', vtec_render_card( 5, $r['itens'][0] ), 'modelo vazio cai no card padrão' );
contem( 'vtec-destino', vtec_render_card( 0, array( 'destino' => vtec_destino_por_slug( 'rio-de-janeiro' ), 'produto' => null ) ), 'card padrão de destino' );

$o = vtec_html_ordem( vtec_destino_por_slug( 'las-vegas' ), 'preco' );
contem( 'href="https://exemplo.test/passeios/las-vegas/?ordem=preco" aria-current="true"', $o, 'ordem atual marcada' );

// widget: fonte destino_atual com ordem da URL quando "mostrar ordem" está ligado
$GLOBALS['vtec_renderizador'] = function ( $m ) { return '[card]'; };
resposta_falsa( 200, fixture( 'search' ) );
$w = vtec_render_grade( array( 'fonte' => 'destino_atual', 'modelo' => 3, 'quantidade' => 3, 'ordem' => 'avaliacao', 'mostrar_ordem' => 'yes', 'mais_texto' => 'Mais' ), $pagina );
contem( 'vtec-ordem', $w, 'widget mostra os botões de ordem' );
igual( 'PRICE', json_decode( end( $GLOBALS['vt_http_log'] )[1]['body'], true )['sorting']['sort'], 'ordem da URL vence a do widget' );
contem( '[card]', $w, 'widget usa o modelo' );
