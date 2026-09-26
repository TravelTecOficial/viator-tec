<?php
/** Endereços /passeios/..., template dentro do tema, 404, SEO (Rank Math) e robots.txt. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_rotas() {
	add_rewrite_rule( '^passeios/sitemap\.xml$', 'index.php?vtec_pagina=sitemap', 'top' );
	add_rewrite_rule( '^passeios/?$', 'index.php?vtec_pagina=destinos', 'top' );
	add_rewrite_rule( '^passeios/p/([A-Za-z0-9_]+)(?:-[^/]*)?/?$', 'index.php?vtec_pagina=produto&vtec_codigo=$matches[1]', 'top' );
	add_rewrite_rule( '^passeios/([^/]+)/?$', 'index.php?vtec_pagina=destino&vtec_destino=$matches[1]', 'top' );
}

function vtec_pagina_404() {
	return array( 'status' => 404, 'titulo' => '', 'descricao' => '', 'canonica' => '', 'html' => '', 'contexto' => array(), 'modelo' => 0 );
}

/** Monta a página pedida. Não depende do WordPress além das opções e do cache — por isso é testável. */
function vtec_resolver_pagina( $pagina, $destino, $codigo, $ordem ) {
	$o = vtec_opcoes();

	if ( 'destinos' === $pagina ) {
		$destinos = vtec_destinos_com_foto();
		return array(
			'status'    => 200,
			'titulo'    => 'Passeios e ingressos',
			'descricao' => 'Passeios, ingressos e experiências nos principais destinos, com reserva segura pela Viator.',
			'canonica'  => home_url( '/passeios/' ),
			'html'      => vtec_html_destinos( $destinos ),
			'contexto'  => array( 'destino' => null, 'produto' => null ),
			'modelo'    => (int) $o['modelos']['destinos'],
		);
	}

	if ( 'destino' === $pagina ) {
		$d = vtec_destino_por_slug( $destino );
		if ( ! $d ) {
			return vtec_pagina_404();
		}
		$ordem = 'preco' === $ordem ? 'preco' : 'avaliacao';
		$r     = vtec_buscar( $d['id'], $ordem, 1, $o['por_pagina'] );
		return array(
			'status'    => 200,
			'titulo'    => 'Passeios em ' . $d['nome'],
			'descricao' => 'Os melhores passeios e ingressos em ' . $d['nome'] . ', com reserva segura pela Viator.',
			'canonica'  => home_url( '/passeios/' . $d['slug'] . '/' ),
			'html'      => is_wp_error( $r ) ? vtec_html_indisponivel() : vtec_html_destino( $d, $r['cards'], $r['total'], $ordem, $o['por_pagina'] ),
			'contexto'  => array( 'destino' => $d, 'produto' => null, 'ordem' => $ordem ),
			'modelo'    => (int) $o['modelos']['destino'],
		);
	}

	if ( 'produto' === $pagina ) {
		if ( ! vtec_codigo_valido( $codigo ) ) {
			return vtec_pagina_404();
		}
		$p = vtec_produto( $codigo );
		if ( is_wp_error( $p ) ) {
			$dados = $p->get_error_code() === 'vtec_http_400' || $p->get_error_code() === 'vtec_http_404';
			return $dados ? vtec_pagina_404() : array(
				'status' => 200, 'titulo' => 'Passeio', 'descricao' => '', 'canonica' => '', 'html' => vtec_html_indisponivel(),
				'contexto' => array(), 'modelo' => 0,
			);
		}
		$v = vtec_produto_view( $p, vtec_preco_a_partir( $codigo ) );
		return array(
			'status'    => 200,
			'titulo'    => $v['titulo'],
			'descricao' => mb_substr( wp_trim_words( implode( ' ', $v['paragrafos'] ), 30, '' ), 0, 160 ),
			'canonica'  => vtec_url_produto( $v['codigo'], $v['titulo'] ),
			'html'      => vtec_html_produto( $v ),
			'contexto'  => array( 'destino' => null, 'produto' => $v ),
			'modelo'    => (int) $o['modelos']['produto'],
		);
	}

	return vtec_pagina_404();
}

function vtec_robots( $txt ) {
	return rtrim( $txt ) . "\n\n# Viator Tec: conteúdo protegido (regra da Viator)\n"
		. "Disallow: /wp-json/viator-tec/v1/protegido\n"
		. "Disallow: /wp-content/plugins/viator-tec/assets/protegido.js\n";
}

/** Conteúdo da página: o modelo do Elementor escolhido (com o contexto) ou, se não houver/vier vazio, o HTML da fase 1. */
function vtec_render_pagina( $atual ) {
	if ( ! empty( $atual['modelo'] ) ) {
		vtec_definir_contexto( $atual['contexto'] );
		$html = (string) call_user_func( vtec_renderizador(), (int) $atual['modelo'], true );
		if ( '' !== trim( $html ) ) {
			return $html;
		}
	}
	return $atual['html'];
}

/** Página feita no Elementor não vai dentro de .site-main (o tema limita a largura; o banner precisa ir de ponta a ponta). */
function vtec_classe_main( $atual, $html ) {
	return $html !== $atual['html'] ? 'vtec-main vtec-main-elementor' : 'site-main vtec-main';
}

function vtec_tag_canonica( $url ) {
	return '' === $url ? '' : '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "
";
}

