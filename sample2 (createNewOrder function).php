<?php

protected function createNewOrder ($originalOrder, $amount, $product, $customer) {
    try {
        $productInStock = true;
        $stockManage = false;
        $quote = $this->quoteFactory->create();
        $quote->setStore($this->store);
        $quote->setStoreId($this->store->getId());
        $quote->setQuoteCurrencyCode($originalOrder->getOrderCurrencyCode());
        $quote->setCustomerEmail($originalOrder->getCustomerEmail());
        $quote->assignCustomer($customer);
        $quote->setIsRecurringOrder(true);

        $quote->getBillingAddress()->addData($this->addressFactory->create()->load($originalOrder->getDefaultBilling())->getData());
        $quote->getShippingAddress()->addData($this->addressFactory->create()->load($originalOrder->getDefaultShipping())->getData());

        $product = $this->productModel->load($product);
        $originalPrice = $product->getPrice();
        $product->setPrice($amount);
        $product->setData('stripe_sub_enabled', 0);
        $stockStatus = $this->stockRegistry->getStockStatus($product->getId(), $product->getStore()->getWebsiteId());
        if ($stockStatus->getData('stock_status') == 0) {
            $productInStock = false;
            $this->productHelper->setSkipSaleableCheck(true);
            $this->genericFunctions->updateProductStock($product, 2, 1);
        } else {
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            if ($stockItem->getData('manage_stock')) {
                $stockManage = true;
                $this->genericFunctions->updateStockManage($product, 0);
            }
        }
        $quoteItem = $quote->addProduct($product, 1);
        $quoteItem->setCustomPrice($amount);
        $quoteItem->setOriginalCustomPrice($amount);
        $quoteItem->setBaseCustomPrice($amount);
        $quoteItem->setBaseOriginalCustomPrice($amount);

        $quote->getShippingAddress()
            ->setShippingMethod($originalOrder->getShippingMethod())
            ->setCollectShippingRates(true);


        $quote->setPaymentMethod($originalOrder->getPayment()->getMethod());
        $quote->setInventoryProcessed(false);
        $quote->save();
        $data = [];
        $data = array_merge($data, ['method' => 'stripe_payments']);
        $quote->getPayment()->setAdditionalInformation("is_recurring_subscription", true)->importData($data);
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        $order = $this->quoteManagement->submit($quote);

        $order->setEmailSent(0);
        $order->setData('order_type', 3);
        $order->setState($this->orderModel::STATE_COMPLETE, true)->save();
        $order->setStatus($this->orderModel::STATE_COMPLETE, true)->save();
        $increment_id = $order->getRealOrderId();
        if ($productInStock == false) {
            $this->productHelper->setSkipSaleableCheck(false);
            $this->genericFunctions->updateProductStock($product, 0, 0);
        } else {
            if ($stockManage) {
                $this->genericFunctions->updateStockManage($product, 1);
            }
        }
        $product->setPrice($originalPrice);
        $product->setData('stripe_sub_enabled', 1);
        $product->save();
        return $order;
    } catch (\Exception $e) {
        $quote->removeAllItems();
        $quote->save();
        throw new \Exception('Error: ' . $e->getMessage());
    }
}

?>