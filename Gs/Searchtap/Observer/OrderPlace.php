<?php
namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\Indexer;

class OrderPlace implements ObserverInterface {

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $items = $order->getAllItems();
        $productIds = array();

        foreach ($items as $item) {
            $productIds[] = $item->getProductId();
        }

        $indexer = new Indexer();
        $indexer->productBulkStatusUpdate($productIds);
    }
}