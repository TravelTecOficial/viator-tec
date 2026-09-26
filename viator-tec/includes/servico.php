<?php
/** O que as páginas pedem à Viator, já com cache. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_guardar_nota( $codigo, $nota, $total ) {
	if ( $total > 0 ) {
		set_transient( 'vtec_nota_' . $codigo, array( 'nota' => (float) $nota, 'total' => (int) $total ), 12 * HOUR_IN_SECONDS );
	}
}

function vtec_ler_nota( $codigo ) {
	$n = get_transient( 'vtec_nota_' . $codigo );
	return is_array( $n ) ? $n : null;
}

function vtec_buscar( $destino_id, $ordem, $inicio, $qtd ) {
	$ordens = array(
		'avaliacao' => array( 'sort' => 'TRAVELER_RATING', 'order' => 'DESCENDING' ),
		'preco'     => array( 'sort' => 'PRICE', 'order' => 'ASCENDING' ),
	);
	$corpo = array(
		'filtering'  => array( 'destination' => (string) (int) $destino_id ),
		'sorting'    => isset( $ordens[ $ordem ] ) ? $ordens[ $ordem ] : $ordens['avaliacao'],
		'pagination' => array( 'start' => max( 1, (int) $inicio ), 'count' => min( 50, max( 1, (int) $qtd ) ) ),
		'currency'   => 'BRL',
	);
	$r = vtec_cache( 'busca|' . wp_json_encode( $corpo ), 12 * HOUR_IN_SECONDS, function () use ( $corpo ) {
		return vtec_api( 'POST', '/products/search', $corpo );
	} );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$cards = array_map( 'vtec_card', isset( $r['products'] ) ? $r['products'] : array() );
	foreach ( $cards as $c ) {
		vtec_guardar_nota( $c['codigo'], $c['nota'], $c['total'] );
	}
	return array( 'cards' => $cards, 'total' => (int) ( isset( $r['totalCount'] ) ? $r['totalCount'] : 0 ) );
}

function vtec_produto( $codigo ) {
	if ( ! vtec_codigo_valido( $codigo ) ) {
		return new WP_Error( 'vtec_codigo', 'Código de passeio inválido.' );
	}
	$campanha = vtec_opcoes()['campanha'];
	$p = vtec_cache( 'produto|' . $codigo . '|' . $campanha, 12 * HOUR_IN_SECONDS, function () use ( $codigo, $campanha ) {
		return vtec_api( 'GET', '/products/' . $codigo, null, array( 'campaign-value' => $campanha ) );
	} );
	if ( ! is_wp_error( $p ) ) {
		$n = vtec_nota( isset( $p['reviews'] ) ? $p['reviews'] : null );
		vtec_guardar_nota( $codigo, $n['nota'], $n['total'] );
	}
	return $p;
}

/** "A partir de" em BRL: o calendário vem na moeda do fornecedor, convertida pelo câmbio da Viator. */
function vtec_preco_a_partir( $codigo ) {
	$s = vtec_cache( 'agenda|' . $codigo, 12 * HOUR_IN_SECONDS, function () use ( $codigo ) {
		return vtec_api( 'GET', '/availability/schedules/' . $codigo );
	} );
	if ( is_wp_error( $s ) || ! isset( $s['summary']['fromPrice'], $s['currency'] ) ) {
		return null;
	}
	$valor = (float) $s['summary']['fromPrice'];
	if ( 'BRL' === $s['currency'] ) {
		return round( $valor, 2 );
	}
	$moeda = (string) $s['currency'];
	$c = vtec_cache( 'cambio|' . $moeda, 12 * HOUR_IN_SECONDS, function () use ( $moeda ) {
		return vtec_api( 'POST', '/exchange-rates', array( 'sourceCurrencies' => array( $moeda ), 'targetCurrencies' => array( 'BRL' ) ) );
	} );
	if ( is_wp_error( $c ) || empty( $c['rates'][0]['rate'] ) ) {
		return null;
	}
	return round( $valor * (float) $c['rates'][0]['rate'], 2 );
}

function vtec_destinos_viator() {
	$r = vtec_cache( 'destinos', 7 * DAY_IN_SECONDS, function () {
		return vtec_api( 'GET', '/destinations' );
	} );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$lista = array();
	foreach ( isset( $r['destinations'] ) ? $r['destinations'] : array() as $d ) {
		$lista[] = array(
			'id'   => (int) $d['destinationId'],
			'nome' => (string) $d['name'],
			'tipo' => (string) $d['type'],
			'pai'  => isset( $d['parentDestinationId'] ) ? (int) $d['parentDestinationId'] : 0,
		);
	}
	return $lista;
}

/** "Paris (França)": sobe pela hierarquia da Viator até achar o país. */
function vtec_rotulo_destino( $d, $por_id ) {
	if ( 'COUNTRY' === $d['tipo'] ) {
		return $d['nome'];
	}
	$pai   = isset( $d['pai'] ) ? $d['pai'] : 0;
	$voltas = 0;
	while ( $pai && isset( $por_id[ $pai ] ) && $voltas++ < 10 ) {
		if ( 'COUNTRY' === $por_id[ $pai ]['tipo'] ) {
			return $d['nome'] . ' (' . $por_id[ $pai ]['nome'] . ')';
		}
		$pai = isset( $por_id[ $pai ]['pai'] ) ? $por_id[ $pai ]['pai'] : 0;
	}
	return $d['nome'];
}

/** Busca do autocompletar: quem começa com o termo vem primeiro; cada item ganha rótulo e slug. */
function vtec_filtrar_destinos( $lista, $termo, $max = 20 ) {
	$termo = strtolower( remove_accents( trim( (string) $termo ) ) );
	if ( '' === $termo ) {
		return array();
	}
	$inicio = array();
	$meio   = array();
	foreach ( $lista as $d ) {
		$pos = strpos( strtolower( remove_accents( $d['nome'] ) ), $termo );
		if ( 0 === $pos ) {
			$inicio[] = $d;
		} elseif ( false !== $pos ) {
			$meio[] = $d;
		}
	}
	$por_id = array();
	foreach ( $lista as $d ) {
		$por_id[ $d['id'] ] = $d;
	}
	$achados = array();
	foreach ( array_slice( array_merge( $inicio, $meio ), 0, $max ) as $d ) {
		$d['rotulo'] = vtec_rotulo_destino( $d, $por_id );
		$d['slug']   = sanitize_title( $d['nome'] );
		$achados[]   = $d;
	}
	return $achados;
}
