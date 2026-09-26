<?php
/** Funções puras: transformam as respostas da Viator no que as páginas mostram. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_preco_brl( $valor ) {
	if ( ! is_numeric( $valor ) || (float) $valor <= 0 ) {
		return '';
	}
	return 'R$ ' . number_format( (float) $valor, 2, ',', '.' );
}

function vtec_minutos( $m ) {
	$m = (int) $m;
	if ( $m >= 1440 && 0 === $m % 1440 ) {
		$d = intdiv( $m, 1440 );
		return $d . ( 1 === $d ? ' dia' : ' dias' );
	}
	$h = intdiv( $m, 60 );
	$r = $m % 60;
	if ( 0 === $h ) {
		return $r . ' min';
	}
	return $h . 'h' . ( $r ? sprintf( '%02d', $r ) : '' );
}

function vtec_duracao_texto( $d ) {
	if ( ! is_array( $d ) ) {
		return '';
	}
	if ( isset( $d['fixedDurationInMinutes'] ) ) {
		return vtec_minutos( $d['fixedDurationInMinutes'] );
	}
	if ( isset( $d['variableDurationFromMinutes'], $d['variableDurationToMinutes'] ) ) {
		return vtec_minutos( $d['variableDurationFromMinutes'] ) . ' a ' . vtec_minutos( $d['variableDurationToMinutes'] );
	}
	return isset( $d['unstructuredDuration'] ) ? (string) $d['unstructuredDuration'] : '';
}

/** Menor variante com largura >= $largura_min da imagem de capa (ou a maior, se nenhuma alcança). */
function vtec_imagem( $imagens, $largura_min ) {
	if ( ! is_array( $imagens ) || ! $imagens ) {
		return '';
	}
	$capa = $imagens[0];
	foreach ( $imagens as $i ) {
		if ( ! empty( $i['isCover'] ) ) {
			$capa = $i;
			break;
		}
	}
	return vtec_variante( $capa, $largura_min );
}

function vtec_variante( $imagem, $largura_min ) {
	$variantes = isset( $imagem['variants'] ) ? $imagem['variants'] : array();
	usort( $variantes, function ( $a, $b ) {
		return (int) $a['width'] - (int) $b['width'];
	} );
	foreach ( $variantes as $v ) {
		if ( (int) $v['width'] >= $largura_min ) {
			return (string) $v['url'];
		}
	}
	return $variantes ? (string) end( $variantes )['url'] : '';
}

function vtec_galeria( $imagens, $max = 10 ) {
	$urls = array();
	foreach ( is_array( $imagens ) ? $imagens : array() as $i ) {
		$u = vtec_variante( $i, 720 );
		if ( '' !== $u ) {
			$urls[] = $u;
		}
		if ( count( $urls ) >= $max ) {
			break;
		}
	}
	return $urls;
}

function vtec_url_produto( $codigo, $titulo ) {
	return home_url( '/passeios/p/' . $codigo . '-' . sanitize_title( $titulo ) . '/' );
}

function vtec_codigo_valido( $codigo ) {
	return (bool) preg_match( '/^[A-Za-z0-9_]{3,30}$/', (string) $codigo );
}

/** Nota e total de avaliações; a busca traz combinedAverageRating, o produto só as fontes. */
function vtec_nota( $reviews ) {
	if ( ! is_array( $reviews ) ) {
		return array( 'nota' => 0.0, 'total' => 0 );
	}
	if ( isset( $reviews['combinedAverageRating'] ) ) {
		return array( 'nota' => round( (float) $reviews['combinedAverageRating'], 1 ), 'total' => (int) ( isset( $reviews['totalReviews'] ) ? $reviews['totalReviews'] : 0 ) );
	}
	$total = 0;
	$soma  = 0.0;
	foreach ( isset( $reviews['sources'] ) ? $reviews['sources'] : array() as $f ) {
		$total += (int) $f['totalCount'];
		$soma  += (float) $f['averageRating'] * (int) $f['totalCount'];
	}
	return array( 'nota' => $total ? round( $soma / $total, 1 ) : 0.0, 'total' => $total );
}

function vtec_card( $p ) {
	$preco = isset( $p['pricing']['currency'], $p['pricing']['summary']['fromPrice'] ) && 'BRL' === $p['pricing']['currency']
		? vtec_preco_brl( $p['pricing']['summary']['fromPrice'] ) : '';
	$nota  = vtec_nota( isset( $p['reviews'] ) ? $p['reviews'] : null );
	return array(
		'codigo'              => (string) $p['productCode'],
		'titulo'              => (string) $p['title'],
		'url'                 => vtec_url_produto( $p['productCode'], $p['title'] ),
		'imagem'              => vtec_imagem( isset( $p['images'] ) ? $p['images'] : null, 480 ),
		'duracao'             => vtec_duracao_texto( isset( $p['duration'] ) ? $p['duration'] : null ),
		'preco'               => $preco,
		'cancelamento_gratis' => isset( $p['flags'] ) && in_array( 'FREE_CANCELLATION', (array) $p['flags'], true ),
		'nota'                => $nota['nota'],
		'total'               => $nota['total'],
	);
}

function vtec_textos_itens( $itens ) {
	$textos = array();
	foreach ( is_array( $itens ) ? $itens : array() as $i ) {
		foreach ( array( 'otherDescription', 'description', 'typeDescription' ) as $campo ) {
			if ( ! empty( $i[ $campo ] ) ) {
				$textos[] = (string) $i[ $campo ];
				break;
			}
		}
	}
	return $textos;
}

function vtec_produto_view( $p, $preco ) {
	$descricao  = isset( $p['description'] ) ? (string) $p['description'] : '';
	$paragrafos = array_values( array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", $descricao ) ) ) );
	$duracao    = isset( $p['itinerary']['duration'] ) ? $p['itinerary']['duration'] : null;
	$nota       = vtec_nota( isset( $p['reviews'] ) ? $p['reviews'] : null );
	return array(
		'codigo'       => (string) $p['productCode'],
		'titulo'       => (string) $p['title'],
		'paragrafos'   => $paragrafos,
		'galeria'      => vtec_galeria( isset( $p['images'] ) ? $p['images'] : null ),
		'duracao'      => vtec_duracao_texto( $duracao ),
		'preco'        => null === $preco ? '' : vtec_preco_brl( $preco ),
		'inclusoes'    => vtec_textos_itens( isset( $p['inclusions'] ) ? $p['inclusions'] : null ),
		'exclusoes'    => vtec_textos_itens( isset( $p['exclusions'] ) ? $p['exclusions'] : null ),
		'cancelamento' => isset( $p['cancellationPolicy']['description'] ) ? (string) $p['cancellationPolicy']['description'] : '',
		'encontro'     => isset( $p['logistics']['start'][0]['description'] ) ? (string) $p['logistics']['start'][0]['description'] : '',
		'informacoes'  => vtec_textos_itens( isset( $p['additionalInfo'] ) ? $p['additionalInfo'] : null ),
		'link'         => isset( $p['productUrl'] ) ? (string) $p['productUrl'] : '',
		'nota'         => $nota['nota'],
		'total'        => $nota['total'],
	);
}
