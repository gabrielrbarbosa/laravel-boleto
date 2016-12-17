<?php
namespace Eduardokum\LaravelBoleto\Boleto\Banco;

use Eduardokum\LaravelBoleto\Boleto\AbstractBoleto;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Util;

class Bancoob extends AbstractBoleto implements BoletoContract
{
    const BANCOBB_CONST_NOSSO_NUMERO = "3197";
    /**
     * Código do banco
     * @var string
     */
    protected $codigoBanco = self::COD_BANCO_BANCOOB;
    /**
     * Define as carteiras disponíveis para este banco
     * @var array
     */
    protected $carteiras = array('1','3');
    /**
     * Espécie do documento, coódigo para remessa
     * @var string
     */
    protected $especiesCodigo = [
        'DM' => '01',
        'NP' => '02',
        'DS' => '12',
    ];
    /**
     * Define o número do convênio (4, 6 ou 7 caracteres)
     * @var string
     */
    protected $convenio;
    /**
     * Defgine o numero da variação da carteira.
     * @var string
     */
    protected $variacao_carteira;
    /**
     * Define o número do convênio. Sempre use string pois a quantidade de caracteres é validada.
     *
     * @param string $convenio
     * @return BancoDoBrasil
     */
    public function setConvenio($convenio)
    {
        $this->convenio = $convenio;
        return $this;
    }
    /**
     * Retorna o número do convênio
     *
     * @return string
     */
    public function getConvenio()
    {
        return $this->convenio;
    }
    /**
     * Define o número da variação da carteira, para saber quando utilizar o nosso numero de 17 posições.
     *
     * @param string $variacao_carteira
     * @return BancoDoBrasil
     */
    public function setVariacaoCarteira($variacao_carteira)
    {
        $this->variacao_carteira = (int) $variacao_carteira;
        return $this;
    }
    /**
     * Retorna o número da variacao de carteira
     *
     * @return string
     */
    public function getVariacaoCarteira()
    {
        return $this->variacao_carteira;
    }
    /**
     * Método que valida se o banco tem todos os campos obrigadotorios preenchidos
     */
    public function isValid()
    {
        if(
            empty($this->numero) ||
            empty($this->convenio) ||
            empty($this->carteira)
        )
        {
            return false;
        }
        return true;
    }
    /**
     * Gera o Nosso Número.
     *
     * @throws \Exception
     * @return string
     */
    protected function gerarNossoNumero()
    {
        $agencia = $this->getAgencia();
        $conta = $this->getConta().$this->getContaDv();
        $numero_boleto = $this->getNumero();

        $numero = Util::numberFormatGeral($agencia, 4).Util::numberFormatGeral($conta, 10).Util::numberFormatGeral($numero_boleto, 7);

        return $numero;
    }
    /**
     * Método que retorna o nosso numero usado no boleto. alguns bancos possuem algumas diferenças.
     *
     * @return string
     */
    public function getNossoNumeroBoleto()
    {
        $numero = $this->getNossoNumero();
        $constante = str_repeat(self::BANCOBB_CONST_NOSSO_NUMERO, 6);
        $soma = 0;

        for ($i=0; $i < strlen($numero); $i++) {
            if ((int)$numero[$i] > 0) {
                $soma += ((int)$numero[$i] * (int)$constante[$i]);
            }
        }

        $resto = $soma % 11;
        $digito_verificador = 0;

        if (($resto != 0) && ($resto != 1)){
            $digito_verificador = 11 - $resto;
        }

        $nosso_numero = $this->getNumero().'-'.$digito_verificador;

        return $nosso_numero;
    }
    /**
     * Método para gerar o código da posição de 20 a 44
     *
     * @return string
     * @throws \Exception
     */
    protected function getCampoLivre()
    {
        if ($this->campoLivre) {
            return $this->campoLivre;
        }
        $length = strlen($this->getConvenio());
        $nossoNumero = $this->gerarNossoNumero();
        // Apenas para convênio com 6 dígitos, modalidade sem registro - carteira 16 e 18 (definida para 21)
        if (strlen($this->getNumero()) > 10) {
            if ($length == 6 and in_array($this->getCarteira(),['16','18']) and Util::numberFormatGeral($this->getVariacaoCarteira(), 3) == '017') {
                // Convênio (6) + Nosso número (17) + Carteira (2)
                return $this->campoLivre = Util::numberFormatGeral($this->getConvenio(), 6) . $nossoNumero . '21';
            } else {
                throw new \Exception('Só é possível criar um boleto com mais de 10 dígitos no nosso número quando a carteira é 21 e o convênio possuir 6 dígitos.');
            }
        }
        switch ($length) {
            case 4:
            case 6:
                // Nosso número (11) + Agencia (4) + Conta (8) + Carteira (2)
                return $this->campoLivre =  $nossoNumero . Util::numberFormatGeral($this->getAgencia(), 4) . Util::numberFormatGeral($this->getConta(), 8) . Util::numberFormatGeral($this->getCarteira(), 2);
            case 7:
                // Zeros (6) + Nosso número (17) + Carteira (2)
                return $this->campoLivre =  '000000' . $nossoNumero . Util::numberFormatGeral($this->getCarteira(), 2);
        }
        throw new \Exception('O código do convênio precisa ter 4, 6 ou 7 dígitos!');
    }
}