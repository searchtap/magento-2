<?php
namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\Indexer;

class ProductSave implements ObserverInterface {

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $indexer = new Indexer();
        $product = $observer->getEvent()->getProduct();
        $indexer->productSave($product);
    }
}