if ( defined( 'VTEC_TESTE' ) ) {
	return; // daqui para baixo só ganchos do WordPress
}

add_action( 'init', 'vtec_rotas' );

// Atualização automática não roda a ativação: regrava os links quando a versão muda.
add_action( 'init', function () {
	if ( get_option( 'vtec_rotas_versao' ) !== VTEC_VERSION ) {
		flush_rewrite_rules();
		// o índice do Rank Math fica em cache: sem isso ele continua apontando para o sitemap antigo
		if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}
		update_option( 'vtec_rotas_versao', VTEC_VERSION );
	}
}, 99 );

add_filter( 'query_vars', function ( $vars ) {
	return array_merge( $vars, array( 'vtec_pagina', 'vtec_destino', 'vtec_codigo' ) );
} );

// A requisição só tem query vars nossas: sem isso o WordPress a trataria como a home do blog.
add_action( 'parse_query', function ( $q ) {
	if ( $q->is_main_query() && $q->get( 'vtec_pagina' ) ) {
		$q->is_home = false;
		$q->is_archive = false;
		$q->is_singular = false;
		$q->is_page = false;
		$q->set( 'posts_per_page', 1 );
		$q->set( 'no_found_rows', true );
	}
} );

add_action( 'template_redirect', function () {
	$pagina = get_query_var( 'vtec_pagina' );
	if ( ! $pagina ) {
		return;
	}
	if ( 'sitemap' === $pagina ) {
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo vtec_xml_sitemap( vtec_urls_sitemap() ); // phpcs:ignore -- XML escapado em vtec_xml_sitemap
		exit;
	}
	$GLOBALS['vtec_atual'] = vtec_resolver_pagina(
		$pagina,
		(string) get_query_var( 'vtec_destino' ),
		(string) get_query_var( 'vtec_codigo' ),
		isset( $_GET['ordem'] ) ? sanitize_key( wp_unslash( $_GET['ordem'] ) ) : ''
	);
	if ( 404 === $GLOBALS['vtec_atual']['status'] ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		unset( $GLOBALS['vtec_atual'] );
		return;
	}
	status_header( 200 );
}, 5 );

add_filter( 'template_include', function ( $modelo ) {
	return isset( $GLOBALS['vtec_atual'] ) ? VTEC_DIR . 'templates/pagina.php' : $modelo;
}, 99 );

add_action( 'init', function () {
	wp_register_style( 'viator-tec', VTEC_URL . 'assets/viator-tec.css', array(), VTEC_VERSION );
	wp_register_script( 'viator-tec', VTEC_URL . 'assets/viator-tec.js', array(), VTEC_VERSION, true );
	wp_register_script( 'viator-tec-protegido', VTEC_URL . 'assets/protegido.js', array(), VTEC_VERSION, true );
	wp_add_inline_script( 'viator-tec', 'window.vtecCfg=' . wp_json_encode( array( 'rest' => rest_url( 'viator-tec/v1/' ) ) ) . ';', 'before' );
	wp_add_inline_script( 'viator-tec-protegido', 'window.vtecCfg=window.vtecCfg||' . wp_json_encode( array( 'rest' => rest_url( 'viator-tec/v1/' ) ) ) . ';', 'before' );
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! isset( $GLOBALS['vtec_atual'] ) ) {
		return;
	}
	wp_enqueue_style( 'viator-tec' );
	wp_enqueue_script( 'viator-tec' );
	wp_enqueue_script( 'viator-tec-protegido' );
} );

add_filter( 'body_class', function ( $classes ) {
	return isset( $GLOBALS['vtec_atual'] ) ? array_merge( $classes, array( 'vtec-pagina' ) ) : $classes;
} );

function vtec_titulo_seo() {
	return $GLOBALS['vtec_atual']['titulo'] . ' | ' . get_bloginfo( 'name' );
}
add_filter( 'pre_get_document_title', function ( $t ) {
	return isset( $GLOBALS['vtec_atual'] ) ? vtec_titulo_seo() : $t;
}, 99 );
add_filter( 'rank_math/frontend/title', function ( $t ) {
	return isset( $GLOBALS['vtec_atual'] ) ? vtec_titulo_seo() : $t;
}, 99 );
add_filter( 'rank_math/frontend/description', function ( $d ) {
	return isset( $GLOBALS['vtec_atual'] ) ? $GLOBALS['vtec_atual']['descricao'] : $d;
}, 99 );
// O Rank Math não gera canônica em rotas virtuais: desliga a dele e imprime a nossa.
add_filter( 'rank_math/frontend/canonical', function ( $c ) {
	return isset( $GLOBALS['vtec_atual'] ) ? '' : $c;
}, 99 );
add_action( 'wp_head', function () {
	if ( isset( $GLOBALS['vtec_atual'] ) ) {
		echo vtec_tag_canonica( $GLOBALS['vtec_atual']['canonica'] ); // phpcs:ignore -- escapado em vtec_tag_canonica
	}
}, 2 );
add_filter( 'rank_math/frontend/robots', function ( $r ) {
	return isset( $GLOBALS['vtec_atual'] ) ? array( 'index' => 'index', 'follow' => 'follow' ) : $r;
}, 99 );

add_filter( 'robots_txt', 'vtec_robots', 99 );
