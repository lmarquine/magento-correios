<?php
/**
 * @author: Ricardo Martins
 * Date: 5/3/15
 */ 
class Freteco_Correios_Model_Carrier_CorreiosMethod extends PedroTeixeira_Correios_Model_Carrier_CorreiosMethod {
    /**
     * Get Correios return
     *
     * @return bool|SimpleXMLElement[]
     *
     * @throws Exception
     */
    protected function _getCorreiosReturn()
    {
        $filename      = $this->getConfigData('url_ws_correios');
        if(Mage::getStoreConfigFlag('carriers/pedroteixeira_correios/freteco_enabled'))
        {
            $filename = Mage::getStoreConfig('carriers/pedroteixeira_correios/url_ws_freteco');
        }

        $contratoCodes = explode(',', $this->getConfigData('contrato_codes'));

        try {
            $client = new Zend_Http_Client($filename);
            $client->setConfig(
                array(
                    'timeout' => $this->getConfigData('ws_timeout'),
                    'adapter' => Mage::getModel('pedroteixeira_correios/http_client_adapter_socket')
                )
            );

            $client->setParameterGet('StrRetorno', 'xml');
            $client->setParameterGet('nCdServico', $this->_postMethods);
            $client->setParameterGet('nVlPeso', $this->_packageWeight);
            $client->setParameterGet('sCepOrigem', $this->_fromZip);
            $client->setParameterGet('sCepDestino', $this->_toZip);
            $client->setParameterGet('nCdFormato', 1);
            $client->setParameterGet('nVlComprimento', $this->_midSize);
            $client->setParameterGet('nVlAltura', $this->_midSize);
            $client->setParameterGet('nVlLargura', $this->_midSize);

            if(Mage::getStoreConfigFlag('carriers/pedroteixeira_correios/freteco_enabled'))
            {
                $client->setParameterGet('apikey', Mage::getStoreConfig('carriers/pedroteixeira_correios/freteco_apikey'));
            }

            if ($this->getConfigData('mao_propria')) {
                $client->setParameterGet('sCdMaoPropria', 'S');
            } else {
                $client->setParameterGet('sCdMaoPropria', 'N');
            }

            if ($this->getConfigData('aviso_recebimento')) {
                $client->setParameterGet('sCdAvisoRecebimento', 'S');
            } else {
                $client->setParameterGet('sCdAvisoRecebimento', 'N');
            }

            if ($this->getConfigData('valor_declarado')
                || in_array($this->getConfigData('acobrar_code'), $this->_postMethodsExplode)
            ) {
                $client->setParameterGet('nVlValorDeclarado', number_format($this->_packageValue, 2, ',', ''));
            } else {
                $client->setParameterGet('nVlValorDeclarado', 0);
            }

            $contrato = false;
            foreach ($contratoCodes as $contratoEach) {
                if (in_array($contratoEach, $this->_postMethodsExplode)) {
                    $contrato = true;
                }
            }

            if ($contrato) {
                if ($this->getConfigData('cod_admin') == '' || $this->getConfigData('senha_admin') == '') {
                    $this->_throwError('coderror', 'Need correios admin data', __LINE__);
                    return false;
                } else {
                    $client->setParameterGet('nCdEmpresa', $this->getConfigData('cod_admin'));
                    $client->setParameterGet('sDsSenha', $this->getConfigData('senha_admin'));
                }
            }

            $content = $client->request()->getBody();

            if ($content == '') {
                throw new Exception('No XML returned [' . __LINE__ . ']');
            }

            libxml_use_internal_errors(true);
            $sxe = simplexml_load_string($content);
            if (!$sxe) {
                throw new Exception('Bad XML [' . __LINE__ . ']');
            }

            $xml = new SimpleXMLElement($content);

            if (count($xml->cServico) <= 0) {
                throw new Exception('No tag cServico in Correios XML [' . __LINE__ . ']');
            }

            return $xml->cServico;
        } catch (Exception $e) {
            $this->_throwError('urlerror', 'URL Error - ' . $e->getMessage(), __LINE__);
            return false;
        }
    }

}