<?php

namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\Indexer;

class ProductCSVImport implements ObserverInterface {

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info('in import csv event');

        $adapter = $observer->getEvent()->getProductEntitiesInfo();
       // $productIds = $adapter->getProductsSku();
        //$productIds = $adapter->getAffectedEntityIds();

        $logger->info(print_r($adapter, true));
    }
}