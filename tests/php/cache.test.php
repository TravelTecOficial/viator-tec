<?php
require '/t/bootstrap.php';
require '/p/includes/cache.php';

$chamadas = 0;
$gerar = function () use ( &$chamadas ) { $chamadas++; return array( 'n' => $chamadas ); };
igual( array( 'n' => 1 ), vtec_cache( 'a', 3600, $gerar ), 'gera na primeira vez' );
igual( array( 'n' => 1 ), vtec_cache( 'a', 3600, $gerar ), 'usa o cache na segunda' );
igual( 1, $chamadas, 'gerou uma vez só' );

// cache principal venceu, API falhou -> usa a reserva
foreach ( array_keys( $GLOBALS['vt_transients'] ) as $k ) {
	if ( '_r' !== substr( $k, -2 ) ) { delete_transient( $k ); }
}
igual( array( 'n' => 1 ), vtec_cache( 'a', 3600, function () { return new WP_Error( 'vtec_http_500', 'fora' ); } ), 'erro usa a reserva' );

// sem reserva -> devolve o erro e não guarda nada
$r = vtec_cache( 'b', 3600, function () { return new WP_Error( 'vtec_http_500', 'fora' ); } );
ok( is_wp_error( $r ), 'sem reserva devolve o erro' );

vtec_limpar_cache();
igual( array( 'n' => 2 ), vtec_cache( 'a', 3600, $gerar ), 'limpar cache força gerar de novo' );
