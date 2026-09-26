<?php
require '/t/bootstrap.php';
foreach ( array( 'dados', 'render' ) as $f ) { require "/p/includes/$f.php"; }

$h = vtec_html_destinos( array( array( 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => 'https://x.test/f.jpg' ), array( 'nome' => 'Sem <foto>', 'slug' => 'sem-foto', 'foto' => '' ) ) );
contem( 'href="https://exemplo.test/passeios/las-vegas/"', $h, 'link do destino' );
contem( 'Sem &lt;foto&gt;', $h, 'nome escapado' );
nao_contem( 'src=""', $h, 'sem img vazia' );

$card = array( 'codigo' => 'A1B', 'titulo' => 'Tour "top" <b>', 'url' => 'https://exemplo.test/passeios/p/A1B-tour/', 'imagem' => '', 'duracao' => '', 'preco' => '', 'cancelamento_gratis' => true, 'nota' => 4.9, 'total' => 321 );
$c = vtec_html_cards( array( $card ) );
contem( 'Tour &quot;top&quot; &lt;b&gt;', $c, 'título escapado' );
nao_contem( 'src=""', $c, 'card sem imagem não tem img vazia' );
nao_contem( 'R$', $c, 'card sem preço não mostra R$' );
nao_contem( '321', $c, 'total de avaliações fora do HTML' );
nao_contem( '4,9', $c, 'nota fora do HTML' );
contem( 'data-codigo="A1B"', $c, 'lugar da nota para o JS protegido' );
contem( 'Cancelamento grátis', $c, 'selo de cancelamento' );

$d = vtec_html_destino( array( 'nome' => 'Las Vegas', 'slug' => 'las-vegas' ), array_fill( 0, 12, $card ), 30, 'preco', 12 );
contem( '<h1', $d, 'título da página' );
contem( 'Passeios em Las Vegas', $d, 'título com destino' );
contem( 'data-inicio="13"', $d, 'carregar mais começa no 13' );
contem( 'A reserva e o pagamento são feitos no site da Viator.', $d, 'aviso fixo' );
contem( 'aria-current="true"', $d, 'ordem atual marcada' );
nao_contem( 'vtec-mais', vtec_html_destino( array( 'nome' => 'X', 'slug' => 'x' ), array( $card ), 1, 'avaliacao', 12 ), 'sem botão quando cabe tudo' );
contem( 'Nenhum passeio', vtec_html_destino( array( 'nome' => 'X', 'slug' => 'x' ), array(), 0, 'avaliacao', 12 ), 'destino vazio' );

$v = vtec_produto_view( fixture( 'product' ), 5065.2 );
$p = vtec_html_produto( $v );
contem( 'Reservar na Viator', $p, 'botão reservar' );
contem( 'target="_blank" rel="noopener sponsored"', $p, 'nova aba e rel de afiliado' );
contem( esc_url( $v['link'] ), $p, 'link de afiliado' );
contem( 'R$ 5.065,20', $p, 'preço' );
contem( 'A reserva e o pagamento são feitos no site da Viator.', $p, 'aviso fixo' );
contem( 'data-codigo="56549P1"', $p, 'nota pelo JS' );
nao_contem( '207', $p, 'total de avaliações fora do HTML' );
$v['preco'] = '';
nao_contem( 'A partir de', vtec_html_produto( $v ), 'sem preço não mostra "a partir de"' );
contem( 'indisponíveis no momento', vtec_html_indisponivel(), 'mensagem de indisponível' );

// Campo do autocompletar: a lista (<ul>) não pode ficar dentro de <p> — o navegador a expulsa e ela some da tela.
$campo = vtec_html_campo_destino();
contem( 'id="vtec-procurar"', $campo, 'campo de busca' );
contem( 'id="vtec-sugestoes"', $campo, 'lista de sugestões' );
nao_contem( '<p', $campo, 'nada de <p> em volta da lista' );
ok( 1 === preg_match( '#<div class="vtec-auto">\s*<input[^>]+id="vtec-procurar"[^>]*>\s*<ul id="vtec-sugestoes"#', $campo ), 'lista logo depois do campo, dentro do mesmo div' );
