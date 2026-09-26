<?php
require '/t/bootstrap.php';
require '/p/includes/opcoes.php';

igual( 'sandbox', vtec_opcoes()['ambiente'], 'padrão é sandbox' );
igual( 'https://api.sandbox.viator.com/partner', vtec_base_url(), 'base sandbox' );

$atual = vtec_opcoes_padrao();
$atual['chave_sandbox'] = 'CHAVE-VELHA';
$s = vtec_sanitizar_opcoes( array(
	'ambiente' => 'producao', 'chave_sandbox' => '', 'chave_producao' => ' nova-prod ', 'campanha' => 'rede tur+1', 'por_pagina' => '999',
	'destino_id' => array( '684', '', '479', 'abc' ), 'destino_nome' => array( 'Las Vegas', 'Sem id', 'Paris', 'X' ),
	'destino_slug' => array( '', '', 'Paris!', '' ), 'destino_foto' => array( 'https://x.test/a.jpg', '', '', '' ),
), $atual );
igual( 'producao', $s['ambiente'], 'ambiente produção' );
igual( 'CHAVE-VELHA', $s['chave_sandbox'], 'chave vazia no formulário mantém a salva' );
igual( 'nova-prod', $s['chave_producao'], 'chave nova sem espaços' );
igual( 'rede-tur-1', $s['campanha'], 'campanha só com letras, números e hífen' );
igual( 50, $s['por_pagina'], 'por página no máximo 50' );
igual( 2, count( $s['destinos'] ), 'linhas sem id numérico ou sem nome são descartadas' );
igual( array( 'id' => 684, 'nome' => 'Las Vegas', 'slug' => 'las-vegas', 'foto' => 'https://x.test/a.jpg' ), $s['destinos'][0], 'slug gerado pelo nome' );
igual( 'paris', $s['destinos'][1]['slug'], 'slug informado é limpo' );

$s2 = vtec_sanitizar_opcoes( array( 'ambiente' => 'hack', 'destino_id' => array( '1', '2', '3' ), 'destino_nome' => array( 'Rio', 'Rio', 'P' ), 'destino_slug' => array( '', '', 'p' ) ), $atual );
igual( 'sandbox', $s2['ambiente'], 'ambiente inválido vira sandbox' );
igual( array( 'rio', 'rio-2', 'p-2' ), array_column( $s2['destinos'], 'slug' ), 'slugs únicos e "p" é reservado' );

update_option( 'vtec_opcoes', $s );
igual( 'Las Vegas', vtec_destino_por_slug( 'las-vegas' )['nome'], 'destino por slug' );
igual( null, vtec_destino_por_slug( 'nao-existe' ), 'slug inexistente devolve null' );
igual( 'nova-prod', vtec_chave_atual(), 'chave do ambiente atual' );
igual( 'https://api.viator.com/partner', vtec_base_url(), 'base produção' );
