<?php

namespace Natso\Piraeus\Plugin;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class SuppressOrderEmailPlugin
{
    const PAYMENT_METHOD_CODE = 'piraeus';

    /**
     * Around plugin on the actual email sender.
     * No matter where send() is called from, if the order is still in
     * pending_payment (= bank hasn't confirmed yet), we suppress the email.
     * Success.php will call send() again after updating the order to processing,
     * at which point this check passes and the email goes out normally.
     */
    public function aroundSend(
        OrderSender $subject,
        callable $proceed,
        Order $order,
        bool $forceSyncMode = false
    ) {
        if (!$order->getPayment()) {
            return $proceed($order, $forceSyncMode);
        }

        if ($order->getPayment()->getMethod() !== self::PAYMENT_METHOD_CODE) {
            return $proceed($order, $forceSyncMode);
        }

        if ($order->getState() === 'new' || $order->getState() === 'canceled') {
            return false; // suppress
        }

        return $proceed($order, $forceSyncMode);
    }
}