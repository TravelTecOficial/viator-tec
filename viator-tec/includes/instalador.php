<?php
/** Instala os 5 modelos do Elementor (templates/modelos/*.json). Nunca sobrescreve modelo existente. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vtec_modelos_chaves() {
	return array( 'card_destino', 'card_passeio', 'destinos', 'destino', 'produto' );
}

function vtec_ler_modelo( $chave ) {
	$arquivo = VTEC_DIR . 'templates/modelos/' . basename( $chave ) . '.json';
	if ( ! file_exists( $arquivo ) ) {
		return null;
	}
	$m = json_decode( file_get_contents( $arquivo ), true );
	return is_array( $m ) ? $m : null;
}

function vtec_preparar_modelo( $modelo, $ids ) {
	$dados = wp_json_encode( $modelo['content'] );
	foreach ( vtec_modelos_chaves() as $chave ) {
		$dados = str_replace( '{{MODELO_' . $chave . '}}', (string) ( isset( $ids[ $chave ] ) ? (int) $ids[ $chave ] : 0 ), $dados );
	}
	return $dados;
}

/** O dono nunca abriu/salvou no Elementor (salvar muda a data de modificação; gravar meta, não). */
function vtec_modelo_intocado( $criado, $modificado ) {
	return (string) $criado === (string) $modificado;
}

function vtec_gravar_modelo( $id, $m, $ids ) {
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', $m['tipo'] );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_elementor_page_settings', $m['page_settings'] );
	update_post_meta( $id, '_elementor_data', wp_slash( vtec_preparar_modelo( $m, $ids ) ) );
	update_post_meta( $id, '_vtec_modelo_versao', VTEC_VERSION );
	wp_set_object_terms( $id, $m['tipo'], 'elementor_library_type' );
}

function vtec_instalar_modelos( $forcar = false ) {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return new WP_Error( 'vtec_sem_elementor', 'O Elementor não está ativo.' );
	}
	$o   = vtec_opcoes();
	$ids = $o['modelos'];
	foreach ( vtec_modelos_chaves() as $chave ) {
		$m = vtec_ler_modelo( $chave );
		if ( ! $m ) {
			continue;
		}
		$post = ! empty( $ids[ $chave ] ) ? get_post( $ids[ $chave ] ) : null;
		if ( ! $forcar && $post ) {
			// versão nova do plugin: atualiza só o modelo que o dono não editou
			if ( VTEC_VERSION !== get_post_meta( $post->ID, '_vtec_modelo_versao', true ) && vtec_modelo_intocado( $post->post_date, $post->post_modified ) ) {
				vtec_gravar_modelo( $post->ID, $m, $ids );
			}
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $m['titulo'] ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		vtec_gravar_modelo( $id, $m, $ids );
		$ids[ $chave ] = (int) $id;
	}
	$o['modelos'] = $ids;
	update_option( 'vtec_opcoes', $o, false );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}
	return $ids;
}

if ( defined( 'VTEC_TESTE' ) ) {
	return;
}

// A cada versão nova: instala os modelos que faltam e atualiza os que o dono não editou.
add_action( 'admin_init', function () {
	if ( VTEC_VERSION !== get_option( 'vtec_modelos_versao' ) && current_user_can( 'manage_options' ) && did_action( 'elementor/loaded' ) ) {
		update_option( 'vtec_modelos_versao', VTEC_VERSION, false );
		vtec_instalar_modelos();
	}
} );
