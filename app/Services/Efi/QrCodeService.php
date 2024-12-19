<?php

namespace App\Services\Efi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;

class QrCodeService
{

    const ID_PAYLOAD_FORMAT_INDICATOR = '00';
    const ID_MERCHANT_ACCOUNT_INFORMATION = '26';
    const ID_MERCHANT_ACCOUNT_INFORMATION_GUI = '00';
    const ID_MERCHANT_ACCOUNT_INFORMATION_KEY = '01';
    const ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION = '02';
    const ID_MERCHANT_CATEGORY_CODE = '52';
    const ID_TRANSACTION_CURRENCY = '53';
    const ID_TRANSACTION_AMOUNT = '54';
    const ID_COUNTRY_CODE = '58';
    const ID_MERCHANT_NAME = '59';
    const ID_MERCHANT_CITY = '60';
    const ID_ADDITIONAL_DATA_FIELD_TEMPLATE = '62';
    const ID_ADDITIONAL_DATA_FIELD_TEMPLATE_TXID = '05';
    const ID_CRC16 = '63';

    /**
     * Chave do pix
     * @var string
     */
    private $chavePix;

    /**
     * Descricao da transacao
     * @var string
     */

    private $descricao;

    /**
     * Nome do titular da conta que irá receber
     * @var string
     */

    private $nomeTitularConta;

    /**
     * Cidade do titular da conta que irá receber
     * @var string
     */

    private $nomeCidadeTitularConta;

    /**
     * Id da transacao do pix
     * @var string
     */

    private $txid;

    /**
     * Valor da transacao do pix
     * @var float
     */

    private $valorTransacao;


    public function setChavePix($chavePix)
    {
        $this->chavePix = (string)$chavePix;
        return $this;
    }

    public function setDescricao($descricao)
    {
        $this->descricao = (string)$descricao;
        return $this;
    }

    public function setNomeTitularConta($nomeTitularConta){
        $this->nomeTitularConta = (string)$nomeTitularConta;
        return $this;
    }

    public function setNomeCidadeTitularConta($nomeCidadeTitularConta){
        $this->nomeCidadeTitularConta = (string)$nomeCidadeTitularConta;
        return $this;
    }

    public function setTxid($txid){
        $this->txid = (string)$txid;
        return $this;
    }

    /**
     * Valor da transacao do pix
     * @param float $valorTransacao
     */

    public function setValorTransacao($valorTransacao){
        $this->valorTransacao = (string)number_format($valorTransacao, 2, '.', '');
        return $this;
    }

    private function getValor($id, $valor)
    {
        $tamanho = str_pad(strlen($valor), 2, '0', STR_PAD_LEFT);

        return $id.$tamanho.$valor;
    }

    private function getCampoAdicionalTemplate()
    {
        $txid = $this->getValor(self::ID_ADDITIONAL_DATA_FIELD_TEMPLATE_TXID, $this->txid);

        return $this->getValor(self::ID_ADDITIONAL_DATA_FIELD_TEMPLATE, $txid);
    }

    private function getCRC16($payload) {
        //ADICIONA DADOS GERAIS NO PAYLOAD
        $payload .= self::ID_CRC16.'04';
  
        //DADOS DEFINIDOS PELO BACEN
        $polinomio = 0x1021;
        $resultado = 0xFFFF;
  
        //CHECKSUM
        if (($length = strlen($payload)) > 0) {
            for ($offset = 0; $offset < $length; $offset++) {
                $resultado ^= (ord($payload[$offset]) << 8);
                for ($bitwise = 0; $bitwise < 8; $bitwise++) {
                    if (($resultado <<= 1) & 0x10000) $resultado ^= $polinomio;
                    $resultado &= 0xFFFF;
                }
            }
        }
  
        //RETORNA CÓDIGO CRC16 DE 4 CARACTERES
        return self::ID_CRC16.'04'.strtoupper(dechex($resultado));
    }

    public function getInformacaoTitularConta()
    {
        $gui = $this->getValor(self::ID_MERCHANT_ACCOUNT_INFORMATION_GUI, 'br.gov.bcb.pix');

        $chave = $this->getValor(self::ID_MERCHANT_ACCOUNT_INFORMATION_KEY, $this->chavePix);

        $descricao = strlen($this->descricao) ? $this->getValor(self::ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION, $this->descricao) : '';

        return $this->getValor(self::ID_MERCHANT_ACCOUNT_INFORMATION, $gui.$chave.$descricao);
    }


