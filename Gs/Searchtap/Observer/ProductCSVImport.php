<?php

namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\Indexer;
use \Magento\Catalog\Model\Product;

class ProductCSVImport implements ObserverInterface {

  //  protected $objectManager;
    private $product;

    public function __construct(
        Product $product
    )
    {
        $this->product = $product;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logger->info('Product Importing using CSV..');
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        try {
            $data = $observer->getEvent()->getData('bunch');
                foreach ($data as $product) {

                    $productId = $this->product->getIdBySku($product['sku']);
                    $status=$product['status'];
                    $logger->info("Id will: ".$productId." Product Status ". $status);
                    if($status==1)
                       exec('php ' . $directory->getRoot() . '/bin/magento searchtap:indexer --p ' . $productId . ' --s 1 > /dev/null 2>/dev/null &');

                }
        } catch (\Exception $e) {
            $logger->error($e);
        }

    }
}
