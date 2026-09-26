<?php
/** HTML das páginas. Só recebe dados prontos e devolve texto; tudo escapado. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTEC_AVISO', 'A reserva e o pagamento são feitos no site da Viator.' );

function vtec_html_nota_vazia( $codigo ) {
	return '<span class="vtec-nota" data-codigo="' . esc_attr( $codigo ) . '"></span>';
}

function vtec_html_img( $url, $alt, $classe ) {
	return '' === $url ? '<div class="' . esc_attr( $classe ) . ' vtec-sem-foto"></div>'
		: '<img class="' . esc_attr( $classe ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
}

function vtec_html_destino_card( $d ) {
	return '<a class="vtec-destino" href="' . esc_url( home_url( '/passeios/' . $d['slug'] . '/' ) ) . '">'
		. vtec_html_img( $d['foto'], $d['nome'], 'vtec-destino-foto' )
		. '<span class="vtec-destino-nome">' . esc_html( $d['nome'] ) . '</span></a>';
}

function vtec_html_destinos( $destinos ) {
	$h = '<div class="vtec"><h1 class="vtec-titulo">Passeios e ingressos</h1><div class="vtec-grade vtec-grade-destinos">';
	foreach ( $destinos as $d ) {
		$h .= vtec_html_destino_card( $d );
	}
	return $h . '</div></div>';
}

function vtec_html_cards( $cards ) {
	$h = '';
	foreach ( $cards as $c ) {
		$h .= '<a class="vtec-card" href="' . esc_url( $c['url'] ) . '">'
			. vtec_html_img( $c['imagem'], $c['titulo'], 'vtec-card-foto' )
			. '<span class="vtec-card-corpo">'
			. vtec_html_nota_vazia( $c['codigo'] )
			. '<span class="vtec-card-titulo">' . esc_html( $c['titulo'] ) . '</span>'
			. ( '' !== $c['duracao'] ? '<span class="vtec-card-duracao">' . esc_html( $c['duracao'] ) . '</span>' : '' )
			. ( $c['cancelamento_gratis'] ? '<span class="vtec-selo">Cancelamento grátis</span>' : '' )
			. ( '' !== $c['preco'] ? '<span class="vtec-card-preco">a partir de <strong>' . esc_html( $c['preco'] ) . '</strong></span>' : '' )
			. '</span></a>';
	}
	return $h;
}

function vtec_html_destino( $destino, $cards, $total, $ordem, $por_pagina ) {
	$h = '<div class="vtec"><p class="vtec-voltar"><a href="' . esc_url( home_url( '/passeios/' ) ) . '">← Todos os destinos</a></p>'
		. '<h1 class="vtec-titulo">Passeios em ' . esc_html( $destino['nome'] ) . '</h1>'
		. '<p class="vtec-aviso">' . esc_html( VTEC_AVISO ) . '</p>';
	if ( ! $cards ) {
		return $h . '<p class="vtec-vazio">Nenhum passeio encontrado neste destino.</p></div>';
	}
	$itens = array();
	foreach ( $cards as $c ) {
		$itens[] = array( 'destino' => $destino, 'produto' => $c );
	}
	return $h . vtec_html_ordem( $destino, $ordem ) . vtec_html_grade( $itens, $total, 0, array(
		'fonte' => 'destino', 'destino' => $destino['slug'], 'ordem' => $ordem, 'qtd' => $por_pagina, 'mais_texto' => 'Carregar mais passeios',
	) ) . '</div>';
}

function vtec_html_lista( $titulo, $itens ) {
	if ( ! $itens ) {
		return '';
	}
	$h = '<h2>' . esc_html( $titulo ) . '</h2><ul class="vtec-lista">';
	foreach ( $itens as $i ) {
		$h .= '<li>' . esc_html( $i ) . '</li>';
	}
	return $h . '</ul>';
}

function vtec_html_produto( $v ) {
	$h = '<div class="vtec vtec-produto"><h1 class="vtec-titulo">' . esc_html( $v['titulo'] ) . '</h1>' . vtec_html_nota_vazia( $v['codigo'] );
	if ( $v['galeria'] ) {
		$h .= '<div class="vtec-galeria">';
		foreach ( $v['galeria'] as $i => $url ) {
			$h .= '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $v['titulo'] ) . '"' . ( $i ? ' loading="lazy"' : '' ) . '>';
		}
		$h .= '</div>';
	}
	$h .= '<div class="vtec-produto-colunas"><div class="vtec-produto-texto">';
	foreach ( $v['paragrafos'] as $par ) {
		$h .= '<p>' . esc_html( $par ) . '</p>';
	}
	$h .= vtec_html_lista( 'O que está incluído', $v['inclusoes'] )
		. vtec_html_lista( 'Não está incluído', $v['exclusoes'] )
		. ( '' !== $v['encontro'] ? '<h2>Ponto de encontro</h2><p>' . esc_html( $v['encontro'] ) . '</p>' : '' )
		. vtec_html_lista( 'Informações importantes', $v['informacoes'] )
		. ( '' !== $v['cancelamento'] ? '<h2>Cancelamento</h2><p>' . esc_html( $v['cancelamento'] ) . '</p>' : '' )
		. '</div><aside class="vtec-reserva">'
		. ( '' !== $v['preco'] ? '<p class="vtec-reserva-preco">A partir de <strong>' . esc_html( $v['preco'] ) . '</strong> por pessoa</p>' : '' )
		. ( '' !== $v['duracao'] ? '<p class="vtec-reserva-duracao">Duração: ' . esc_html( $v['duracao'] ) . '</p>' : '' )
		. ( '' !== $v['link'] ? '<a class="vtec-botao" href="' . esc_url( $v['link'] ) . '" target="_blank" rel="noopener sponsored">Reservar na Viator</a>' : '' )
		. '<p class="vtec-aviso">' . esc_html( VTEC_AVISO ) . '</p></aside></div></div>';
	return $h;
}

function vtec_html_indisponivel() {
	return '<div class="vtec"><p class="vtec-vazio">Passeios indisponíveis no momento. Tente de novo em alguns minutos.</p></div>';
}

/** Campo "Adicionar destino" do painel. Tudo em div: <ul> dentro de <p> é expulso pelo navegador. */
function vtec_html_campo_destino() {
	return '<div class="vtec-auto-linha"><label for="vtec-procurar"><strong>Adicionar destino:</strong></label> '
		. '<div class="vtec-auto"><input id="vtec-procurar" type="search" class="regular-text" autocomplete="off" placeholder="Digite a cidade ou o país, ex.: Paris">'
		. '<ul id="vtec-sugestoes" hidden></ul></div> <span id="vtec-aviso-destino" class="description"></span></div>';
}
