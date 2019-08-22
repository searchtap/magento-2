<?php

namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\SearchTapAPI;

class ProductDelete implements ObserverInterface {

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
        $productIds[] = $productId;
        $st = new SearchTapAPI($this->applicationId, $this->collectionName, $this->adminKey);
        $st->searchtapCurlDeleteRequest($productIds);
    }
}