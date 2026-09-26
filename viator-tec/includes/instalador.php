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

/**
 * O modelo continua exatamente como o plugin gravou: o hash salvo bate com o _elementor_data atual.
 * Qualquer edição (Elementor, EMCP, script) muda os dados; modelo sem hash não foi o plugin que criou.
 */
function vtec_modelo_intocado( $hash_salvo, $dados_atuais ) {
	return '' !== (string) $hash_salvo && hash_equals( (string) $hash_salvo, md5( (string) $dados_atuais ) );
}

/**
 * O que fazer com uma chave: criar só o que o plugin nunca criou (ou "Reinstalar"), e só selecionar
 * na tela quando o dono ainda não escolheu nada. Modelo do plugin apagado pelo dono não volta sozinho.
 */
function vtec_plano_modelo( $instalado_id, $instalado_existe, $selecionado, $forcar ) {
	if ( $forcar ) {
		return array( 'criar' => true, 'selecionar' => true );
	}
	if ( ! $instalado_id ) {
		return array( 'criar' => true, 'selecionar' => ! $selecionado );
	}
	return array( 'criar' => false, 'selecionar' => false );
}

function vtec_gravar_modelo( $id, $m, $ids ) {
	$dados = vtec_preparar_modelo( $m, $ids );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', $m['tipo'] );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_elementor_page_settings', $m['page_settings'] );
	update_post_meta( $id, '_elementor_data', wp_slash( $dados ) );
	update_post_meta( $id, '_vtec_modelo_versao', VTEC_VERSION );
	update_post_meta( $id, '_vtec_modelo_hash', md5( $dados ) );
	wp_set_object_terms( $id, $m['tipo'], 'elementor_library_type' );
}

/**
 * IDs dos modelos que o plugin criou (separados da escolha do dono em vtec_opcoes['modelos']).
 * Até a 0.3.4 não havia essa lista: adota os modelos marcados com _vtec_modelo_versao e ainda não editados.
 */
function vtec_modelos_instalados() {
	$inst = get_option( 'vtec_modelos_instalados', null );
	if ( is_array( $inst ) ) {
		return $inst;
	}
	$inst = array();
	foreach ( vtec_opcoes()['modelos'] as $chave => $id ) {
		$post = $id ? get_post( $id ) : null;
		if ( $post && get_post_meta( $id, '_vtec_modelo_versao', true ) ) {
			$inst[ $chave ] = (int) $id;
			if ( $post->post_date === $post->post_modified && ! get_post_meta( $id, '_vtec_modelo_hash', true ) ) {
				update_post_meta( $id, '_vtec_modelo_hash', md5( (string) get_post_meta( $id, '_elementor_data', true ) ) );
			}
		}
	}
	update_option( 'vtec_modelos_instalados', $inst, false );
	return $inst;
}

function vtec_instalar_modelos( $forcar = false ) {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return new WP_Error( 'vtec_sem_elementor', 'O Elementor não está ativo.' );
	}
	$o    = vtec_opcoes();
	$inst = vtec_modelos_instalados();
	foreach ( vtec_modelos_chaves() as $chave ) {
		$m = vtec_ler_modelo( $chave );
		if ( ! $m ) {
			continue;
		}
		$id_inst = isset( $inst[ $chave ] ) ? (int) $inst[ $chave ] : 0;
		$post    = $id_inst ? get_post( $id_inst ) : null;
		$plano   = vtec_plano_modelo( $id_inst, (bool) $post && 'trash' !== $post->post_status, (int) $o['modelos'][ $chave ], $forcar );
		if ( ! $plano['criar'] ) {
			// versão nova: atualiza o modelo do plugin que ninguém mexeu
			if ( $post && VTEC_VERSION !== get_post_meta( $post->ID, '_vtec_modelo_versao', true )
				&& vtec_modelo_intocado( get_post_meta( $post->ID, '_vtec_modelo_hash', true ), get_post_meta( $post->ID, '_elementor_data', true ) ) ) {
				vtec_gravar_modelo( $post->ID, $m, $inst );
			}
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $m['titulo'] ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		vtec_gravar_modelo( $id, $m, $inst );
		$inst[ $chave ] = (int) $id;
		if ( $plano['selecionar'] ) {
			$o['modelos'][ $chave ] = (int) $id;
		}
	}
	update_option( 'vtec_modelos_instalados', $inst, false );
	update_option( 'vtec_opcoes', $o, false );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}
	return $inst;
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
