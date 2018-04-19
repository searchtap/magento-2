<?php

namespace Gs\Searchtap\Model\Config\Source;


class GetStore implements \Magento\Framework\Option\ArrayInterface {

    protected $_storeManager;

    public function __construct(\Magento\Store\Model\StoreManagerInterface $storeManager)
    {
        $this->_storeManager = $storeManager;
    }

    public function toOptionArray()
    {
        $stores = $this->_storeManager->getStores();

        foreach ($stores as $key => $value) {
            $options[] = ['label' => $value['name'].' - '.$value['code'], 'value' => $key];
        }

        return $options;
    }
}