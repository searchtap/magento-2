<?php

namespace Gs\Searchtap\Console\Command;

use Gs\Searchtap\Observer\Searchtap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

//use Searchtap\src\Searchtap\SearchTapClient;

class Indexer extends Command
{
    protected $state;
    protected $objectManager;
    protected $storeId;
    protected $collectionName;
    protected $adminKey;
    protected $applicationId;
    protected $selectedAttributes;
    protected $logger;
    protected $cert_path;
    protected $imageWidth = 0;
    protected $imageHeight = 0;
    public $actualCount = 0;
    public $parentCount = 0;
    protected $product_visibility_array = array('1' => 'Not Visible Individually', '2' => 'Catalog', '3' => 'Search', '4' => 'Catalog,Search');

    const NAME = 'p';

    public function __construct(\Magento\Framework\App\State $state) {
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $options = [
            new InputOption(
                self::NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'p'
            )
        ];

        $this->setName('searchtap:indexer')
            ->setDescription('Searchtap')
            ->setDefinition($options);;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);

        $productIds = $input->getOption(self::NAME);

        if(!$productIds)
            $this->indexProducts();
        else
            $this->indexSingleProduct($productIds);
    }

    public function getStoreDetails()
    {
        $this->cert_path = BP . '/app/code/Gs/Searchtap/gs_cert/searchtap.io.crt';
        $this->product_visibility_array = array('1' => 'Not Visible Individually', '2' => 'Catalog', '3' => 'Search', '4' => 'Catalog,Search');
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->storeId = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_store');
        $this->imageWidth = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/image/st_image_width');
        $this->imageHeight = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/image/st_image_height');
        $this->collectionName = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_collection');
        $this->adminKey = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_admin_key');
        $this->applicationId = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_application_id');
        $this->selectedAttributes = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/attributes/additional_attributes');
    }

