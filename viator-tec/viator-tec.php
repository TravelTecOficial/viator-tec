<?php
/**
 * Plugin Name: Viator Tec
 * Description: Passeios da Viator (Partner API, afiliado) em /passeios/: destinos, lista de passeios e página do passeio com o botão Reservar na Viator.
 * Version:     0.1.1
 * Author:      TravelTec
 * Text Domain: viator-tec
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTEC_VERSION', '0.1.1' );
define( 'VTEC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VTEC_URL', plugin_dir_url( __FILE__ ) );
define( 'VTEC_BASENAME', plugin_basename( __FILE__ ) );

foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'rotas', 'rest', 'admin', 'atualizador' ) as $vtec_arquivo ) {
	require_once VTEC_DIR . 'includes/' . $vtec_arquivo . '.php';
}

register_activation_hook( __FILE__, function () {
	vtec_rotas();
	flush_rewrite_rules();
	update_option( 'vtec_rotas_versao', VTEC_VERSION );
} );
