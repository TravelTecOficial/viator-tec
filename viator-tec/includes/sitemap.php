<?php
/** /passeios-sitemap.xml: lista de destinos, cada destino e os passeios da 1ª página. Entra no índice do Rank Math. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_urls_sitemap() {
	$o    = vtec_opcoes();
	$urls = array( home_url( '/passeios/' ) );
	foreach ( $o['destinos'] as $d ) {
		$urls[] = home_url( '/passeios/' . $d['slug'] . '/' );
		$r      = vtec_buscar( $d['id'], 'avaliacao', 1, $o['por_pagina'] );
		if ( ! is_wp_error( $r ) ) {
			foreach ( $r['cards'] as $c ) {
				$urls[] = $c['url'];
			}
		}
	}
	return array_values( array_unique( $urls ) );
}

function vtec_xml_sitemap( $urls ) {
	$x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
	foreach ( $urls as $u ) {
		$x .= '<url><loc>' . htmlspecialchars( $u, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '</loc></url>';
	}
	return $x . '</urlset>';
}

function vtec_indice_sitemap( $xml ) {
	return $xml . '<sitemap><loc>' . htmlspecialchars( home_url( '/passeios-sitemap.xml' ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '</loc></sitemap>';
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

add_filter( 'rank_math/sitemap/index', 'vtec_indice_sitemap', 20 );
