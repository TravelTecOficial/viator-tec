<?php
/**
 * Cache das respostas da Viator em transients. Cada valor tem uma cópia de reserva de 7 dias,
 * usada quando a API falha depois que o cache principal venceu. "Limpar cache" só troca a
 * geração: as chaves antigas deixam de ser lidas e vencem sozinhas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_cache_chave( $chave ) {
	return 'vtec_' . (int) get_option( 'vtec_cache_geracao', 1 ) . '_' . md5( $chave );
}

function vtec_cache( $chave, $ttl, $gerar ) {
	$k = vtec_cache_chave( $chave );
	$v = get_transient( $k );
	if ( false !== $v ) {
		return $v;
	}
	$novo = call_user_func( $gerar );
	if ( is_wp_error( $novo ) ) {
		$reserva = get_transient( $k . '_r' );
		return false !== $reserva ? $reserva : $novo;
	}
	set_transient( $k, $novo, $ttl );
	set_transient( $k . '_r', $novo, 7 * DAY_IN_SECONDS );
	return $novo;
}

function vtec_limpar_cache() {
	update_option( 'vtec_cache_geracao', (int) get_option( 'vtec_cache_geracao', 1 ) + 1, false );
}