//    public function initializeSearchtap () {
//        $st = new SearchTapClient($this->collectionName, $this->adminKey);
//    }

    public function indexSingleProduct($ids) {

        echo 'Start Indexing';
        $productIds = explode(",", $ids);

        $this->getStoreDetails();

        $productCollection = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');

        $collection = $productCollection->create()
            ->addStoreFilter($this->storeId)
            ->addAttributeToSelect("*")
            ->addAttributeToFilter('entity_id', array('in' => $productIds));

        $this->productsJson($collection);
    }

    public function indexProducts()
    {
        echo 'Indexer started';
        $this->getStoreDetails();

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $counter = $i = 0;
        $productSteps = 1000;

        //Indexed enabled products and push to searchtap API.
        $productCollection = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');

        $collection = $productCollection->create()
            ->addStoreFilter($this->storeId)
            ->addAttributeToFilter('status', array('eq' => 1));

        $collection->getSelect()->joinLeft(array(
            'link_table' => 'catalog_product_super_link'),
            'link_table.product_id = e.entity_id',
            array('product_id')
        );

        $collection->getSelect()->where('link_table.product_id IS NULL');

        $collection->load();

        $totalProducts = $collection->getSize();

        $this->logger->info('Total Products that need to be indexed = ' . $totalProducts);

        while ($i <= $totalProducts) {
            $counter++;

            //Fetching all enabled products (Simple without parent and all other product types)
            $collections = $productCollection->create()
                ->addStoreFilter($this->storeId)
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('status', array('eq' => 1))
                ->addAttributeToSort('created_at', 'desc')
                ->setPageSize($productSteps)
                ->setCurPage($counter);

            $collections->getSelect()->joinLeft(array(
                'link_table' => 'catalog_product_super_link'),
                'link_table.product_id = e.entity_id',
                array('product_id')
            );

            $collections->getSelect()->where('link_table.product_id IS NULL');

            $collections->load();

            $this->productsJson($collections);

            $i += $productSteps;

            $this->logger->info('Indexed ' . $i . ' products');
        }

        $issues = $totalProducts - $this->actualCount;
        $issues -= $this->parentCount;
        $this->logger->info("Indexing Completed");
        $this->logger->info("Total products needs to be indexed = " . $totalProducts);
        $this->logger->info("Total Parent products having no child enabled = " . $this->parentCount);
        $this->logger->info("Total products that actually indexed = " . $this->actualCount);
        $this->logger->info("Issues = " . $issues);

        //Remove disabled products from searchtap server

        $deleteCollection = $productCollection->create()
            ->addStoreFilter($this->storeId)
            ->addAttributeToFilter('status', array('eq' => 2))
            ->load();

        $totalDeletedProducts = $deleteCollection->getSize();

        $counter = $i = 0;

        $this->logger->info('Total products that need to be deleted = ' . $totalDeletedProducts);

        while ($i <= $totalDeletedProducts) {
            $counter++;

            $productIds = array();

            $deleteCollections = $productCollection->create()
                ->addStoreFilter($this->storeId)
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('status', array('eq' => 2))
                ->setPageSize($productSteps)
                ->setCurPage($counter)
                ->load();

            foreach ($deleteCollections as $product) {
                $productIds[] = $product->getId();
            }

            $this->searchtapCurlDeleteRequest($productIds);

            $i += $productSteps;

            $this->logger->info('Indexing complete');
        }

        echo 'Indexer completed';
    }

    public function productsJson($collection)
    {
        $product_array = array();

        foreach ($collection as $product) {

            $productFlag = true;
            $product_type_id = $product->getTypeId();
            switch ($product_type_id) {
                case 'configurable':
                    $child_status = array();
                    $child_count = 0;

                    $data = $product->getTypeInstance()->getConfigurableOptions($product);

                    foreach ($data as $attr) {
                        foreach ($attr as $p) {
                            $childObject = $this->objectManager->get('Magento\Catalog\Model\Product');
                            $childProduct = $childObject->loadByAttribute('sku', $p['sku']);
                            $childStatus = (int)$childProduct->getStatus();

                            $child_status[$child_count] = $childStatus;

                            $child_count++;
                        }
                    }

                    $flag = 0;
                    for ($i = 0; $i < $child_count; $i++) {
                        if ($child_status[$i] == 1)
                            $flag = 1;
                    }
                    if ($flag == 0) {
                        $this->parentCount++;
                        $productFlag = false;
                    }
                    break;
            }

            if ($productFlag) {
                $product_array[] = $this->productArray($product);
                $this->actualCount++;
            }
        }

//        foreach ($collection as $product) {
//
//            $product_array[] = $this->productArray($product);
//        }

        $product_json = json_encode($product_array);

//        $this->logger->info(print_r($product_json, true));

        unset($product_array);

        $this->searchtapCurlRequest($product_json);

        unset($product_json);
    }

    public function productArray($product)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $productId = $product->getId();
        $productName = $product->getName();
        $productSKU = $product->getSKU();
        $productStatus = $product->getStatus();

        $productVisibility = $this->product_visibility_array[$product->getVisibility()];
        $productURL = $product->getProductUrl();
        $productPrice = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();

        $productSpecialPrice = $product->getFinalPrice();
        $productCreatedAt = strtotime($product->getCreatedAt());
        $productType = $product->getTypeId();

        //get stock details
        $stock = $this->objectManager->get('Magento\CatalogInventory\Api\StockRegistryInterface')->getStockItem($product->getId());
        $productStockQty = $stock->getQty();
        $productInStock = $stock->getIsInStock();

        //get parent ID
        $parentIds = $this->objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($productId);
        if (isset($parentIds[0]))
            $parentId = $parentIds[0];
        else
            $parentId = 0;


        //get variations of configurable products
        $variation = array();
        if ($productType == "configurable") {
            $data = $product->getTypeInstance()->getConfigurableOptions($product);
            $childCount = 0;
            $option = array();

            foreach ($data as $attr) {
                foreach ($attr as $p) {
                    $option[$p['sku']][$p['attribute_code']] = $p['option_title'];
                    $option[$p['sku']]['sku'] = $p['sku'];

                    $childObject = $this->objectManager->get('Magento\Catalog\Model\Product');
                    $childProduct = $childObject->loadByAttribute('sku', $p['sku']);
                    $option[$p['sku']]['id'] = (int)$childProduct->getId();
                    $option[$p['sku']]['price'] = (float)$childProduct->getPrice();
                    $option[$p['sku']]['discounted_price'] = (float)$childProduct->getFinalPrice();
                    $option[$p['sku']]['status'] = (int)$childProduct->getStatus();
                    $option[$p['sku']]['visibility'] = $this->product_visibility_array[$childProduct->getVisibility()];

                    //get stock details
                    $childStock = $this->objectManager->get('Magento\CatalogInventory\Api\StockRegistryInterface')->getStockItem($childProduct->getId());
                    $option[$p['sku']]['stock_qty'] = $childStock->getQty();
                    $option[$p['sku']]['in_stock'] = $childStock->getIsInStock();
                }
            }

            foreach ($option as $child) {
                $variation[$childCount] = $child;
                $childCount++;
            }
        }


        //get images
        $store = $this->objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore();
        $productImage = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
        $productSmallImage = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getSmallImage();
        $productThumbnailImage = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getThumbnail();

        $emulator = $this->objectManager->create('Magento\Store\Model\App\Emulation');
        $emulator->startEnvironmentEmulation($this->storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);

        $image_helper = $this->objectManager->create('Magento\Catalog\Helper\ImageFactory');
        $image = $image_helper->create()->init($product,'category_page_list')->resize($this->imageWidth, $this->imageHeight)->getUrl();

        $emulator->stopEnvironmentEmulation();

        //get currency code and symbol
        $currencyCode = $store->getCurrentCurrencyCode();
        $currencySymbol = $this->objectManager->create('Magento\Framework\Locale\CurrencyInterface')->getCurrency($currencyCode)->getSymbol();

        //get product categories
        $catpathArray = array();
        $catlevelArray = array();
        $_categories = array();

        $categories = $product->getCategoryCollection()
            ->setStoreId($this->storeId)
            ->addAttributeToSelect('path');

        foreach ($categories as $cat1) {

            $pathIds = explode('/', $cat1->getPath());
            array_shift($pathIds);

            $categoryFactory = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\CollectionFactory');
            $collection_cat = $categoryFactory->create()
                ->addAttributeToSelect('*')
                ->setStoreId($this->storeId)
                ->addFieldToFilter('entity_id', array('in' => $pathIds));;

            $pathByName = '';
            $level = 1;
            $path_name = array(array());
            $row = 0;
            foreach ($collection_cat as $cat) {
                if (!$cat->hasChildren()) {
                    if(!in_array($cat->getName(), $_categories))
                        if($cat->getIsActive()) {
                            $_categories[] = $cat->getName();
                        }
                }
                $path_name[$row][0] = $cat->getId();
                $path_name[$row][1] = $cat->getName();
                $path_name[$row][2] = $cat->getIsActive();

                $row++;
            }

            for ($i = 0; $i < $row; $i++) {
                for ($j = 0; $j < $row; $j++) {
                    if ($pathIds[$i] == $path_name[$j][0]) {
                        if ($pathByName == '') {
                            $pathByName .= $path_name[$j][1];
                        } else {
                            $pathByName .= '|||' . $path_name[$j][1];
                        }

                        if($path_name[$j][2]) {
                            if (!isset($catlevelArray['_category_level_' . $level])) {
                                $catlevelArray['_category_level_' . $level][] = $path_name[$j][1];
                            }
                        }
                    }
                }
                if (!isset($catpathArray['categories_level_' . $level])) {
                    $catpathArray['categories_level_' . $level][] = $pathByName;
                }
                $level++;
            }
        }

        //get custom attributes
        $selected = explode(',', $this->selectedAttributes);

        $customAttributes = array();

        foreach ($selected as $attr) {

            $value = $product->hasData($attr);
            if ($value) {
                if ($product->getResource()->getAttribute($attr)->getFrontendInput() == 'multiselect') {
                    $explodeAttrs = explode(',', $product->getResource()->getAttribute($attr)->getFrontend()->getValue($product));
                    $customAttributes[$attr] = $explodeAttrs;
                } else
                    $customAttributes[$attr] = $product->getResource()->getAttribute($attr)->getFrontend()->getValue($product);
            }
        }

        //print_r($customAttributes);

        $productArray = array(
            'id' => (int)$productId,
            'name' => $productName,
            'sku' => $productSKU,
            'status' => (int)$productStatus,
            'visibility' => $productVisibility,
            'url' => $productURL,
            'price' => (float)$productPrice,
            'discounted_price' => (float)$productSpecialPrice,
            'created_date' => $productCreatedAt,
            'product_type' => $productType,
            'stock_qty' => $productStockQty,
            'in_stock' => $productInStock,
            'parent_id' => $parentId,
            'variation' => $variation,
            'image' => $productImage,
            'small_image' => $productSmallImage,
            'thumbnail_image' => $productThumbnailImage,
            '_category' => $_categories,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'cache_image' => $image
        );

        $array = array_merge($productArray, $catpathArray, $catlevelArray, $customAttributes);

        return $array;
    }

    public function searchtapCurlRequest($product_json)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://manage.searchtap.net/v2/collections/" . $this->collectionName . "/records",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => "",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $product_json,
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json",
                "Authorization: Bearer " . $this->adminKey
            ),
        ));

        curl_exec($curl);
        $err = curl_error($curl);
        $this->logger->info($err);

        $this->logger->info( "SearchTap API response :: " . curl_getinfo($curl, CURLINFO_HTTP_CODE) );

        curl_close($curl);

//        if ($err) {
//            Mage::log("Exception occurred", null, $this->log_file_name);
//        }
    }

    public function searchtapCurlDeleteRequest($productIds)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $curl = curl_init();

        if(count($productIds) == 0)
            return;

//        $data_json = json_encode($productIds);

        foreach ($productIds as $id) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://manage.searchtap.net/v2/collections/" . $this->collectionName . "/records/" . $id,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CAINFO => "",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/json",
                    "Authorization: Bearer " . $this->adminKey
                ),
            ));
        }

        $exec = curl_exec($curl);

        $err = curl_error($curl);

        $this->logger->info($err);

        $result = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $this->logger->info("SearchTap Delete API response :: " . $result);

        curl_close($curl);

//        if ($err) {
//            Mage::log("Exception occurred", null, $this->log_file_name);
//        }
        return;
    }
}


