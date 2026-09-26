<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
echo '<main id="content" class="site-main vtec-main">' . $GLOBALS['vtec_atual']['html'] . '</main>'; // phpcs:ignore -- HTML já escapado em render.php
get_footer();
