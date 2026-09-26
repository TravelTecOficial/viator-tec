<?php
namespace Elementor\Core\DynamicTags {
	abstract class Base_Tag { public function __construct( $d = array() ) {} }
	abstract class Tag extends Base_Tag {}
	abstract class Data_Tag extends Base_Tag {}
}
namespace Elementor\Modules\DynamicTags {
	class Module { const TEXT_CATEGORY = 'text'; const URL_CATEGORY = 'url'; const IMAGE_CATEGORY = 'image'; }
}
namespace Elementor {
	class Controls_Manager { const SELECT = 'select'; const NUMBER = 'number'; const TEXT = 'text'; const SWITCHER = 'switcher'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; }
	abstract class Widget_Base {
		public $s = array(); public $controles = array();
		public function __construct( $d = array(), $a = null ) {}
		protected function start_controls_section( $id, $a ) {}
		protected function end_controls_section() {}
		protected function add_control( $id, $a ) { $this->controles[ $id ] = $a; }
		protected function add_responsive_control( $id, $a ) { $this->controles[ $id ] = $a; }
		public function get_settings_for_display() { return $this->s; }
		public function registrar() { $this->register_controls(); }
		public function renderizar() { ob_start(); $this->render(); return ob_get_clean(); }
	}
}
namespace {
	require '/t/bootstrap.php';
	function get_posts( $a ) { return array( (object) array( 'ID' => 41, 'post_title' => 'Passeios – card de passeio' ) ); }
	foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto', 'grade', 'elementor' ) as $f ) { require "/p/includes/$f.php"; }
	require '/p/includes/elementor-classes.php';

	$classes = vtec_classes_tags();
	igual( 17, count( $classes ), 'uma classe por campo' );
	$nomes = array();
	foreach ( $classes as $campo => $classe ) {
		$t = new $classe();
		$nomes[] = $t->get_name();
		igual( 'viator', $t->get_group(), "$campo no grupo Viator" );
	}
	igual( 17, count( array_unique( $nomes ) ), 'nomes únicos' );
	ok( in_array( 'vtec-link-reserva', $nomes, true ), 'nome com hífen' );

	$ctx = array( 'destino' => array( 'id' => 1, 'nome' => 'Rio', 'slug' => 'rio', 'foto' => 'https://x.test/r.jpg' ), 'produto' => array( 'titulo' => 'Cristo', 'url' => 'https://exemplo.test/passeios/p/X1-cristo/', 'imagem' => 'https://x.test/c.jpg', 'codigo' => 'X1' ) );
	vtec_definir_contexto( $ctx );
	$titulo = new VTEC_Tag_Titulo();
	ob_start(); $titulo->render(); igual( 'Cristo', ob_get_clean(), 'tag de texto imprime o valor' );
	igual( array( 'text' ), $titulo->get_categories(), 'categoria texto' );
	$img = new VTEC_Tag_Imagem();
	igual( array( 'id' => '', 'url' => 'https://x.test/c.jpg' ), $img->get_value(), 'tag de imagem devolve url' );
	igual( array( 'image' ), $img->get_categories(), 'categoria imagem' );
	$url = new VTEC_Tag_Url_Passeio();
	igual( 'https://exemplo.test/passeios/p/X1-cristo/', $url->get_value(), 'tag de url' );
	igual( array( 'url' ), $url->get_categories(), 'categoria url' );

	$w = new VTEC_Widget_Grade();
	$w->registrar();
	igual( 'vtec-grade', $w->get_name(), 'nome do widget' );
	ok( isset( $w->controles['fonte'], $w->controles['modelo'], $w->controles['colunas'] ), 'controles principais' );
	igual( array( '0' => '— Card padrão do plugin —', '41' => 'Passeios – card de passeio' ), $w->controles['modelo']['options'], 'lista de modelos' );
	$GLOBALS['vtec_renderizador'] = function () { return '[c]'; };
	update_option( 'vtec_opcoes', array( 'destinos' => array( array( 'id' => 1, 'nome' => 'Rio', 'slug' => 'rio', 'foto' => 'f' ) ) ) );
	$w->s = array( 'fonte' => 'destinos', 'modelo' => '41', 'quantidade' => 12 );
	contem( '<div class="vtec-grade-item">[c]</div>', $w->renderizar(), 'widget renderiza a grade' );

	ok( vtec_elemento_dinamico( false, array( 'settings' => array( '__dynamic__' => array( 'title' => '[elementor-tag id="1" name="vtec-titulo" settings="%7B%7D"]' ) ) ) ), 'tag vtec marca o elemento como dinâmico' );
	ok( ! vtec_elemento_dinamico( false, array( 'settings' => array( '__dynamic__' => array( 'title' => '[elementor-tag name="post-title"]' ) ) ) ), 'outras tags não mudam' );
	ok( vtec_elemento_dinamico( false, array( 'widgetType' => 'vtec-grade', 'settings' => array() ) ), 'a grade é dinâmica' );
}
