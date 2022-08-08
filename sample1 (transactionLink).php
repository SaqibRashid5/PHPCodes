<?php

ini_set('error_reporting', E_ALL);
ini_set("display_errors", "1");

use Magento\Framework\App\Bootstrap;
require 'app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$registry = $objectManager->get('Magento\Framework\Registry');
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');


$stripe = $objectManager->create('StripeIntegration\Payments\Model\Config')->getStripeClient();

try {
  $collection = $objectManager->create('Magento\Sales\Model\Order'); 
  $order = $collection->loadByIncrementId('xxxxxxxxx');

  $transactionId = 'pi_xxxxxxxxxxxxxxxxxxxxxxxx';

  $payment = $order->getPayment();
  $payment->setLastTransId($transactionId);
  $payment->setTransactionId($transactionId);

  $message = __("%1 amount of %2 via Stripe.", 'Captured', $order->getOrderCurrencyCode() .  ' ' . $order->getGrandTotal());

  $transactionBuilder = $objectManager->create('Magento\Sales\Model\Order\Payment\Transaction\Builder');
  $transaction = $transactionBuilder->setPayment($payment)
      ->setOrder($order)
      ->setTransactionId($transactionId)
      ->setFailSafe(true)
      ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

  $payment->addTransactionCommentsToOrder(
      $transaction,
      $message
  );
  $payment->setParentTransactionId(null);
  $payment->save();
  $order->save();
  echo 'Transaction created and payment linked successfully!';
} catch (\Exception $e) {
  echo $e->getMessage();
}


?>