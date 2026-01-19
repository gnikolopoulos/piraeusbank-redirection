<?php

namespace Natso\Piraeus\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

class Vpos extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code = 'piraeus';
    protected $_isOffline = true;
    protected $_isInitializeNeeded = true;

    public function isAvailable(CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote);
    }

}