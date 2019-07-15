<?php

namespace Storefront\BTCPayServer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use stdClass;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;

class IpnManagement {

    private $invoiceService;
    private $transaction;
    private $orderRepository;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $db;


    /**
     * IpnManagement constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderRepository $orderRepository
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     */
    public function __construct(ScopeConfigInterface $scopeConfig, OrderRepository $orderRepository, InvoiceService $invoiceService, Transaction $transaction, ResourceConnection $resourceConnection) {
        $this->scopeConfig = $scopeConfig;
        // TODO can we use the orderRepository ? Does not have loadByIncrementId() though...
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->db = $resourceConnection->getConnection();
    }

    public function getStoreConfig($path, $storeId) {
        $_val = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $_val;

    }



    public function postIpn() {
        $postedString = file_get_contents('php://input');
        if (!$postedString) {
            throw new \RuntimeException('No data posted. Cannot process BTCPay Server IPN.');
        }
        $data = json_decode($postedString, true);

        $btcpayInvoiceId = $data['data']['id'];

        // Only use the "id" field from the POSTed data and discard the rest. The posted data can be malicious.
        unset($data);

        $this->updateInvoice($btcpayInvoiceId);
    }

    /**
     * @param $transactionId
     * @return \Magento\Sales\Model\Order | null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateInvoice($transactionId) {
        $table_name = $this->db->getTableName('btcpayserver_transactions');
        $select = $this->db->select()->from($table_name)->where('transaction_id = ?', $transactionId)->limit(1);

        $result = $this->db->fetchRow($select);
        $row = $result->fetch();
        if ($row) {

            $orderId = $row['order_id'];
            $order = $this->orderRepository->get($orderId);

            $storeId = $order->getStoreId();

            $token = $this->getStoreConfig('payment/btcpayserver/token', $storeId);
            $host = $this->getStoreConfig('payment/btcpayserver/host', $storeId);

            $params = new stdClass();
            $params->invoiceID = $transactionId;
            //$params->extension_version = $this->getExtensionVersion();
            $item = new Item($token, $host, $params);
            $invoice = new Invoice($item);

            $orderStatus = json_decode($invoice->checkInvoiceStatus($transactionId), true);

            if ($orderId !== $orderStatus['orderID']) {
                throw new \RuntimeException('The supplied order ID ' . $orderId . ' does not match transaction ID ' . $transactionId . '. Cannot process BTCPay Server IPN.');
            }

            $invoice_status = $orderStatus['data']['status'] ?? false;


            $where = $this->db->quoteInto('order_id = ?', $orderId) . ' and ' . $this->db->quoteInto('transaction_id = ?', $transactionId);
            $rowsChanged = $this->db->update($table_name, ['transaction_status' => $invoice_status], $where);


            // TODO fill $event in some other way...
            $event = [];
            switch ($event['name']) {

                case 'invoice_paidInFull':

                    if ($invoice_status === 'paid') {
                        // 1) Payments have been made to the invoice for the requested amount but the transaction has not been confirmed yet
                        $paidNotConfirmedStatus = $this->getStoreConfig('payment/btcpayserver/payment_paid_status', $storeId);

                        $order->addStatusHistoryComment('Payment underway, but not confirmed yet', $paidNotConfirmedStatus);
                        $order->save();
                        return true;
                    }
                    break;

                case 'invoice_confirmed':
                    if ($invoice_status === 'confirmed') {
                        // 2) Paid and confirmed (happens before completed and transitions to it quickly)

                        // TODO maybe add the transation ID in the comment or something like that?

                        $confirmedStatus = $this->getStoreConfig('payment/btcpayserver/payment_confirmed_status', $storeId);
                        $order->addStatusHistoryComment('Payment confirmed, but not completed yet', $confirmedStatus);

                        $order->save();
                        return true;
                    }
                    break;


                case 'invoice_completed':
                    if ($invoice_status === 'complete') {
                        // 3) Paid, confirmed and settled. Final!
                        // TODO maybe add the transation ID in the comment or something like that?

                        $completedStatus = $this->getStoreConfig('payment/btcpayserver/payment_completed_status', $storeId);
                        $order->addStatusHistoryComment('Payment completed', $completedStatus);
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->register();

                        // TODO we really need to save the invoice first as we are saving it again in this transaction? Leaving it out for now.
                        //$invoice->save();

                        $transactionSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                        $transactionSave->save();

                        return true;
                    }
                    break;


                case 'invoice_failedToConfirm':
                    if ($invoice_status === 'invalid') {
                        $order->addStatusHistoryComment('Failed to confirm the order. The order will automatically update when the status changes.');
                        $order->save();
                        return true;
                    }
                    break;

                case 'invoice_expired':
                    if ($invoice_status === 'expired') {
                        // Invoice expired - let's do nothing.

                        return true;
                    }
                    break;

                case 'invoice_refundComplete':
                    // Full refund

                    $order->addStatusHistoryComment('Refund received through BTCPay Server.');
                    $order->setState(Order::STATE_CLOSED)->setStatus(Order::STATE_CLOSED);

                    $order->save();

                    return true;
                    break;

                // TODO what about partial refunds, partial payments and overpayment?
            }

            return $order;

        } else {
            // No transaction round found
            return null;
        }
    }


}
