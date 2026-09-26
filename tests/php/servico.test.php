<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K' ) );

resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_buscar( 684, 'preco', 1, 3 );
igual( 1309, $r['total'], 'total da busca' );
igual( 3, count( $r['cards'] ), 'três cards' );
$corpo = json_decode( $GLOBALS['vt_http_log'][0][1]['body'], true );
igual( array( 'sort' => 'PRICE', 'order' => 'ASCENDING' ), $corpo['sorting'], 'ordem por preço' );
igual( '684', $corpo['filtering']['destination'], 'destino como texto' );
igual( 'BRL', $corpo['currency'], 'moeda BRL' );
igual( array( 'nota' => 5.0, 'total' => 207 ), vtec_ler_nota( '56549P1' ), 'nota guardada para o JS protegido' );

vtec_buscar( 684, 'preco', 1, 3 );
igual( 1, count( $GLOBALS['vt_http_log'] ), 'segunda busca igual vem do cache' );
resposta_falsa( 200, fixture( 'search' ) );
vtec_buscar( 684, 'qualquer', 1, 3 );
igual( array( 'sort' => 'TRAVELER_RATING', 'order' => 'DESCENDING' ), json_decode( $GLOBALS['vt_http_log'][1][1]['body'], true )['sorting'], 'ordem inválida vira avaliação' );

ok( is_wp_error( vtec_produto( '../etc' ) ), 'código inválido nem chama a API' );
resposta_falsa( 200, fixture( 'product' ) );
$p = vtec_produto( '56549P1' );
igual( '56549P1', $p['productCode'], 'produto' );
contem( '/products/56549P1?campaign-value=redeturistica-passeios', end( $GLOBALS['vt_http_log'] )[0], 'campanha na chamada do produto' );

resposta_falsa( 200, fixture( 'schedules' ) );   // USD 180
resposta_falsa( 200, fixture( 'exchange' ) );    // 5.30484035
igual( 954.87, vtec_preco_a_partir( '56549P1' ), 'preço convertido de USD para BRL' );
resposta_falsa( 500, '{}' );
igual( null, vtec_preco_a_partir( 'OUTRO1' ), 'erro no preço devolve null' );

resposta_falsa( 200, fixture( 'destinations' ) );
$d = vtec_destinos_viator();
ok( count( $d ) >= 3 && isset( $d[0]['id'], $d[0]['nome'], $d[0]['tipo'] ), 'destinos da Viator' );
igual( array( 'Las Vegas' ), array_column( vtec_filtrar_destinos( $d, 'vegas' ), 'nome' ), 'filtro por nome, sem maiúsculas' );
igual( array(), vtec_filtrar_destinos( $d, '' ), 'termo vazio não lista nada' );
