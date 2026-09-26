<?php
/** Classes que estendem o Elementor. Carregado só pelos ganchos do Elementor (ou pelos testes, com stubs). */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as Tags;

abstract class VTEC_Tag_Texto extends \Elementor\Core\DynamicTags\Tag {
	const CAMPO = '';
	public function get_name() { return 'vtec-' . str_replace( '_', '-', static::CAMPO ); }
	public function get_title() { return vtec_campos_tags()[ static::CAMPO ][0]; }
	public function get_group() { return 'viator'; }
	public function get_categories() { return array( Tags::TEXT_CATEGORY ); }
	public function render() { echo vtec_valor_campo( static::CAMPO, vtec_contexto_ou_exemplo() ); } // phpcs:ignore -- escapado em vtec_valor_campo
}

abstract class VTEC_Tag_Link extends \Elementor\Core\DynamicTags\Data_Tag {
	const CAMPO = '';
	public function get_name() { return 'vtec-' . str_replace( '_', '-', static::CAMPO ); }
	public function get_title() { return vtec_campos_tags()[ static::CAMPO ][0]; }
	public function get_group() { return 'viator'; }
	public function get_categories() { return array( Tags::URL_CATEGORY ); }
	public function get_value( array $options = array() ) { return vtec_valor_campo( static::CAMPO, vtec_contexto_ou_exemplo() ); }
}

abstract class VTEC_Tag_Foto extends \Elementor\Core\DynamicTags\Data_Tag {
	const CAMPO = '';
	public function get_name() { return 'vtec-' . str_replace( '_', '-', static::CAMPO ); }
	public function get_title() { return vtec_campos_tags()[ static::CAMPO ][0]; }
	public function get_group() { return 'viator'; }
	public function get_categories() { return array( Tags::IMAGE_CATEGORY ); }
	public function get_value( array $options = array() ) { return array( 'id' => '', 'url' => vtec_valor_campo( static::CAMPO, vtec_contexto_ou_exemplo() ) ); }
}

class VTEC_Tag_Titulo extends VTEC_Tag_Texto { const CAMPO = 'titulo'; }
class VTEC_Tag_Descricao extends VTEC_Tag_Texto { const CAMPO = 'descricao'; }
class VTEC_Tag_Preco extends VTEC_Tag_Texto { const CAMPO = 'preco'; }
class VTEC_Tag_Duracao extends VTEC_Tag_Texto { const CAMPO = 'duracao'; }
class VTEC_Tag_Galeria extends VTEC_Tag_Texto { const CAMPO = 'galeria'; }
class VTEC_Tag_Inclusoes extends VTEC_Tag_Texto { const CAMPO = 'inclusoes'; }
class VTEC_Tag_Exclusoes extends VTEC_Tag_Texto { const CAMPO = 'exclusoes'; }
class VTEC_Tag_Encontro extends VTEC_Tag_Texto { const CAMPO = 'encontro'; }
class VTEC_Tag_Cancelamento extends VTEC_Tag_Texto { const CAMPO = 'cancelamento'; }
class VTEC_Tag_Informacoes extends VTEC_Tag_Texto { const CAMPO = 'informacoes'; }
class VTEC_Tag_Nota extends VTEC_Tag_Texto { const CAMPO = 'nota'; }
class VTEC_Tag_Destino_Nome extends VTEC_Tag_Texto { const CAMPO = 'destino_nome'; }
class VTEC_Tag_Imagem extends VTEC_Tag_Foto { const CAMPO = 'imagem'; }
class VTEC_Tag_Destino_Foto extends VTEC_Tag_Foto { const CAMPO = 'destino_foto'; }
class VTEC_Tag_Url_Passeio extends VTEC_Tag_Link { const CAMPO = 'url_passeio'; }
class VTEC_Tag_Link_Reserva extends VTEC_Tag_Link { const CAMPO = 'link_reserva'; }
class VTEC_Tag_Destino_Url extends VTEC_Tag_Link { const CAMPO = 'destino_url'; }

class VTEC_Widget_Grade extends \Elementor\Widget_Base {
	public function get_name() { return 'vtec-grade'; }
	public function get_title() { return 'Viator – Grade'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return array( 'general' ); }
	public function get_keywords() { return array( 'viator', 'passeios', 'grade', 'destinos' ); }
	public function get_style_depends() { return array( 'viator-tec' ); }
	public function get_script_depends() { return array( 'viator-tec', 'viator-tec-protegido' ); }
	protected function is_dynamic_content(): bool { return true; }

	protected function register_controls() {
		$this->start_controls_section( 'vtec_conteudo', array( 'label' => 'Conteúdo' ) );
		$this->add_control( 'fonte', array(
			'label' => 'Mostrar', 'type' => Controls_Manager::SELECT, 'default' => 'destino_atual',
			'options' => array( 'destinos' => 'Destinos da lista', 'destino_atual' => 'Passeios do destino atual', 'destino' => 'Passeios de um destino' ),
		) );
		$this->add_control( 'destino', array(
			'label' => 'Destino', 'type' => Controls_Manager::SELECT, 'options' => vtec_opcoes_destinos_select(), 'condition' => array( 'fonte' => 'destino' ),
		) );
		$this->add_control( 'modelo', array(
			'label' => 'Modelo do card', 'type' => Controls_Manager::SELECT, 'default' => '0', 'options' => vtec_opcoes_modelos_select(),
		) );
		$this->add_control( 'quantidade', array( 'label' => 'Quantidade', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 50, 'default' => 12 ) );
		$this->add_control( 'ordem', array(
			'label' => 'Ordem', 'type' => Controls_Manager::SELECT, 'default' => 'avaliacao',
			'options' => array( 'avaliacao' => 'Mais bem avaliados', 'preco' => 'Menor preço' ), 'condition' => array( 'fonte!' => 'destinos' ),
		) );
		$this->add_control( 'mostrar_ordem', array(
			'label' => 'Botões de ordenar', 'type' => Controls_Manager::SWITCHER, 'default' => '', 'condition' => array( 'fonte!' => 'destinos' ),
		) );
		$this->add_control( 'mais_texto', array( 'label' => 'Texto do "carregar mais"', 'type' => Controls_Manager::TEXT, 'default' => 'Carregar mais' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'vtec_layout', array( 'label' => 'Layout', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'colunas', array(
			'label' => 'Colunas', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6,
			'default' => 3, 'tablet_default' => 2, 'mobile_default' => 1,
			'selectors' => array( '{{WRAPPER}} .vtec-grade-el' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
		) );
		$this->add_responsive_control( 'espaco', array(
			'label' => 'Espaço entre cards', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'default' => array( 'unit' => 'px', 'size' => 30 ),
			'selectors' => array( '{{WRAPPER}} .vtec-grade-el' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		echo vtec_render_grade( $this->get_settings_for_display(), vtec_contexto_ou_exemplo() ); // phpcs:ignore -- HTML escapado na grade
	}
}
