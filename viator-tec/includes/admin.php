<?php
/** Configurações › Viator Tec. */

if ( ! defined( 'ABSPATH' ) || defined( 'VTEC_TESTE' ) ) {
	return;
}

add_action( 'admin_menu', function () {
	add_options_page( 'Viator Tec', 'Viator Tec', 'manage_options', 'viator-tec', 'vtec_tela_admin' );
} );

add_action( 'admin_post_vtec_salvar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'vtec_salvar' );
	update_option( 'vtec_opcoes', vtec_sanitizar_opcoes( wp_unslash( $_POST ), vtec_opcoes() ), false );
	vtec_limpar_cache();
	wp_safe_redirect( admin_url( 'options-general.php?page=viator-tec&salvo=1' ) );
	exit;
} );

add_action( 'admin_post_vtec_limpar', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'vtec_limpar' );
	vtec_limpar_cache();
	wp_safe_redirect( admin_url( 'options-general.php?page=viator-tec&limpo=1' ) );
	exit;
} );

function vtec_tela_admin() {
	$o     = vtec_opcoes();
	$busca = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';
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

	echo '<h2>Destinos</h2><p>ID da Viator, nome exibido, endereço (/passeios/<em>slug</em>/) e foto (opcional — sem foto usa a do passeio mais bem avaliado).</p>'
		. '<table class="widefat striped"><thead><tr><th>ID Viator</th><th>Nome</th><th>Slug</th><th>URL da foto</th></tr></thead><tbody>';
	$linhas = array_merge( $o['destinos'], array_fill( 0, 3, array( 'id' => '', 'nome' => '', 'slug' => '', 'foto' => '' ) ) );
	foreach ( $linhas as $d ) {
		echo '<tr><td><input size="8" name="destino_id[]" value="' . esc_attr( $d['id'] ) . '"></td>'
			. '<td><input name="destino_nome[]" value="' . esc_attr( $d['nome'] ) . '"></td>'
			. '<td><input name="destino_slug[]" value="' . esc_attr( $d['slug'] ) . '"></td>'
			. '<td><input class="regular-text" name="destino_foto[]" value="' . esc_attr( $d['foto'] ) . '"></td></tr>';
	}
	echo '</tbody></table><p>Para tirar um destino, apague o ID e salve. Linhas em branco são ignoradas.</p>';
	submit_button( 'Salvar' );
	echo '</form>';

	echo '<h2>Procurar ID de destino</h2><form method="get"><input type="hidden" name="page" value="viator-tec">'
		. '<input name="busca" value="' . esc_attr( $busca ) . '" placeholder="ex.: Paris"> ';
	submit_button( 'Procurar', 'secondary', '', false );
	echo '</form>';
	if ( '' !== $busca ) {
		$lista = vtec_destinos_viator();
		if ( is_wp_error( $lista ) ) {
			echo '<p>Erro: ' . esc_html( $lista->get_error_message() ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( vtec_filtrar_destinos( $lista, $busca ) as $d ) {
				echo '<li><code>' . (int) $d['id'] . '</code> — ' . esc_html( $d['nome'] ) . ' (' . esc_html( $d['tipo'] ) . ')</li>';
			}
			echo '</ul>';
		}
	}

	echo '<h2>Cache</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="vtec_limpar">';
	wp_nonce_field( 'vtec_limpar' );
	submit_button( 'Limpar cache', 'secondary' );
	echo '</form></div>';
}
