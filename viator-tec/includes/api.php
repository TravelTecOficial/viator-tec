<?php
/** Cliente HTTP da Viator Partner API v2. Nunca expõe a chave em mensagens. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'vtec_dormir' ) ) {
	function vtec_dormir( $segundos ) {
		sleep( $segundos );
	}
}

function vtec_api( $metodo, $caminho, $corpo = null, $query = array() ) {
	$chave = vtec_chave_atual();
	if ( '' === $chave ) {
		return new WP_Error( 'vtec_sem_chave', 'Chave da Viator não configurada.' );
	}
	$url = vtec_base_url() . $caminho . ( $query ? '?' . http_build_query( $query ) : '' );
	$args = array(
		'method'  => $metodo,
		'timeout' => 20,
		'headers' => array(
			'exp-api-key'     => $chave,
			'Accept'          => 'application/json;version=2.0',
			'Accept-Language' => 'pt-BR',
			'Content-Type'    => 'application/json',
		),
	);
	if ( null !== $corpo ) {
		$args['body'] = wp_json_encode( $corpo );
	}

	for ( $tentativa = 1; $tentativa <= 2; $tentativa++ ) {
		$r = wp_remote_request( $url, $args );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'vtec_rede', 'Sem resposta da Viator: ' . $r->get_error_message() );
		}
		$codigo = (int) wp_remote_retrieve_response_code( $r );
		if ( 429 === $codigo && 1 === $tentativa ) {
			// Limite da janela de 10 s: espera o que a Viator pedir (no máximo 5 s) e tenta uma vez.
			vtec_dormir( min( 5, max( 1, (int) wp_remote_retrieve_header( $r, 'retry-after' ) ) ) );
			continue;
		}
		break;
	}

	$dados = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $codigo < 200 || $codigo >= 300 ) {
		$msg = is_array( $dados ) && isset( $dados['message'] ) ? (string) $dados['message'] : 'HTTP ' . $codigo;
		return new WP_Error( 'vtec_http_' . $codigo, $msg, array( 'status' => $codigo ) );
	}
	if ( ! is_array( $dados ) ) {
		return new WP_Error( 'vtec_json', 'Resposta inválida da Viator.' );
	}
	return $dados;
}
