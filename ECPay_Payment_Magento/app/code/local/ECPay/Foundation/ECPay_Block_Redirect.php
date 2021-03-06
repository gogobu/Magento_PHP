<?php
require_once(Mage::getBaseDir('app') . '/code/local/ECPay/ECPay.Payment.Integration.php');

class ECPay_Block_Redirect extends Mage_Core_Block_Template {
    protected $paymentName = 'paymentName';
    protected $choosePayment = 'choosePayment';
    protected $sendExtend = array();

    private function _getSession() {
        return Mage::getSingleton('checkout/session');
    }

    private function _getOrder() {
        if ($this->getOrder()) {
            return $this->getOrder();
        } elseif ($orderIncrementId = $this->_getSession()->getLastRealOrderId()) {
            return Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        } else {
            return null;
        }
    }

    private function _getConfigData($keyword) {
        return Mage::getStoreConfig('payment/' . $this->paymentName . '/' . $keyword, null);
    }

    protected function _getUrl($name = '') {
        if ($name === '') {
            return Mage::getUrl('');
        } else {
            return Mage::getUrl($this->paymentName . '/processing/' . $name);
        }
    }

    protected function _getTotal() {
        $order = $this->_getOrder();
        return round($order->getGrandTotal());
    }

    protected function AutoSubmit() {
        $oOrder = $this->_getOrder();

        if ($oOrder) {
            try {
                $oPayment = new ECPay_AllInOne();
                $oPayment->ServiceURL = ($this->_getConfigData('test_mode') ? 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut' : 'https://payment.ecpay.com.tw/Cashier/AioCheckOut');
                $oPayment->HashKey = $this->_getConfigData('hash_key');
                $oPayment->HashIV = $this->_getConfigData('hash_iv');
                $oPayment->MerchantID = $this->_getConfigData('merchant_id');

                $oPayment->Send['ReturnURL'] = $this->_getUrl('response');
                $oPayment->Send['ClientBackURL'] = $this->_getUrl('');
                $oPayment->Send['OrderResultURL'] = $this->_getUrl('result');
                $oPayment->Send['MerchantTradeNo'] = ($this->_getConfigData('test_mode') ? $this->_getConfigData('test_order_prefix') : '') . $oOrder->getIncrementId();
                $oPayment->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
                $oPayment->Send['TotalAmount'] = round($oOrder->getGrandTotal());
                $oPayment->Send['TradeDesc'] = 'ecpay_magento_module_1.1.0316';
                $oPayment->Send['ChoosePayment'] = $this->choosePayment;
                $oPayment->Send['Remark'] = '';
                
                $oPayment->Send['NeedExtraPaidInfo'] = ECPay_ExtraPaymentInfo::No;

                array_push(
                    $oPayment->Send['Items'],
                    array(
                        'Name' => Mage::helper($this->paymentName)->__('網路商品一批'),
                        'Price' => round($oOrder->getGrandTotal()),
                        'Currency' => Mage::app()->getLocale()->currency(
                                Mage::app()->getStore()->getCurrentCurrencyCode()
                            )->getSymbol(),
                        'Quantity' => 1,
                        'URL' => '')
                    );
                if ($this->sendExtend !== array()) {
                    $oPayment->SendExtend = $this->sendExtend;
                }
                $oPayment->CheckOut();
            } catch (Exception $e) {
                Mage::throwException($e->getMessage());
            }
        }

        return ;
    }

}