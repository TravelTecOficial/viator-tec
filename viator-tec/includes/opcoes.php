<?php
/** Opções do plugin (Configurações › Viator Tec), guardadas na option vtec_opcoes. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_opcoes_padrao() {
	return array(
		'ambiente'       => 'sandbox',
		'chave_sandbox'  => '',
		'chave_producao' => '',
		'campanha'       => 'redeturistica-passeios',
		'por_pagina'     => 12,
		'destinos'       => array(),
		'modelos'        => array( 'card_destino' => 0, 'card_passeio' => 0, 'destinos' => 0, 'destino' => 0, 'produto' => 0 ),
	);
}

function vtec_opcoes() {
	$o            = array_merge( vtec_opcoes_padrao(), (array) get_option( 'vtec_opcoes', array() ) );
	$o['modelos'] = array_merge( vtec_opcoes_padrao()['modelos'], (array) $o['modelos'] );
	return $o;
}

/**
 * Limpa o que veio do formulário. Chave em branco mantém a que já estava salva
 * (o campo de senha nunca é preenchido de volta na tela).
 */
function vtec_sanitizar_opcoes( $entrada, $atual ) {
	$s = vtec_opcoes_padrao();

	$s['ambiente'] = ( isset( $entrada['ambiente'] ) && 'producao' === $entrada['ambiente'] ) ? 'producao' : 'sandbox';
	foreach ( array( 'chave_sandbox', 'chave_producao' ) as $campo ) {
		$nova        = isset( $entrada[ $campo ] ) ? trim( sanitize_text_field( $entrada[ $campo ] ) ) : '';
		$s[ $campo ] = '' !== $nova ? $nova : ( isset( $atual[ $campo ] ) ? $atual[ $campo ] : '' );
	}
	$campanha      = isset( $entrada['campanha'] ) ? trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $entrada['campanha'] ) ), '-' ) : '';
	$s['campanha'] = '' !== $campanha ? substr( $campanha, 0, 200 ) : $s['campanha'];
	$por_pagina      = isset( $entrada['por_pagina'] ) ? (int) $entrada['por_pagina'] : 12;
	$s['por_pagina'] = min( 50, max( 1, $por_pagina ) );

	$ids    = isset( $entrada['destino_id'] ) ? (array) $entrada['destino_id'] : array();
	$usados = array();
	foreach ( $ids as $i => $id ) {
		$id   = trim( (string) $id );
		$nome = isset( $entrada['destino_nome'][ $i ] ) ? trim( sanitize_text_field( $entrada['destino_nome'][ $i ] ) ) : '';
		if ( ! ctype_digit( $id ) || '' === $nome ) {
			continue;
		}
		$slug = sanitize_title( isset( $entrada['destino_slug'][ $i ] ) && '' !== trim( $entrada['destino_slug'][ $i ] ) ? $entrada['destino_slug'][ $i ] : $nome );
		$base = $slug;
		$n    = 2;
		while ( 'p' === $slug || isset( $usados[ $slug ] ) ) {
			$slug = $base . '-' . $n++;
		}
		$usados[ $slug ] = true;
		$s['destinos'][] = array(
			'id'   => (int) $id,
			'nome' => $nome,
			'slug' => $slug,
			'foto' => isset( $entrada['destino_foto'][ $i ] ) ? esc_url_raw( trim( $entrada['destino_foto'][ $i ] ) ) : '',
		);
	}
	$salvos = isset( $atual['modelos'] ) && is_array( $atual['modelos'] ) ? $atual['modelos'] : array();
	foreach ( array_keys( $s['modelos'] ) as $chave ) {
		if ( isset( $entrada[ 'modelo_' . $chave ] ) ) {
			$s['modelos'][ $chave ] = ctype_digit( (string) $entrada[ 'modelo_' . $chave ] ) ? (int) $entrada[ 'modelo_' . $chave ] : 0;
		} elseif ( isset( $salvos[ $chave ] ) ) {
			$s['modelos'][ $chave ] = (int) $salvos[ $chave ];
		}
	}
	return $s;
}

function vtec_destino_por_slug( $slug ) {
	foreach ( vtec_opcoes()['destinos'] as $d ) {
		if ( $d['slug'] === $slug ) {
			return $d;
		}
	}
	return null;
}

function vtec_chave_atual() {
	$o = vtec_opcoes();
	return 'producao' === $o['ambiente'] ? $o['chave_producao'] : $o['chave_sandbox'];
}

function vtec_base_url() {
	return 'producao' === vtec_opcoes()['ambiente'] ? 'https://api.viator.com/partner' : 'https://api.sandbox.viator.com/partner';
}
