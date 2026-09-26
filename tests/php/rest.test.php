<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rest' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'por_pagina' => 3, 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );

vtec_guardar_nota( 'A1B', 4.8, 99 );
igual( array( 'A1B' => array( 'nota' => 4.8, 'total' => 99 ) ), vtec_rest_protegido( 'A1B,SEMNOTA,../x' ), 'só códigos com nota e válidos' );

resposta_falsa( 200, fixture( 'search' ) );
$r = vtec_rest_mais( 'las-vegas', 'avaliacao', 4 );
contem( 'vtec-card', $r['html'], 'cards da próxima página' );
igual( 7, $r['proximo'], 'próximo início' );
igual( 4, json_decode( $GLOBALS['vt_http_log'][0][1]['body'], true )['pagination']['start'], 'pede a partir do 4' );
igual( 'vtec_destino', vtec_rest_mais( 'xx', 'avaliacao', 4 )->get_error_code(), 'destino fora da lista' );

$busca = fixture( 'search' );
$busca['totalCount'] = 5;
resposta_falsa( 200, $busca );
igual( null, vtec_rest_mais( 'las-vegas', 'preco', 4 )['proximo'], 'última página não tem próximo' );
