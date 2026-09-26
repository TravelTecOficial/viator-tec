<?php
require '/t/bootstrap.php';
foreach ( array( 'opcoes', 'api', 'cache', 'dados', 'servico', 'render', 'contexto' ) as $f ) { require "/p/includes/$f.php"; }

// sem destinos e sem chave: exemplo fixo, sem chamar a API
$ex = vtec_contexto_exemplo();
igual( 'Nome do passeio (exemplo)', $ex['produto']['titulo'], 'exemplo fixo sem destinos' );
igual( 0, count( $GLOBALS['vt_http_log'] ), 'exemplo sem destinos não chama a API' );
igual( $ex, vtec_contexto_ou_exemplo(), 'sem contexto usa o exemplo' );

$view = vtec_produto_view( fixture( 'product' ), 957.54 );
ok( '' !== $view['imagem'] && '' !== $view['url'], 'view tem imagem e url' );
$d   = array( 'id' => 684, 'nome' => 'Las <Vegas>', 'slug' => 'las-vegas', 'foto' => 'https://x.test/f.jpg' );
$ctx = array( 'destino' => $d, 'produto' => $view );
vtec_definir_contexto( $ctx );
igual( $ctx, vtec_contexto_ou_exemplo(), 'contexto definido vence o exemplo' );

igual( 'Vale Privado do Fogo Caminhadas e Aventura', vtec_valor_campo( 'titulo', $ctx ), 'título' );
igual( 'a partir de R$ 957,54, por pessoa', vtec_valor_campo( 'preco', $ctx ), 'preço no formato dos roteiros' );
contem( '<p>Passe o dia', vtec_valor_campo( 'descricao', $ctx ), 'descrição em parágrafos' );
contem( '<div class="rt-galeria"', vtec_valor_campo( 'galeria', $ctx ), 'galeria no HTML dos roteiros' );
contem( '<div class="rt-galeria-track"', vtec_valor_campo( 'galeria', $ctx ), 'trilho da galeria' );
contem( '<li>Guia profissional</li>', vtec_valor_campo( 'inclusoes', $ctx ), 'inclusões em lista' );
contem( '<li>Gorjetas</li>', vtec_valor_campo( 'exclusoes', $ctx ), 'exclusões em lista' );
contem( '24 horas', vtec_valor_campo( 'cancelamento', $ctx ), 'cancelamento' );
contem( 'Moapa', vtec_valor_campo( 'encontro', $ctx ), 'ponto de encontro' );
igual( '<span class="vtec-nota" data-codigo="56549P1"></span>', vtec_valor_campo( 'nota', $ctx ), 'nota só como espaço' );
contem( 'pid=', vtec_valor_campo( 'link_reserva', $ctx ), 'link de afiliado' );
igual( $view['url'], vtec_valor_campo( 'url_passeio', $ctx ), 'url do passeio' );
contem( 'tripadvisor.com', vtec_valor_campo( 'imagem', $ctx ), 'foto do passeio' );
igual( 'Las &lt;Vegas&gt;', vtec_valor_campo( 'destino_nome', $ctx ), 'nome do destino escapado' );
igual( 'https://exemplo.test/passeios/las-vegas/', vtec_valor_campo( 'destino_url', $ctx ), 'url do destino' );
igual( 'https://x.test/f.jpg', vtec_valor_campo( 'destino_foto', $ctx ), 'foto do destino' );
igual( '', vtec_valor_campo( 'nao-existe', $ctx ), 'campo desconhecido vazio' );

$card = vtec_card( fixture( 'search' )['products'][0] );
$card['titulo'] = 'Tour "top" <b>';
$c2 = array( 'destino' => $d, 'produto' => $card );
igual( 'Tour &quot;top&quot; &lt;b&gt;', vtec_valor_campo( 'titulo', $c2 ), 'título do card escapado' );
igual( '', vtec_valor_campo( 'descricao', $c2 ), 'card não tem descrição' );
igual( '', vtec_valor_campo( 'preco', array( 'destino' => $d, 'produto' => array( 'preco' => '' ) ) ), 'sem preço não mostra "a partir de"' );
igual( '', vtec_html_galeria( array(), 'x' ), 'galeria vazia' );
igual( '', vtec_html_ul( array() ), 'lista vazia' );

$campos = vtec_campos_tags();
igual( 17, count( $campos ), '17 campos dinâmicos' );
igual( 'imagem', $campos['imagem'][1], 'foto é campo de imagem' );
igual( 'url', $campos['link_reserva'][1], 'reservar é campo de URL' );
vtec_definir_contexto( null );
igual( null, vtec_contexto(), 'contexto limpo' );
