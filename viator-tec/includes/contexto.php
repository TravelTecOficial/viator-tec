<?php
/**
 * "Passeio/destino atual" das telas feitas no Elementor. Campos dinâmicos, shortcode [vtec] e a grade
 * leem daqui. Sem contexto (editor do Elementor), usa um passeio de exemplo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_definir_contexto( $ctx ) {
	$GLOBALS['vtec_contexto'] = $ctx;
}

function vtec_contexto() {
	return isset( $GLOBALS['vtec_contexto'] ) && is_array( $GLOBALS['vtec_contexto'] ) ? $GLOBALS['vtec_contexto'] : null;
}

function vtec_contexto_ou_exemplo() {
	$c = vtec_contexto();
	return $c ? $c : vtec_contexto_exemplo();
}

/** 1º passeio do 1º destino da lista; sem destino/chave, um exemplo fixo (o editor nunca fica vazio). */
function vtec_contexto_exemplo() {
	static $exemplo = null;
	if ( null !== $exemplo ) {
		return $exemplo;
	}
	$o       = vtec_opcoes();
	$destino = $o['destinos'] ? $o['destinos'][0] : null;
	$produto = null;
	if ( $destino ) {
		$r = vtec_buscar( $destino['id'], 'avaliacao', 1, 1 );
		if ( ! is_wp_error( $r ) && $r['cards'] ) {
			$codigo = $r['cards'][0]['codigo'];
			$p      = vtec_produto( $codigo );
			if ( ! is_wp_error( $p ) ) {
				$produto = vtec_produto_view( $p, vtec_preco_a_partir( $codigo ) );
			}
		}
	}
	if ( ! $produto ) {
		$produto = array(
			'codigo' => '', 'titulo' => 'Nome do passeio (exemplo)',
			'paragrafos' => array( 'Descrição do passeio. Cadastre um destino em Configurações › Viator Tec para ver dados reais.' ),
			'galeria' => array(), 'duracao' => '3h', 'preco' => 'R$ 199,00', 'inclusoes' => array( 'Guia' ),
			'exclusoes' => array( 'Gorjetas' ), 'cancelamento' => 'Cancelamento grátis até 24 horas antes.',
			'encontro' => 'Ponto de encontro do passeio.', 'informacoes' => array(), 'link' => '', 'nota' => 0.0,
			'total' => 0, 'imagem' => '', 'url' => '',
		);
	}
	if ( ! $destino ) {
		$destino = array( 'id' => 0, 'nome' => 'Destino (exemplo)', 'slug' => '', 'foto' => '' );
	}
	if ( '' === $destino['foto'] ) {
		$destino['foto'] = $produto['imagem'];
	}
	$exemplo = array( 'destino' => $destino, 'produto' => $produto, 'ordem' => 'avaliacao' );
	return $exemplo;
}

function vtec_html_ul( $itens ) {
	if ( ! $itens ) {
		return '';
	}
	$h = '<ul>';
	foreach ( $itens as $i ) {
		$h .= '<li>' . esc_html( $i ) . '</li>';
	}
	return $h . '</ul>';
}

/** Mesmo HTML da galeria dos roteiros: o script da página do roteiro transforma em carrossel com lightbox. */
function vtec_html_galeria( $urls, $alt ) {
	if ( ! $urls ) {
		return '';
	}
	$h = '<div class="rt-galeria" style="padding:4px 0"><div class="rt-galeria-track" style="display:flex;gap:12px;width:max-content;flex-wrap:nowrap;--rt-duracao:40s">';
	foreach ( $urls as $u ) {
		$h .= '<img src="' . esc_url( $u ) . '" alt="' . esc_attr( $alt ) . '" style="height:220px;width:auto;flex:0 0 auto;border-radius:8px" />';
	}
	return $h . '</div></div>';
}

