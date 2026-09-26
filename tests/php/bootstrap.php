<?php
// Base dos testes: constantes, stubs mínimos do WordPress e verificadores. Não vai no zip.
define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'VTEC_TESTE', true );

$GLOBALS['vt_falhas']     = 0;
$GLOBALS['vt_opcoes']     = array();
$GLOBALS['vt_transients'] = array();   // chave => [valor, ttl]
$GLOBALS['vt_http']       = array();   // fila de respostas falsas para wp_remote_request
$GLOBALS['vt_http_log']   = array();   // [url, args] de cada chamada
$GLOBALS['vt_ganchos']    = array();   // gancho => [callbacks]
$GLOBALS['vt_regras']     = array();   // add_rewrite_rule
$GLOBALS['vt_sono']       = array();

function ok( $cond, $msg ) {
	echo ( $cond ? 'OK    ' : 'FALHA ' ) . $msg . "\n";
	if ( ! $cond ) { $GLOBALS['vt_falhas']++; }
}
function igual( $esperado, $atual, $msg ) {
	$bom = $esperado === $atual;
	echo ( $bom ? 'OK    ' : 'FALHA ' ) . $msg . "\n";
	if ( ! $bom ) {
		echo '      esperado: ' . var_export( $esperado, true ) . "\n      atual:    " . var_export( $atual, true ) . "\n";
		$GLOBALS['vt_falhas']++;
	}
}
function contem( $trecho, $texto, $msg ) { ok( false !== strpos( (string) $texto, $trecho ), $msg ); }
function nao_contem( $trecho, $texto, $msg ) { ok( false === strpos( (string) $texto, $trecho ), $msg ); }
function fixture( $nome ) { return json_decode( file_get_contents( '/f/' . $nome . '.json' ), true ); }
function resposta_falsa( $codigo, $corpo, $cabecalhos = array() ) {
	$GLOBALS['vt_http'][] = array( 'response' => array( 'code' => $codigo ), 'body' => is_string( $corpo ) ? $corpo : json_encode( $corpo ), 'headers' => $cabecalhos );
}

class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $s ) { return (string) $s; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function remove_accents( $s ) {
	return strtr( $s, array( 'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ç'=>'C' ) );
}
function sanitize_title( $s ) {
	$s = strtolower( remove_accents( (string) $s ) );
	$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
	return trim( $s, '-' );
}
function home_url( $p = '' ) { return 'https://exemplo.test' . $p; }
function admin_url( $p = '' ) { return 'https://exemplo.test/wp-admin/' . $p; }
function get_bloginfo( $k ) { return 'Rede Turística'; }
function wp_trim_words( $t, $n = 55, $mais = '…' ) {
	$p = preg_split( '/\s+/', trim( strip_tags( $t ) ) );
	return count( $p ) > $n ? implode( ' ', array_slice( $p, 0, $n ) ) . $mais : implode( ' ', $p );
}

function get_option( $k, $padrao = false ) { return array_key_exists( $k, $GLOBALS['vt_opcoes'] ) ? $GLOBALS['vt_opcoes'][ $k ] : $padrao; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['vt_opcoes'][ $k ] = $v; return true; }
function get_transient( $k ) { return isset( $GLOBALS['vt_transients'][ $k ] ) ? $GLOBALS['vt_transients'][ $k ][0] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['vt_transients'][ $k ] = array( $v, $ttl ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['vt_transients'][ $k ] ); return true; }

function wp_remote_request( $url, $args ) {
	$GLOBALS['vt_http_log'][] = array( $url, $args );
	if ( ! $GLOBALS['vt_http'] ) { return new WP_Error( 'http_request_failed', 'sem resposta falsa na fila' ); }
	return array_shift( $GLOBALS['vt_http'] );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['response']['code'] : ''; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) && isset( $r['headers'][ $h ] ) ? $r['headers'][ $h ] : ''; }
function vtec_dormir( $s ) { $GLOBALS['vt_sono'][] = $s; }

function add_action( $g, $cb, $p = 10, $n = 1 ) { $GLOBALS['vt_ganchos'][ $g ][] = $cb; }
function add_filter( $g, $cb, $p = 10, $n = 1 ) { $GLOBALS['vt_ganchos'][ $g ][] = $cb; }
function add_rewrite_rule( $regex, $destino, $pos = 'bottom' ) { $GLOBALS['vt_regras'][ $regex ] = $destino; }
function register_activation_hook( $f, $cb ) {}
function plugin_dir_path( $f ) { return '/p/'; }
function plugin_dir_url( $f ) { return 'https://exemplo.test/wp-content/plugins/viator-tec/'; }
function plugin_basename( $f ) { return 'viator-tec/viator-tec.php'; }
