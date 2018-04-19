<?php

namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\Indexer;

class BulkProductStatusUpdate implements ObserverInterface {

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $indexer = new Indexer();
        $productsIds = $observer->getEvent()->getProductIds();
        $indexer->productBulkStatusUpdate($productsIds);
    }
}