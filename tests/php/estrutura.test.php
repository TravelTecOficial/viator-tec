<?php
require '/t/bootstrap.php';
require '/p/viator-tec.php';
ok( defined( 'VTEC_VERSION' ), 'VTEC_VERSION definida' );
contem( 'Plugin Name: Viator Tec', file_get_contents( '/p/viator-tec.php' ), 'cabeçalho do plugin' );
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rotas', 'rest', 'admin', 'atualizador' ) as $f ) {
	ok( file_exists( "/p/includes/$f.php" ), "includes/$f.php existe" );
}
