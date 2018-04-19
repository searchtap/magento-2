<?php

namespace Gs\Searchtap\Model\Config\Source;


class GetAdditionalAttributes implements \Magento\Framework\Option\ArrayInterface {

    public function toOptionArray()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $eavConfig = $objectManager->get('\Magento\Eav\Model\Config');
        $attribute = $eavConfig->getEntityAttributeCodes('catalog_product');

        foreach ($attribute as  $value) {
            $options[] = ['label' => $value, 'value' => $value];
        }

        return $options;
    }
}