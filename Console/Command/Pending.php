<?php

namespace Natso\Piraeus\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Natso\Piraeus\Helper\WebService;

class Pending extends Command
{

    private $orderCollectionFactory;
    private $state;
    private $invoiceService;
    private $transaction;
    private $webService;

    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        State $state,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        WebService $webService
    )
    {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->state = $state;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->webService = $webService;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->state->setAreaCode('adminhtml');

        $paymentMethod = 'piraeus';
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('status', 'pending');
        $collection->getSelect()->join(
            ['payment' => $collection->getTable('sales_order_payment')],
            'main_table.entity_id = payment.parent_id',
            ['method']
        )->where('payment.method = ?', $paymentMethod);

        foreach ($collection as $order) {
            $output->writeln('Checking Order #' . $order->getIncrementId());
            $response = $this->webService->followUp($order);

            if (
                $response->Header->ResultCode == 0 &&
                $response->Body->TransactionInfo->StatusFlag == 'Success'
            ) {
                $output->writeln('Transaction successful. Changing order status');
                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->addStatusToHistory($order->getStatus(), 'Success Payment. Transaction Id: ' . $response->Body->TransactionInfo->TransactionID);
                $order->save();

                if ($order->canInvoice()) {
                    $output->writeln('Creating invoice');
                    $invoice = $this->invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $invoice->save();
                    $transactionSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                    $order->addStatusHistoryComment(__('Invoiced', $invoice->getId()))->setIsCustomerNotified(false)->save();
                }
            }

            $output->writeln(
                'Order #' . $order->getIncrementId() . ' processed.'
            );
        }

        return 1;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName("natso_piraeus:pending");
        $this->setDescription("Check Pending orders");
        parent::configure();
    }
}