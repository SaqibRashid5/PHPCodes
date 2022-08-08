<?php

public function createAndPayInvoice ($amount, $product, $customerStripeId, $currencyCode, $subscriptionId) {
    try  {
        // create price in stripe
        $price = $this->stripeClient->prices->create([
            'unit_amount' => $amount * 100,
            'currency' => $currencyCode,
            'product' => $product,
        ]);

        // create invoice item in stripe
        $this->stripeClient->invoiceItems->create(
            ['customer' => $customerStripeId, 'price' => $price['id']]
        );

        // create invoice in stripe
        $paymentMethodId = $this->getPaymentMethodBySubscriptionID($subscriptionId);
        $invoice = $this->stripeClient->invoices->create([
            'customer' => $customerStripeId,
            'default_payment_method' => $paymentMethodId
        ]);

        // finalize invoice in stripe
        $invoice = $this->stripeClient->invoices->finalizeInvoice(
            $invoice['id'],
            []
        );

        $paymentIntent = $invoice['payment_intent'];

        // pay invoice
        $paidInvoice = $this->stripeClient->invoices->pay(
            $invoice['id'],
            []
        );

        return $paymentIntent;
    } catch (\Exception $e) {
        $addNewCardLink = $this->store->getBaseUrl() . 'stripe/customer/cards/';
        $linkTag = "<a href='" . $addNewCardLink . "' class='action'><span>link</span></a>";
        throw new \Exception($e->getMessage() . __(' Please visit this ' . $linkTag . ' to add a new card and change it against this subscription to try again.'));
    }
    return false;
}


?>