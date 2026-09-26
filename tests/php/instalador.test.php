<?php
require '/t/bootstrap.php';
define( 'VTEC_DIR', '/p/' );
foreach ( array( 'opcoes', 'instalador' ) as $f ) { require "/p/includes/$f.php"; }

igual( array( 'card_destino', 'card_passeio', 'destinos', 'destino', 'produto' ), vtec_modelos_chaves(), 'cards antes das páginas' );
$ids_vistos = array();
foreach ( vtec_modelos_chaves() as $chave ) {
	$m = vtec_ler_modelo( $chave );
	ok( is_array( $m ) && '' !== $m['titulo'] && in_array( $m['tipo'], array( 'page', 'section' ), true ), "$chave: modelo válido" );
	$json = json_encode( $m['content'] );
	contem( 'vtec-', $json, "$chave: usa campos Viator" );
	foreach ( array( 'post-custom-field', 'post-title', 'post-url', 'post-featured-image', 'loop-grid', 'theme-post-title' ) as $velho ) {
		nao_contem( $velho, $json, "$chave: sem $velho" );
	}
	preg_match_all( '/"id":"([^"]+)"/', $json, $mm );
	igual( count( $mm[1] ), count( array_unique( $mm[1] ) ), "$chave: ids únicos" );
}
contem( 'vtec-grade', json_encode( vtec_ler_modelo( 'destino' )['content'] ), 'destino usa a grade' );
contem( 'vtec-link-reserva', json_encode( vtec_ler_modelo( 'produto' )['content'] ), 'produto tem o link Reservar' );
contem( 'vtec-galeria', json_encode( vtec_ler_modelo( 'produto' )['content'] ), 'produto tem a galeria' );
igual( null, vtec_ler_modelo( 'nao-existe' ), 'modelo inexistente' );

$dados = vtec_preparar_modelo( vtec_ler_modelo( 'destino' ), array( 'card_passeio' => 123 ) );
contem( '"modelo":"123"', $dados, 'placeholder trocado pelo ID do card' );
nao_contem( '{{MODELO_', $dados, 'nenhum placeholder sobrando' );
ok( is_array( json_decode( $dados, true ) ), 'JSON válido' );

// Cards repetem na mesma página: foto como <img> (widget de imagem), nunca fundo dinâmico (o CSS seria o mesmo em todos).
foreach ( array( 'card_passeio', 'card_destino' ) as $chave ) {
	$json = json_encode( vtec_ler_modelo( $chave )['content'] );
	nao_contem( '"background_image"', $json, "$chave: sem fundo dinâmico" );
	contem( '"widgetType":"image"', $json, "$chave: foto como widget de imagem" );
}
contem( 'vtec-destino-foto', json_encode( vtec_ler_modelo( 'card_destino' )['content'] ), 'card de destino usa a foto do destino' );

// Atualizar modelos do plugin só se o dono nunca editou (data de modificação = data de criação).
ok( vtec_modelo_intocado( '2026-09-26 10:00:00', '2026-09-26 10:00:00' ), 'nunca editado' );
ok( ! vtec_modelo_intocado( '2026-09-26 10:00:00', '2026-09-26 11:30:00' ), 'editado no Elementor' );
