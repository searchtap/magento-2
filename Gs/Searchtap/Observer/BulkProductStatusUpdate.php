<?php

namespace Gs\Searchtap\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gs\Searchtap\Console\Command\SearchTapAPI;

class BulkProductStatusUpdate implements ObserverInterface {

    protected $objectManager;
    protected $logger;
    protected $storeId;

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->storeId = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_store');

        $productsIds = $observer->getEvent()->getProductIds();
        $this->productBulkStatusUpdate($productsIds);
    }

    public function productBulkStatusUpdate($productIds)
    {
        $productCollection = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
        $collectionName = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_collection');
        $adminKey = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_admin_key');
        $st = new SearchTapAPI();
        $deleteProductIds = array();
        $productArr = array();

        $collection = $productCollection->create()
            ->addStoreFilter($this->storeId)
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('entity_id', array('in' => $productIds));

        $status = 0;

        foreach ($collection as $product) {

            //check for product belongs to configurable association.
            $parentIds = $this->objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
            if (isset($parentIds[0])) {
                $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($parentIds[0]);
            }

            $status = $product->getStatus();

            if ($status != 2) {
                $productArr[] = $this->productArray($product);
            } else {
                $deleteProductIds[] = $product->getId();
            }
        }

        if ($status != 2) {
            $productJson = json_encode($productArr);
            $st->searchtapCurlRequest($productJson, $collectionName, $adminKey);
        } else {
            $st->searchtapCurlDeleteRequest($deleteProductIds, $collectionName, $adminKey);
        }
    }
}