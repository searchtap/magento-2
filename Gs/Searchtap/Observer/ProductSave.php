<?php

namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\Indexer;

class ProductSave implements ObserverInterface
{

    protected $objectManager;
    protected $logger;

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $product = $observer->getEvent()->getProduct();
        $this->productSave($product);
    }

    //Trigger on product save from backend.
    public function productSave($product)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);
        //check if product belongs to configurable association
        $parentIds = $this->objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
        if (isset($parentIds[0])) {
            $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($parentIds[0]);
        }

        $status = $product->getStatus();
        $this->logger->info('Product ID = ' . $product->getId());
        $this->logger->info('Product status = ' . $status);

        $directory = $this->objectManager->get('\Magento\Framework\Filesystem\DirectoryList');

        $storeId = $product->getStoreId();

        $storeIds = [];

        if (!$storeId) {
            $storeManager = $this->objectManager->create('\Magento\Store\Model\StoreManagerInterface');
            $stores = $storeManager->getStores();
            foreach ($stores as $store) {
                $indexEnable = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/image/st_image_width', $store->getId());

                if ($indexEnable)
                    $storeIds[] = $store->getId();
            }

        }
        $this->logger->info($storeIds);

        if (count($storeIds) > 0) {
            foreach ($storeIds as $storeId) {
                if($status)
                exec('php ' . $directory->getRoot() . '/bin/magento searchtap:indexer --p ' . $product->getId() . ' --s ' . $storeId . ' > /dev/null 2>/dev/null &');
            }
        }
        else {
            if($status)
            exec('php ' . $directory->getRoot() . '/bin/magento searchtap:indexer --p ' . $product->getId() . ' --s ' . $storeId . ' > /dev/null 2>/dev/null &');
        }
    }
}