function vtec_campos_tags() {
	return array(
		'titulo'       => array( 'Título do passeio', 'texto' ),
		'descricao'    => array( 'Descrição', 'texto' ),
		'preco'        => array( 'Preço (a partir de)', 'texto' ),
		'duracao'      => array( 'Duração', 'texto' ),
		'galeria'      => array( 'Galeria de fotos', 'texto' ),
		'inclusoes'    => array( 'O que inclui', 'texto' ),
		'exclusoes'    => array( 'Não inclui', 'texto' ),
		'encontro'     => array( 'Ponto de encontro', 'texto' ),
		'cancelamento' => array( 'Cancelamento', 'texto' ),
		'informacoes'  => array( 'Informações importantes', 'texto' ),
		'nota'         => array( 'Nota (avaliações)', 'texto' ),
		'destino_nome' => array( 'Nome do destino', 'texto' ),
		'imagem'       => array( 'Foto do passeio', 'imagem' ),
		'destino_foto' => array( 'Foto do destino', 'imagem' ),
		'url_passeio'  => array( 'Link da página do passeio', 'url' ),
		'link_reserva' => array( 'Link Reservar na Viator', 'url' ),
		'destino_url'  => array( 'Link da página do destino', 'url' ),
	);
}

/** Valor de um campo no contexto. Texto já escapado; URLs cruas (quem imprime escapa). */
function vtec_valor_campo( $campo, $ctx ) {
	$p = isset( $ctx['produto'] ) && is_array( $ctx['produto'] ) ? $ctx['produto'] : array();
	$d = isset( $ctx['destino'] ) && is_array( $ctx['destino'] ) ? $ctx['destino'] : array();
	$v = function ( $k ) use ( $p ) {
		return isset( $p[ $k ] ) && is_string( $p[ $k ] ) ? $p[ $k ] : '';
	};
	switch ( $campo ) {
		case 'titulo':
		case 'duracao':
			return esc_html( $v( $campo ) );
		case 'descricao':
			$h = '';
			foreach ( isset( $p['paragrafos'] ) ? $p['paragrafos'] : array() as $par ) {
				$h .= '<p>' . esc_html( $par ) . '</p>';
			}
			return $h;
		case 'preco':
			return '' !== $v( 'preco' ) ? 'a partir de ' . esc_html( $v( 'preco' ) ) . ', por pessoa' : '';
		case 'galeria':
			return vtec_html_galeria( isset( $p['galeria'] ) ? $p['galeria'] : array(), $v( 'titulo' ) );
		case 'inclusoes':
		case 'exclusoes':
		case 'informacoes':
			return vtec_html_ul( isset( $p[ $campo ] ) ? $p[ $campo ] : array() );
		case 'cancelamento':
		case 'encontro':
			return '' !== $v( $campo ) ? '<p>' . esc_html( $v( $campo ) ) . '</p>' : '';
		case 'nota':
			return '' !== $v( 'codigo' ) ? vtec_html_nota_vazia( $v( 'codigo' ) ) : '';
		case 'imagem':
			return $v( 'imagem' );
		case 'url_passeio':
			return $v( 'url' );
		case 'link_reserva':
			return $v( 'link' );
		case 'destino_nome':
			return esc_html( isset( $d['nome'] ) ? $d['nome'] : '' );
		case 'destino_url':
			return ! empty( $d['slug'] ) ? home_url( '/passeios/' . $d['slug'] . '/' ) : '';
		case 'destino_foto':
			return isset( $d['foto'] ) ? (string) $d['foto'] : '';
	}
	return '';
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

// [vtec campo="destino_nome"] — mesmo valor dos campos dinâmicos, para usar dentro de textos.
add_shortcode( 'vtec', function ( $atts ) {
	$atts = shortcode_atts( array( 'campo' => '' ), $atts, 'vtec' );
	return vtec_valor_campo( sanitize_key( $atts['campo'] ), vtec_contexto_ou_exemplo() );
} );
