<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
$vtec_html = vtec_render_pagina( $GLOBALS['vtec_atual'] );
echo '<main id="content" class="' . esc_attr( vtec_classe_main( $GLOBALS['vtec_atual'], $vtec_html ) ) . '">' . $vtec_html . '</main>'; // phpcs:ignore -- HTML escapado em render.php / Elementor
get_footer();
