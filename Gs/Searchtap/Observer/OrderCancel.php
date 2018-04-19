<?php
namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\Indexer;

class OrderCancel implements ObserverInterface {

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $item = $observer->getEvent()->getItem();
        $productId = $item->getProductId();

        $indexer = new Indexer();
        $indexer->productBulkStatusUpdate($productId);
    }
}