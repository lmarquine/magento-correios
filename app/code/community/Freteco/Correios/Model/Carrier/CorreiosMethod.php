<?php
class Freteco_Correios_Model_Carrier_CorreiosMethod
    extends PedroTeixeira_Correios_Model_Carrier_CorreiosMethod
    implements Mage_Shipping_Model_Carrier_Interface
{

    /**
     * Collect Rates
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        // Do initial check
        if($this->_inicialCheck($request) === false)
        {
            return false;
        }

        // Check package value
        if($this->_packageValue < $this->getConfigData('min_order_value') || $this->_packageValue > $this->getConfigData('max_order_value'))
        {
            // Value limits
            $this->_throwError('valueerror', 'Value limits', __LINE__);
            return $this->_result;
        }

        // Check ZIP Code
        if(!preg_match("/^([0-9]{8})$/", $this->_toZip))
        {
            // Invalid Zip Code
            $this->_throwError('zipcodeerror', 'Invalid Zip Code', __LINE__);
            return $this->_result;
        }

        // Fix weight
        $weightCompare = $this->getConfigData('maxweight');
        if($this->getConfigData('weight_type') == 'gr')
        {
            $this->_packageWeight = number_format($this->_packageWeight/1000, 2, '.', '');
            $weightCompare = number_format($weightCompare/1000, 2, '.', '');
        }

        // Check weght
        if ($this->_packageWeight > $weightCompare)
        {
            //Weight exceeded limit
            $this->_throwError('maxweighterror', 'Weight exceeded limit', __LINE__);
            return $this->_result;
        }

        // Check weight zero
        if ($this->_packageWeight <= 0)
        {
            // Weight zero
            $this->_throwError('weightzeroerror', 'Weight zero', __LINE__);
            return $this->_result;
        }

        // Generate Volume Weight
        if($this->_generateVolumeWeight() === false)
        {
            // Dimension error
            $this->_throwError('dimensionerror', 'Dimension error', __LINE__);
            return $this->_result;
        }

        // Get post methods
        $this->_postMethods = $this->getConfigData('postmethods');
        $this->_postMethodsFixed = $this->_postMethods;
        $this->_postMethodsExplode = explode(",", $this->getConfigData('postmethods'));

        // Get quotes
        if(Mage::getStoreConfigFlag('carriers/pedroteixeira_correios/freteco_enabled')){
            if($this->_getQuotes()->getError()) {
                return $this->_result;
            }
        }else{
            if(parent::_getQuotes()->getError()) {
                return $this->_result;
            }
        }

        // Use descont codes
        $this->_updateFreeMethodQuote($request);

        // Return rates / errors
        return $this->_result;

    }

    /**
     * Get shipping quote
     *
     * @return object
     */
    protected function _getQuotes(){
        if(!Mage::getStoreConfigFlag('carriers/pedroteixeira_correios/freteco_enabled'))
        {
            return parent::_getQuotes();
        }

        $dieErrors = explode(",", $this->getConfigData('die_errors'));

        // Call Correios
        $correiosReturn = $this->_getCorreiosReturn();

        if($correiosReturn !== false){

            // Check if exist return from Correios
            $existReturn = false;

            foreach($correiosReturn as $servicos){

                // Get Correios error
                $errorId = $this->_cleanCorreiosError((string)$servicos->Erro);

                if($errorId != 0){
                    // Error, throw error message
                    if(in_array($errorId, $dieErrors)){
                        $this->_throwError('correioserror', 'Correios Error: ' . (string)$servicos->MsgErro . ' [Cod. ' . $errorId . '] [Serv. ' . (string)$servicos->Codigo . ']' , __LINE__, (string)$servicos->MsgErro . ' (Cod. ' . $errorId . ')');
                        return $this->_result;
                    }else{
                        continue;
                    }
                }

                $shippingPrice = floatval(str_replace(",",".",(string)$servicos->Valor));
                $shippingDelivery = (int)$servicos->PrazoEntrega;

                if($shippingPrice <= 0){
                    continue;
                }

                // Apend shipping
                $this->_apendShippingReturn((string)$servicos->Codigo, $shippingPrice, $shippingDelivery);
                $existReturn = true;
            }

            // All services are ignored
            if($existReturn === false){
                $this->_throwError('urlerror', 'URL Error, all services return with error', __LINE__);
                return $this->_result;
            }

        }else{
            // Error on HTTP Correios
            return $this->_result;
        }

        // Success
        if($this->_freeMethodRequest === true){
            return $this->_freeMethodRequestResult;
        }else{
            return $this->_result;
        }
    }

    /**
     * Get Correios return
     *
     * @return bool
     */
    protected function _getCorreiosReturn(){
        if(!Mage::getStoreConfigFlag('carriers/pedroteixeira_correios/freteco_enabled'))
        {
            return parent::_getCorreiosReturn();
        }

        $filename = Mage::getStoreConfig('carriers/pedroteixeira_correios/url_ws_freteco');
        $contratoCodes = explode(",", $this->getConfigData('contrato_codes'));

        try {
            $client = new Zend_Http_Client($filename);
            $client->setConfig(array(
                    'timeout' => $this->getConfigData('ws_timeout')
                ));

            $client->setParameterGet('apikey', Mage::getStoreConfig('carriers/pedroteixeira_correios/freteco_apikey'));
            $client->setParameterGet('StrRetorno', 'xml');
            $client->setParameterGet('nCdServico', $this->_postMethods);

            if($this->_volumeWeight > $this->getConfigData('volume_weight_min') && $this->_volumeWeight > $this->_packageWeight){
                $client->setParameterGet('nVlPeso', $this->_volumeWeight);
            }else{
                $client->setParameterGet('nVlPeso', $this->_packageWeight);
            }

            $client->setParameterGet('sCepOrigem', $this->_fromZip);
            $client->setParameterGet('sCepDestino', $this->_toZip);
            $client->setParameterGet('nCdFormato',1);
            $client->setParameterGet('nVlComprimento',$this->getConfigData('comprimento_sent'));
            $client->setParameterGet('nVlAltura',$this->getConfigData('altura_sent'));
            $client->setParameterGet('nVlLargura',$this->getConfigData('largura_sent'));

            if($this->getConfigData('mao_propria')){
                $client->setParameterGet('sCdMaoPropria','S');
            }else{
                $client->setParameterGet('sCdMaoPropria','N');
            }

            if($this->getConfigData('aviso_recebimento')){
                $client->setParameterGet('sCdAvisoRecebimento','S');
            }else{
                $client->setParameterGet('sCdAvisoRecebimento','N');
            }

            if($this->getConfigData('valor_declarado') || in_array($this->getConfigData('acobrar_code'), $this->_postMethodsExplode)){
                $client->setParameterGet('nVlValorDeclarado',number_format($this->_packageValue, 2, ',', '.'));
            }else{
                $client->setParameterGet('nVlValorDeclarado',0);
            }

            $contrato = false;
            foreach($contratoCodes as $contratoEach){
                if(in_array($contratoEach, $this->_postMethodsExplode)){
                    $contrato = true;
                }
            }

            if($contrato){
                if($this->getConfigData('cod_admin') == '' || $this->getConfigData('senha_admin') == ''){
                    // Need correios admin data
                    $this->_throwError('coderror', 'Need correios admin data', __LINE__);
                    return false;
                }else{
                    $client->setParameterGet('nCdEmpresa',$this->getConfigData('cod_admin'));
                    $client->setParameterGet('sDsSenha',$this->getConfigData('senha_admin'));
                }
            }

            $content = $client->request()->getBody();

            if ($content == ""){
                throw new Exception("No XML returned [" . __LINE__ . "]");
            }

            libxml_use_internal_errors(true);
            $sxe = simplexml_load_string($content);
            if (!$sxe) {
                throw new Exception("Bad XML [" . __LINE__ . "]");
            }

            // Load XML
            $xml = new SimpleXMLElement($content);

            if(count($xml->cServico) <= 0){
                throw new Exception("No tag cServico in Correios XML [" . __LINE__ . "]");
            }

            return $xml->cServico;

        } catch (Exception $e) {
            //URL Error
            $this->_throwError('urlerror', 'URL Error - ' . $e->getMessage(), __LINE__);
            return false;
        };
    }
}