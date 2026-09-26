<?php
require '/t/bootstrap.php';
require '/p/viator-tec.php';
ok( defined( 'VTEC_VERSION' ), 'VTEC_VERSION definida' );
contem( 'Plugin Name: Viator Tec', file_get_contents( '/p/viator-tec.php' ), 'cabeçalho do plugin' );
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rotas', 'rest', 'admin', 'atualizador' ) as $f ) {
	ok( file_exists( "/p/includes/$f.php" ), "includes/$f.php existe" );
}
$at = file_get_contents( '/p/includes/atualizador.php' );
contem( "'TravelTecOficial/viator-tec'", $at, 'atualizador aponta para o repositório certo' );
nao_contem( 'roteiros', strtolower( $at ), 'nada do Voucher Tec sobrou no atualizador' );
nao_contem( 'TT_', $at, 'sem constantes TT_' );
ok( vtec_forcar_consulta( array( 'force-check' => '1' ) ), '"Verificar novamente" força consultar o GitHub' );
ok( ! vtec_forcar_consulta( array() ), 'sem force-check usa o cache' );
