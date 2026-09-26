<?php
/** Widget "Viator – Grade": busca os itens, define o contexto de cada um e renderiza o modelo de card. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_destinos_com_foto() {
	$lista = array();
	foreach ( vtec_opcoes()['destinos'] as $d ) {
		if ( '' === $d['foto'] ) {
			$r         = vtec_buscar( $d['id'], 'avaliacao', 1, 1 );
			$d['foto'] = ! is_wp_error( $r ) && $r['cards'] ? $r['cards'][0]['imagem'] : '';
		}
		$lista[] = $d;
	}
	return $lista;
}

function vtec_itens_grade( $fonte, $slug, $ctx, $ordem, $inicio, $qtd ) {
	$inicio = max( 1, (int) $inicio );
	$qtd    = min( 50, max( 1, (int) $qtd ) );
	if ( 'destinos' === $fonte ) {
		$todos = vtec_destinos_com_foto();
		$itens = array();
		foreach ( array_slice( $todos, $inicio - 1, $qtd ) as $d ) {
			$itens[] = array( 'destino' => $d, 'produto' => null );
		}
		return array( 'itens' => $itens, 'total' => count( $todos ), 'destino' => null );
	}
	$d = 'destino' === $fonte ? vtec_destino_por_slug( $slug ) : ( isset( $ctx['destino'] ) && is_array( $ctx['destino'] ) ? $ctx['destino'] : null );
	if ( ! $d || empty( $d['id'] ) ) {
		return array( 'itens' => array(), 'total' => 0, 'destino' => null );
	}
	$r = vtec_buscar( $d['id'], $ordem, $inicio, $qtd );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$itens = array();
	foreach ( $r['cards'] as $c ) {
		$itens[] = array( 'destino' => $d, 'produto' => $c );
	}
	return array( 'itens' => $itens, 'total' => $r['total'], 'destino' => $d );
}

function vtec_renderizador() {
	return isset( $GLOBALS['vtec_renderizador'] ) ? $GLOBALS['vtec_renderizador'] : 'vtec_render_elementor';
}

/** Conteúdo de um modelo do Elementor. Vazio se o Elementor não estiver ativo ou o modelo não existir. */
/** Só modelo publicado do Elementor (rascunho, privado ou lixeira nunca vão para a página nem para a REST pública). */
function vtec_modelo_publicado( $tipo, $status ) {
	return 'elementor_library' === $tipo && 'publish' === $status;
}

function vtec_render_elementor( $modelo, $com_css = false ) {
	if ( ! class_exists( '\Elementor\Plugin' ) || ! vtec_modelo_publicado( get_post_type( (int) $modelo ), get_post_status( (int) $modelo ) ) ) {
		return '';
	}
	return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( (int) $modelo, $com_css );
}

function vtec_card_padrao( $ctx ) {
	if ( ! empty( $ctx['produto'] ) ) {
		return vtec_html_cards( array( $ctx['produto'] ) );
	}
	return ! empty( $ctx['destino'] ) ? vtec_html_destino_card( $ctx['destino'] ) : '';
}

function vtec_render_card( $modelo, $ctx ) {
	$antes = vtec_contexto();
	vtec_definir_contexto( $ctx );
	$html = $modelo ? (string) call_user_func( vtec_renderizador(), (int) $modelo ) : '';
	if ( '' === trim( $html ) ) {
		$html = vtec_card_padrao( $ctx );
	}
	vtec_definir_contexto( $antes );
	return $html;
}

function vtec_html_grade( $itens, $total, $modelo, $op ) {
	if ( ! $itens ) {
		return '<p class="vtec-vazio">Nenhum passeio encontrado.</p>';
	}
	$h = '<div class="vtec-grade-el">';
	foreach ( $itens as $ctx ) {
		$h .= '<div class="vtec-grade-item">' . vtec_render_card( $modelo, $ctx ) . '</div>';
	}
	$h .= '</div>';
	if ( $total > count( $itens ) ) {
		$h .= '<button type="button" class="vtec-mais"'
			. ' data-fonte="' . esc_attr( $op['fonte'] ) . '" data-destino="' . esc_attr( $op['destino'] ) . '"'
			. ' data-ordem="' . esc_attr( $op['ordem'] ) . '" data-modelo="' . (int) $modelo . '"'
			. ' data-qtd="' . (int) $op['qtd'] . '" data-inicio="' . ( count( $itens ) + 1 ) . '">'
			. esc_html( $op['mais_texto'] ) . '</button>';
	}
	return $h;
}

function vtec_html_ordem( $destino, $ordem ) {
	$base = home_url( '/passeios/' . $destino['slug'] . '/' );
	$h    = '<nav class="vtec-ordem">Ordenar: ';
	foreach ( array( 'avaliacao' => 'Mais bem avaliados', 'preco' => 'Menor preço' ) as $valor => $rotulo ) {
		$url = 'avaliacao' === $valor ? $base : $base . '?ordem=' . $valor;
		$h  .= '<a href="' . esc_url( $url ) . '"' . ( $valor === $ordem ? ' aria-current="true"' : '' ) . '>' . esc_html( $rotulo ) . '</a> ';
	}
	return $h . '</nav>';
}

function vtec_render_grade( $s, $ctx ) {
	$fonte = isset( $s['fonte'] ) ? (string) $s['fonte'] : 'destino_atual';
	$ordem = isset( $s['ordem'] ) && 'preco' === $s['ordem'] ? 'preco' : 'avaliacao';
	if ( 'destino_atual' === $fonte && ! empty( $s['mostrar_ordem'] ) && isset( $ctx['ordem'] ) && in_array( $ctx['ordem'], array( 'avaliacao', 'preco' ), true ) ) {
		$ordem = $ctx['ordem'];
	}
	$qtd = isset( $s['quantidade'] ) ? min( 50, max( 1, (int) $s['quantidade'] ) ) : 12;
	$r   = vtec_itens_grade( $fonte, isset( $s['destino'] ) ? (string) $s['destino'] : '', $ctx, $ordem, 1, $qtd );
	if ( is_wp_error( $r ) ) {
		return vtec_html_indisponivel();
	}
	$h = ( 'destinos' !== $fonte && ! empty( $s['mostrar_ordem'] ) && $r['destino'] ) ? vtec_html_ordem( $r['destino'], $ordem ) : '';
	return $h . vtec_html_grade( $r['itens'], $r['total'], isset( $s['modelo'] ) ? (int) $s['modelo'] : 0, array(
		'fonte'      => 'destinos' === $fonte ? 'destinos' : 'destino',
		'destino'    => $r['destino'] ? $r['destino']['slug'] : '',
		'ordem'      => $ordem,
		'qtd'        => $qtd,
		'mais_texto' => isset( $s['mais_texto'] ) && '' !== $s['mais_texto'] ? $s['mais_texto'] : 'Carregar mais',
	) );
}
