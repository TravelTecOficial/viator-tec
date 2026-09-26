<?php
/** Configurações › Viator Tec. */

if ( ! defined( 'ABSPATH' ) || defined( 'VTEC_TESTE' ) ) {
	return;
}

add_action( 'admin_menu', function () {
	add_options_page( 'Viator Tec', 'Viator Tec', 'manage_options', 'viator-tec', 'vtec_tela_admin' );
} );

add_action( 'admin_enqueue_scripts', function ( $tela ) {
	if ( 'settings_page_viator-tec' !== $tela ) {
		return;
	}
	wp_enqueue_script( 'viator-tec-admin', VTEC_URL . 'assets/admin.js', array(), VTEC_VERSION, true );
	wp_add_inline_script( 'viator-tec-admin', 'window.vtecAdmin=' . wp_json_encode( array(
		'rest'  => rest_url( 'viator-tec/v1/destinos' ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	) ) . ';', 'before' );
	wp_add_inline_style( 'common', '.vtec-auto-linha{margin:12px 0}.vtec-auto{position:relative;display:inline-block;vertical-align:middle}#vtec-sugestoes{position:absolute;z-index:99;left:0;right:0;top:100%;margin:2px 0 0;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-height:280px;overflow:auto;box-shadow:0 4px 12px rgba(0,0,0,.1)}#vtec-sugestoes li{margin:0;padding:8px 10px;cursor:pointer}#vtec-sugestoes li:hover,#vtec-sugestoes li.ativo{background:#f0f6fc}#vtec-destinos tr.vtec-novo td{background:#fff8e5}' );
} );

add_action( 'admin_post_vtec_salvar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'vtec_salvar' );
	update_option( 'vtec_opcoes', vtec_sanitizar_opcoes( wp_unslash( $_POST ), vtec_opcoes() ), false );
	vtec_limpar_cache();
	do_action( 'litespeed_purge_all' ); // páginas /passeios/ já em cache (inclusive 404) passam a refletir a mudança
	wp_safe_redirect( admin_url( 'options-general.php?page=viator-tec&salvo=1' ) );
	exit;
} );

add_action( 'admin_post_vtec_limpar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'vtec_limpar' );
	vtec_limpar_cache();
	do_action( 'litespeed_purge_all' );
	wp_safe_redirect( admin_url( 'options-general.php?page=viator-tec&limpo=1' ) );
	exit;
} );

function vtec_tela_admin() {
	$o     = vtec_opcoes();
	echo '<div class="wrap"><h1>Viator Tec</h1>';
	if ( isset( $_GET['salvo'] ) ) {
		echo '<div class="notice notice-success"><p>Salvo. O cache foi limpo.</p></div>';
	}
	if ( isset( $_GET['limpo'] ) ) {
		echo '<div class="notice notice-success"><p>Cache limpo.</p></div>';
	}

	// Teste de conexão: primeira busca do primeiro destino.
	if ( $o['destinos'] ) {
		$t = vtec_buscar( $o['destinos'][0]['id'], 'avaliacao', 1, 1 );
		echo is_wp_error( $t )
			? '<div class="notice notice-error"><p>Conexão com a Viator: ' . esc_html( $t->get_error_message() ) . '</p></div>'
			: '<div class="notice notice-info"><p>Conexão com a Viator OK (' . esc_html( 'producao' === $o['ambiente'] ? 'produção' : 'sandbox' ) . '): ' . (int) $t['total'] . ' passeios em ' . esc_html( $o['destinos'][0]['nome'] ) . '.</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="vtec_salvar">';
	wp_nonce_field( 'vtec_salvar' );
	echo '<table class="form-table"><tr><th>Ambiente</th><td><select name="ambiente">'
		. '<option value="sandbox"' . selected( $o['ambiente'], 'sandbox', false ) . '>Sandbox (teste)</option>'
		. '<option value="producao"' . selected( $o['ambiente'], 'producao', false ) . '>Produção</option></select></td></tr>';
	foreach ( array( 'chave_sandbox' => 'Chave sandbox', 'chave_producao' => 'Chave de produção' ) as $campo => $rotulo ) {
		$ph = '' !== $o[ $campo ] ? '•••••••• (salva — deixe em branco para manter)' : 'cole a chave';
		echo '<tr><th>' . esc_html( $rotulo ) . '</th><td><input type="password" autocomplete="off" class="regular-text" name="' . esc_attr( $campo ) . '" placeholder="' . esc_attr( $ph ) . '"></td></tr>';
	}
	echo '<tr><th>Campanha</th><td><input class="regular-text" name="campanha" value="' . esc_attr( $o['campanha'] ) . '"></td></tr>'
		. '<tr><th>Passeios por página</th><td><input type="number" min="1" max="50" name="por_pagina" value="' . (int) $o['por_pagina'] . '"></td></tr></table>';

	echo '<h2>Destinos</h2>'
		. vtec_html_campo_destino()
		. '<p class="description">Escolha na lista e o destino entra na tabela; depois clique em Salvar. O nome e o endereço (/passeios/<em>slug</em>/) podem ser editados. Foto é opcional — sem foto usa a do passeio mais bem avaliado.</p>'
		. '<table class="widefat striped"><thead><tr><th>ID Viator</th><th>Nome</th><th>Slug</th><th>URL da foto</th></tr></thead><tbody id="vtec-destinos">';
	$linhas = array_merge( $o['destinos'], array_fill( 0, 1, array( 'id' => '', 'nome' => '', 'slug' => '', 'foto' => '' ) ) );
	foreach ( $linhas as $d ) {
		echo '<tr><td><input size="8" name="destino_id[]" value="' . esc_attr( $d['id'] ) . '"></td>'
			. '<td><input name="destino_nome[]" value="' . esc_attr( $d['nome'] ) . '"></td>'
			. '<td><input name="destino_slug[]" value="' . esc_attr( $d['slug'] ) . '"></td>'
			. '<td><input class="regular-text" name="destino_foto[]" value="' . esc_attr( $d['foto'] ) . '"></td></tr>';
	}
	echo '</tbody></table><p>Para tirar um destino, apague o ID e salve. Linhas em branco são ignoradas.</p>';
	submit_button( 'Salvar' );
	echo '</form>';

	echo '<h2>Cache</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="vtec_limpar">';
	wp_nonce_field( 'vtec_limpar' );
	submit_button( 'Limpar cache', 'secondary' );
	echo '</form></div>';
}
