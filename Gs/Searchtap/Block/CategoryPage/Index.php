<?php

namespace Gs\Searchtap\Block\CategoryPage;

class Index extends \Magento\Framework\View\Element\Template
{
    protected $_categoryFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory
    )
    {
        $this->storeManager = $context->getStoreManager();
        $this->request = $context->getRequest();
        $this->registry = $registry;
        $this->_categoryFactory = $categoryFactory;
        parent::__construct($context);
    }

    public function getCategoryId()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $category = $objectManager->get('Magento\Framework\Registry')->registry('current_category');
        return $category->getId();
    }

     public function getCategoryName()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $category = $objectManager->get('Magento\Framework\Registry')->registry('current_category');
        return $category->getName();
    }

    public function getCurrentCategory()
    {
        return $this->_registry->registry('current_category');
    }
}
