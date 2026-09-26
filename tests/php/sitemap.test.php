<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'sitemap' ) as $f ) { require "/p/includes/$f.php"; }
update_option( 'vtec_opcoes', array( 'chave_sandbox' => 'K', 'por_pagina' => 3, 'destinos' => array( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => '' ) ) ) );
resposta_falsa( 200, fixture( 'search' ) );
$u = vtec_urls_sitemap();
igual( 'https://exemplo.test/passeios/', $u[0], 'lista de destinos' );
igual( 'https://exemplo.test/passeios/las-vegas/', $u[1], 'destino' );
igual( 5, count( $u ), 'lista + destino + 3 passeios' );
$x = vtec_xml_sitemap( array( 'https://exemplo.test/a?b=1&c=2' ) );
contem( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $x, 'urlset' );
contem( '<loc>https://exemplo.test/a?b=1&amp;c=2</loc>', $x, '& escapado para XML' );
contem( '<sitemap><loc>https://exemplo.test/passeios-sitemap.xml</loc>', vtec_indice_sitemap( '' ), 'entrada no índice do Rank Math' );
