<?php
/** Ganchos do Elementor. As classes (que estendem o Elementor) só carregam quando ele está ativo. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_classes_tags() {
	$classes = array();
	foreach ( array_keys( vtec_campos_tags() ) as $campo ) {
		$classes[ $campo ] = 'VTEC_Tag_' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $campo ) ) );
	}
	return $classes;
}

/** Elementos com campos Viator (ou a grade) nunca entram no cache de elementos do Elementor. */
function vtec_elemento_dinamico( $atual, $dados ) {
	if ( $atual ) {
		return true;
	}
	if ( isset( $dados['widgetType'] ) && 'vtec-grade' === $dados['widgetType'] ) {
		return true;
	}
	$din = isset( $dados['settings']['__dynamic__'] ) ? wp_json_encode( $dados['settings']['__dynamic__'] ) : '';
	return false !== strpos( (string) $din, 'name=\"vtec-' ) || false !== strpos( (string) $din, 'name="vtec-' );
}

function vtec_opcoes_modelos_select() {
	$lista = array( '0' => '— Card padrão do plugin —' );
	foreach ( get_posts( array( 'post_type' => 'elementor_library', 'numberposts' => 200, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
		$lista[ (string) $p->ID ] = $p->post_title;
	}
	return $lista;
}

function vtec_opcoes_destinos_select() {
	$lista = array();
	foreach ( vtec_opcoes()['destinos'] as $d ) {
		$lista[ $d['slug'] ] = $d['nome'];
	}
	return $lista;
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

add_action( 'elementor/dynamic_tags/register', function ( $gerente ) {
	require_once VTEC_DIR . 'includes/elementor-classes.php';
	$gerente->register_group( 'viator', array( 'title' => 'Viator' ) );
	foreach ( vtec_classes_tags() as $classe ) {
		$gerente->register( new $classe() );
	}
} );

add_action( 'elementor/widgets/register', function ( $gerente ) {
	require_once VTEC_DIR . 'includes/elementor-classes.php';
	$gerente->register( new VTEC_Widget_Grade() );
} );

add_filter( 'elementor/element/is_dynamic_content', 'vtec_elemento_dinamico', 10, 2 );
