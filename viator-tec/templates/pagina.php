<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
echo '<main id="content" class="site-main vtec-main">' . vtec_render_pagina( $GLOBALS['vtec_atual'] ) . '</main>'; // phpcs:ignore -- HTML escapado em render.php / Elementor
get_footer();
