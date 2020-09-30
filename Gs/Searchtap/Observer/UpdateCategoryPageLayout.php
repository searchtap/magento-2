<?php


namespace Gs\Searchtap\Observer;

use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;

/**
 * Class UpdateCategoryPageLayout
 * @package Klevu\Categorynavigation\Model\Observer
 */
class UpdateCategoryPageLayout implements ObserverInterface
{
    private $_registry;

    public function __construct(Registry $registry)
    {
        $this->_registry = $registry;
    }

    public function execute(Observer $observer)
    {
        $action = $observer->getData('full_action_name');
        $layout = $observer->getData('layout');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $categoryPage = $objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/st_category_page/st_category_page_enabled');

        if ($action == "catalog_category_view" && $categoryPage) {
            $category = $this->_registry->registry('current_category');
            if (!$category instanceof CategoryModel) {
                return false;
            }
            $categoryDisplayMode = $category->getData('display_mode');
            if ($categoryDisplayMode != "PAGE") {
                $layout->unsetElement('category.image');
                $layout->unsetElement('category.description');
                $layout->unsetElement('category.cms');
                $layout->unsetElement('category.products');
                $layout->unsetElement('category.products.list');
                $layout->unsetElement('category.product.type.details.renderers');
                $layout->unsetElement('category.product.addto');
                $layout->unsetElement('category.product.addto.compare');
                $layout->unsetElement('product_list_toolbar');
                $layout->unsetElement('product_list_toolbar_pager');
                $layout->unsetElement('category.product.addto.wishlist');
                $layout->unsetElement('catalog.leftnav');
                $layout->unsetElement('catalog.navigation.state');
                $layout->unsetElement('catalog.navigation.renderer');
                $layout->getUpdate()->addHandle('searchtap_category_index');
            }
        }
    }
}


