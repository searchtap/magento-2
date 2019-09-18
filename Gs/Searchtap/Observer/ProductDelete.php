<?php

namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\SearchTapAPI;

class ProductDelete implements ObserverInterface
{

    protected $objectManager;
    protected $logger;
    protected $collectionName;
    protected $adminKey;
    protected $applicationId;

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->collectionName = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_collection');
        $this->adminKey = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_admin_key');
        $this->applicationId = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_application_id');

        $product = $observer->getEvent()->getProduct();
        $this->productDelete($product->getId());
    }

    public function productDelete($productId)
    {
//        $productIds[] = $productId;
//        $st = new SearchTapAPI($this->applicationId, $this->collectionName, $this->adminKey);

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $storeIds = [];

        $storeManager = $this->objectManager->create('\Magento\Store\Model\StoreManagerInterface');
        $stores = $storeManager->getStores();
        foreach ($stores as $store) {
            $indexEnable = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/image/st_image_width', $store->getId());

            if ($indexEnable)
                $storeIds[] = $store->getId();
        }

        $this->logger->info($storeIds);
        $directory = $this->objectManager->get('\Magento\Framework\Filesystem\DirectoryList');

        if (count($storeIds) > 0) {
            foreach ($storeIds as $storeId) {
                exec('php ' . $directory->getRoot() . '/bin/magento searchtap:indexer --d ' . $productId . ' --s ' . $storeId . ' > /dev/null 2>/dev/null &');
            }
        }

//        $st->searchtapCurlDeleteRequest($productIds);
    }
}