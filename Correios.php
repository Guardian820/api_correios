<?php

require 'simple_html_dom.php';

/**
 * @package	API Correios
 * @author	Adão Duque - adaoduquesn@gmail.com
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @since	Version 1.0.0
 * @filesource
 */
class Correios {
		
	/**
	 * Código de rastreio fornecido pelo correios
	 */
	protected $codRastreio  =  null;

	/**
	 * Usaremos esse atributo para instanciar a classe Simple Html Dom
	 */
	protected $Dom          =  null;

	/**
	 * Inicializa a classe Correios e recebe como parametro o código do rastreamento da encomenda
	 * @param  STRING $code  -  Código de rastreamento fornecido pelo Correios
	 * @return VOID
	 * @access PUBLIC
	 */
	public function __construct( $code ) {

		//Recebe o código do rastreamento
		$this->codRastreio  =  $code;

	}

	/**
	 * O método realiza uma chamada a URL dos correios, obtem os dados e processa os mesmos a fim de 
	 * extrair somente o desejado, que são os dados referente a encomenda
	 * @param  NULL
	 * @return ARRAY
	 * @access PUBLIC
	 */
	public function getRastreio() {

        //Seta a URL de consulta
        $url   =  'http://websro.correios.com.br/sro_bin/txect01$.QueryList?P_LINGUA=001&P_TIPO=001&P_COD_UNI=';

        //Seta o código de rastreamento junto a URL
        $url  .=   $this->codRastreio;

        //Inicializa o Curl
        $curl  =   curl_init();

        //Seta a URL de consulta
        curl_setopt( $curl, CURLOPT_URL, $url );

        //Seta que não queremos verifição de SSL
        curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, 0 );

        //Seta o User Agent
        curl_setopt( $curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:10.0) Gecko/20100101 Firefox/10.0' );

        //Seta que desejamos o retorno da consulta
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

        //Realiza a chamada e obtem seu retorno
        $data  =  curl_exec( $curl );

        //Fecha a chamada
        curl_close( $curl );

        /**
         * Agora vamos precisar da classe Simple Html Dom, ela se muito útil para filtrarmos somente o que queremos
         * Vamos instanciar ela
         */
       	$this->Dom  =   new simple_html_dom();

       	//Atribui os dados retornados no ao atributo Dom
       	$html       =   $this->Dom->load( $data );

        //Ininicializa o array que vai conter os dados da tabela de rastreamento
        $rows       =   array();

        //Inicializa o array que vai conter os dados do rastreio que serão retornados
        $data       =   array();

        /**
         * No valor retornado do Curl, temos uma tabela html, vamos extrair seus dados
         * Hora de extrair as TR da tabela
         */
        foreach( $html->find('tr') as $row ) {

            //Incializa o array que vai conter os dados da TR
            $tdText  =  array();

            //Hora de extrair dessa TR todos as TD
            foreach($row->find('td') as $cell) {

                //Obtem o texto da TD
                $tdText[] = $cell->plaintext;

            }

            //Atribui ao array os dados extraidos da TD
            $rows[] = $tdText;

        }

        //Inicializa o contador de interações do looping
        $i     =  0;    

        //Inicializa o contador de indices do array principal
        $j     =  0;

        //Extraindo os dados
        foreach ($rows as $key => $value) {

            /**
             * Verifica se o $i é maior que 0, pois o indice 0 é o texto Data, Local e Situação
             * Verifica também se o $value contem 3 indices, pois as vezes o correios usa colspan e então buga o Simple html Dom
             * O problema é o seguinte, imagine a tabela
             * <table>
             *      <tr><td>Data</td><td>Local</td><td>Situação</td></tr>
             *       <tr rowspan="2">
             *           <td>17/02/2016 07:10</td>
             *           <td>CTE VILA MARIA - Sao Paulo/SP</td>
             *           <td><font color="000000">Encaminhado</font></td>
             *       </tr>
             *       <tr>
             *          <td colspan="2">Encaminhado para CEE VILA GUILHERME - Sao Paulo/SP</td>
             *       </tr>                        
             * <table>
             * Desta forma a classe Simple Html Dom retorna o seguinte:
             * array( 0 => array( 0 => '17/02/2016 07:10', 1 => 'CTE VILA MARIA - Sao Paulo/SP', 2 => 'Encaminhado' ),
             *        1 => array( 0 => 'Encaminhado para CEE VILA GUILHERME - Sao Paulo/SP' )
             *       )
             *
             * Creio que não era para ser assim, era para retornar o que tem no índice 0 e acrescentar o que tem no indice 1, desta forma:
             * array( 0 => array( 0 => '17/02/2016 07:10', 1 => 'CTE VILA MARIA - Sao Paulo/SP', 2 => 'Encaminhado' ), 
             *        1 => array( 0 => '17/02/2016 07:10', 1 => 'Encaminhado para CEE VILA GUILHERME - Sao Paulo/SP', 2 => 'Encaminhado' )
             *       )
             *
             * A classe Simple Html Dom é perfeita, gosto muito dela, não sei como ela interpreta as TR com rowspan, mas da forma abaixo
             * contorna o problema, então vamos continuar
             */
            if( $i > 0 && count( $value ) == 3 ) {

                //Prepara para atribuir os dados extraidos
                $data[$j]  =  array(
                                  'data'      =>  $value[0],
                                  'local'     =>  $value[1],
                                  'situacao'  =>  $value[2]
                              );  

	            //Incrementa o contador
	            $j++; 

            }else if( count( $value ) == 1 ) {

                //Ao entrar aqui sabemos que é por causa do problema descrito acuma, não tem problema
                $data[$j]  =  array(
                                  'data'      =>  $data[$j-1]['data'], //Obtem o valor do indice anterior
                                  'local'     =>  $rows[$key][0], //Obtem o valor corrente
                                  'situacao'  =>  $data[$j-1]['situacao']  //Obtem o valor do indice anterior
                              );
	            //Incrementa o contador
	            $j++; 

            }

            //Incrementa o contador
            $i++;

        }

        //Retorna os dados
        return $data;

	}

}


/**
 * Hora de testar
 *
 */
$Correios  =   new Correios( 'DU184795359BR' );

/**
 * Vamos rastrear o objeto e imprimir o array
 * A partir dai você pode fazer o que quiser com o retorno.
 * Valeu!!!
 * Sujestões: adaoduquesn@gmail.com
 */
print_r( $Correios->getRastreio() );