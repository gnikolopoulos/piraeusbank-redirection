<?php

namespace Natso\Piraeus\Helper;

use Magento\Payment\Helper\Data as PaymentHelper;

class WebService extends \Magento\Framework\App\Helper\AbstractHelper
{
	private $paymentHelper;

    public function __construct(
        PaymentHelper $paymentHelper
    ) {
        $this->paymentHelper = $paymentHelper;
    }

    public function followUp($order)
    {
    	$soap = new \SoapClient('https://paycenter.winbank.gr/services/paymentgateway.asmx?WSDL');
    	$methodInstance = $this->getPaymentMethodInstance($order);

    	$xml = [
			'TransactionRequest' => [
				'Header' => [
			  		'RequestType'       => 'FOLLOW_UP',
			  		'RequestMethod'     => 'SYNCHRONOUS',
			  		'MerchantInfo' => [
					    'AcquirerID'    => 'GR014',
					    'MerchantID'    => $methodInstance->getConfigData('merchant_id'),
					    'PosID'         => $methodInstance->getConfigData('pos_id'),
					    'ChannelType'   => 'MOTO',
					    'User'          => $methodInstance->getConfigData('username'),
					    'Password'      => hash('md5', $methodInstance->getConfigData('password')),
			  		],
				],
				'Body' => [
			  		'TransactionInfo' => [
			    		'MerchantReference' => $order->getIncrementId(),
			  		]
				],
			],
		];

		try {
			$data = $soap->ProcessTransaction($xml);
			return $data->TransactionResponse;
		} catch (Exception $e) {
			return false;
		}
    }

    private function getPaymentMethodInstance($order)
    {
    	$paymentMethodCode = $order->getPayment()->getMethod();
        $methodInstance = $this->paymentHelper->getMethodInstance($paymentMethodCode);
        $methodInstance->setStore($order->getStoreId());

        return $methodInstance;
    }

}