    public function getPayload()
    {
        $payload = $this->getValor(self::ID_PAYLOAD_FORMAT_INDICATOR, '01').
                   $this->getInformacaoTitularConta().
                   $this->getValor(self::ID_MERCHANT_CATEGORY_CODE, '0000').
                   $this->getValor(self::ID_TRANSACTION_CURRENCY, '986').
                   $this->getValor(self::ID_TRANSACTION_AMOUNT, $this->valorTransacao).
                   $this->getValor(self::ID_COUNTRY_CODE, 'BR').
                   $this->getValor(self::ID_MERCHANT_NAME, $this->nomeTitularConta).
                   $this->getValor(self::ID_MERCHANT_CITY, $this->nomeCidadeTitularConta).
                   $this->getCampoAdicionalTemplate();

        //RETORNA PAYLOAD + CRC16
        return $payload.$this->getCRC16($payload);
    }

    

    public static function criarTxidComIdPlaca($id_placa)
    {
        // Certifique-se de que o ID da placa seja válido (apenas alfanumérico e no tamanho adequado)
        $id_placa = substr(preg_replace('/[^A-Za-z0-9]/', '', $id_placa), 0, 12); // Limitar o id_placa a 12 caracteres

        // Cria um prefixo único (timestamp curto + hash curto)
        $timestamp = substr(time(), -5); // Últimos 5 dígitos do timestamp
        $hash = substr(hash('sha256', uniqid($id_placa, true)), 0, 7); // Primeiro 7 caracteres do hash

        // Combine os elementos para formar o txid
        $txid = $timestamp . $hash . $id_placa;

        // Garante que não ultrapasse 24 caracteres
        return substr($txid, 0, 24);
    }


    public static function criarQr(int $idLocation, string $token)
    {


        //LogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletarComFiltro", "datahora"=>now()));

        $arquivo = "Certificados/Naise/homologacaoTeste_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $caminhoCertificado);
        }


        $url = env('URL_EFI') . "/v2/loc/" . $idLocation . "/qrcode";

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
                CURLOPT_SSL_VERIFYPEER => true, // Verifica o certificado do servidor
                CURLOPT_SSL_VERIFYHOST => 2, // Verifica o host do certificado
                CURLOPT_SSLCERT => $certificado // Define o certificado a ser usado
            )
        );

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new \Exception("Erro durante a requisição cURL: " . curl_error($ch));
          }
        curl_close($ch);
    
        $resposta = json_decode($result);

        return $resposta;
        
    }

    public static function mudarDataExpiracaoQr(int $idLocation, string $token)
    {


        //LogsService::criar(array("id_usuario"=>session()->get('id_usuario'), "tabela"=>"tipo_endereco", "funcao"=>"coletarComFiltro", "datahora"=>now()));

        $arquivo = "Certificados/Naise/homologacaoTeste_cert.pem";

        // Obtém o caminho absoluto do arquivo de certificado
        $certificado = Storage::disk('local')->path($arquivo);

        // Verifique se o arquivo realmente existe
        if (!file_exists($certificado)) {
            throw new \Exception("O arquivo de certificado não foi encontrado: " . $caminhoCertificado);
        }


        $url = env('URL_EFI') . "/v2/cobv/" . $idLocation;

        // Inicializa a sessão cURL
        $ch = curl_init($url);

        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
                CURLOPT_SSL_VERIFYPEER => true, // Verifica o certificado do servidor
                CURLOPT_SSL_VERIFYHOST => 2, // Verifica o host do certificado
                CURLOPT_SSLCERT => $certificado // Define o certificado a ser usado
            )
        );

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new \Exception("Erro durante a requisição cURL: " . curl_error($ch));
          }
        curl_close($ch);
    
        $resposta = json_decode($result);

        return $resposta;
        
    }


    public static function setarEstruturaWebhook($chaveAleatoria){
        $estrutura = array('pix' => array('receberSemChave' => true, 'chaves' => array($chaveAleatoria => array('recebimento' => array('txidObrigatorio' => false, 'qrCodeEstatico' => array('recusarTodos' => false), 'webhook' => array('notificacao' => array('tarifa' => true, 'pagador' => true), 'notificar' => array('pixSemTxid' => true)))))));
        return $estrutura;
    }

}
