<?php
/**
 * Rotas REST públicas. /protegido entrega nota e total de avaliações (conteúdo que a Viator
 * proíbe indexar) e está em Disallow no robots.txt; /mais entrega a próxima página de cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_rest_protegido( $codigos_csv ) {
	$saida = array();
	foreach ( array_slice( explode( ',', (string) $codigos_csv ), 0, 60 ) as $c ) {
		$c = trim( $c );
		if ( vtec_codigo_valido( $c ) ) {
			$n = vtec_ler_nota( $c );
			if ( $n ) {
				$saida[ $c ] = $n;
			}
		}
	}
	return $saida;
}

function vtec_rest_mais( $slug, $ordem, $inicio, $modelo = 0, $fonte = 'destino', $qtd = 0 ) {
	$qtd = $qtd ? min( 50, max( 1, (int) $qtd ) ) : vtec_opcoes()['por_pagina'];
	if ( 'destinos' !== $fonte && ! vtec_destino_por_slug( $slug ) ) {
		return new WP_Error( 'vtec_destino', 'Destino não encontrado.', array( 'status' => 404 ) );
	}
	$r = vtec_itens_grade( 'destinos' === $fonte ? 'destinos' : 'destino', $slug, null, $ordem, $inicio, $qtd );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$html = '';
	foreach ( $r['itens'] as $ctx ) {
		$html .= '<div class="vtec-grade-item">' . vtec_render_card( (int) $modelo, $ctx ) . '</div>';
	}
	$proximo = $inicio + count( $r['itens'] );
	return array( 'html' => $html, 'proximo' => $r['itens'] && $proximo <= $r['total'] ? $proximo : null );
}

/** Autocompletar do painel (só administradores). */
function vtec_rest_destinos( $termo ) {
	if ( strlen( trim( (string) $termo ) ) < 2 ) {
		return array();
	}
	$lista = vtec_destinos_viator();
	if ( is_wp_error( $lista ) ) {
		return $lista;
	}
	$saida = array();
	foreach ( vtec_filtrar_destinos( $lista, $termo, 15 ) as $d ) {
		$saida[] = array( 'id' => $d['id'], 'nome' => $d['nome'], 'rotulo' => $d['rotulo'], 'slug' => $d['slug'] );
	}
	return $saida;
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'viator-tec/v1', '/protegido', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			return rest_ensure_response( (object) vtec_rest_protegido( (string) $req->get_param( 'codigos' ) ) );
		},
	) );
	register_rest_route( 'viator-tec/v1', '/destinos', array(
		'methods'             => 'GET',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'callback'            => function ( $req ) {
			return rest_ensure_response( vtec_rest_destinos( sanitize_text_field( (string) $req->get_param( 'termo' ) ) ) );
		},
	) );
	register_rest_route( 'viator-tec/v1', '/mais', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			return rest_ensure_response( vtec_rest_mais(
				sanitize_title( (string) $req->get_param( 'destino' ) ),
				sanitize_key( (string) $req->get_param( 'ordem' ) ),
				max( 1, (int) $req->get_param( 'inicio' ) ),
				(int) $req->get_param( 'modelo' ),
				'destinos' === $req->get_param( 'fonte' ) ? 'destinos' : 'destino',
				(int) $req->get_param( 'qtd' )
			) );
		},
	) );
} );
