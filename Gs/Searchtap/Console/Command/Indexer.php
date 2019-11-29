<?php

namespace Gs\Searchtap\Console\Command;

use Gs\Searchtap\Observer\Searchtap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
use Gs\Searchtap\Console\Command\SearchTapAPI;

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
    public $categoryIncludeInMenu = 0;
    public $skipCategoryIds;
    protected $product_visibility_array = array('1' => 'Not Visible Individually', '2' => 'Catalog', '3' => 'Search', '4' => 'Catalog,Search');
    private $st;

    const NAME = 'p';
    const DELETE = 'd';
    const STORE = 's';
    const DELETE_FULL_SYNC = 'f';

    public function __construct(\Magento\Framework\App\State $state)
    {
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        error_reporting(0);

        $options = [
            new InputOption(
                self::NAME,
                null,
                InputOption::VALUE_OPTIONAL
            ),
            new InputOption(
                self::DELETE,
                null,
                InputOption::VALUE_OPTIONAL
            ),
            new InputOption(
                self::STORE,
                null,
                InputOption::VALUE_OPTIONAL
            ),
            new InputOption(
                self::DELETE_FULL_SYNC,
                null,
                InputOption::VALUE_OPTIONAL
            )
        ];

        $this->setName('searchtap:indexer')
            ->setDescription('Searchtap')
            ->setDefinition($options);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);

        if ($input->getOption(self::STORE) == null) {
            echo "Store ID can not be empty";
            exit;
        }

        $this->storeId = $input->getOption(self::STORE);

        if ($input->getOption(self::NAME) != null) {
            $productIds = $input->getOption(self::NAME);
            if ($productIds)
                $this->indexSingleProduct($productIds);
        } else if ($input->getOption(self::DELETE) != null) {
            $this->getStoreDetails();
            $productIds = explode(",", $input->getOption(self::DELETE));
            $this->st->searchtapCurlDeleteRequest($productIds);
        } else if ($input->getOption(self::DELETE_FULL_SYNC) != null) {
            $this->getStoreDetails();
            $this->deleteFullSync($this->storeId);
        } else {
            $this->indexProducts();
            $this->deleteFullSync($this->storeId);
        }
    }

    public function getStoreDetails()
    {
        $this->cert_path = BP . '/app/code/Gs/Searchtap/gs_cert/searchtap.io.crt';
        $this->product_visibility_array = array('1' => 'Not Visible Individually', '2' => 'Catalog', '3' => 'Search', '4' => 'Catalog,Search');
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->imageWidth = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/image/st_image_width', $this->storeId);
        $this->imageHeight = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/image/st_image_height', $this->storeId);
        $this->collectionName = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_collection', $this->storeId);
        $this->adminKey = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_admin_key', $this->storeId);
        $this->applicationId = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/general/st_application_id', $this->storeId);
        $this->selectedAttributes = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/attributes/additional_attributes', $this->storeId);
        $this->categoryIncludeInMenu = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/categories/st_categories_menu', $this->storeId);
        $this->skipCategoryIds = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/categories/st_categories_ignore', $this->storeId);
        $this->discountFilterEnabled = $this->objectManager->create('Gs\Searchtap\Helper\Data')->getConfigValue('st_settings/st_discount/st_discount_enabled', $this->storeId);
        $this->st = new SearchTapAPI($this->applicationId, $this->collectionName, $this->adminKey);
    }

//    public function initializeSearchtap () {
//        $st = new SearchTapClient($this->collectionName, $this->adminKey);
//    }

    public function indexSingleProduct($ids)
    {
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
        $this->getStoreDetails();

        $storeManager = $this->objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $stores = $storeManager->getStores(true, false);
        foreach ($stores as $store) {
            if ($this->storeId == $store->getId())
                echo 'Indexer started for ' . $store->getName() . "\n";
        }

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

            $this->st->searchtapCurlDeleteRequest($productIds);

            $i += $productSteps;

            $this->logger->info('Indexing complete');
        }

        echo 'Indexer completed';
    }

    public function productsJson($collection)
    {
        $product_array = array();
        $invalid_product=array();
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
                        $invalid_product[]= $product->getId();
                    }
                    break;
            }

            if ($productFlag) {
                $product_array[] = $this->productArray($product);
                $this->actualCount++;
            }
        }

          if(!empty($product_array)){
              $product_json = json_encode($product_array);
              $this->st->searchtapCurlRequest($product_json);
              unset($product_array);
              unset($product_json);
          }

          if(!empty($invalid_product)){
              $this->st->searchtapCurlDeleteRequest($invalid_product);

              unset($invalid_product);
          }
    }

    public function productArray($product)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $emulator = $this->objectManager->create('Magento\Store\Model\App\Emulation');
        $emulator->startEnvironmentEmulation($this->storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);

        $productId = $product->getId();
        $productName = html_entity_decode($product->getName());
        $productSKU = $product->getSKU();
        $productStatus = $product->getStatus();

        $productVisibility = $this->product_visibility_array[$product->getVisibility()];
        $productURL = $product->getProductUrl();
        $productPrice = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
//        $productSpecialPrice = $product->getFinalPrice();
        $productSpecialPrice = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
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

        $configurableAttributes = array();

        //get variations of configurable products
        $variation = array();
        if ($productType == "configurable") {
            $data = $product->getTypeInstance()->getConfigurableOptions($product);
            $childCount = 0;
            $option = array();

            foreach ($data as $key => $attr) {
                foreach ($attr as $p) {
                    $childObject = $this->objectManager->get('Magento\Catalog\Model\Product');
                    $childProduct = $childObject->loadByAttribute('sku', $p['sku']);
                    $child_status= $childProduct->getStatus();
                    if($child_status==1){
                        $option[$p['sku']][$p['attribute_code']] = $p['option_title'];
                        $option[$p['sku']]['sku'] = $p['sku'];
                        $option[$p['sku']]['id'] = (int)$childProduct->getId();
                        $option[$p['sku']]['price'] = (float)$childProduct->getPrice();
                        $option[$p['sku']]['discounted_price'] = (float)$childProduct->getFinalPrice();
                        $option[$p['sku']]['status'] = (int)$childProduct->getStatus();
                        $option[$p['sku']]['visibility'] = $this->product_visibility_array[$childProduct->getVisibility()];
                        $option[$p['sku']][$p['attribute_code'] . '_' . 'value_code'] = (int)$p['value_index'];
                        $option[$p['sku']][$p['attribute_code'] . '_code'] = $key;
                        //get stock details
                        $childStock = $this->objectManager->get('Magento\CatalogInventory\Api\StockRegistryInterface')->getStockItem($childProduct->getId());
                        $option[$p['sku']]['stock_qty'] = $childStock->getQty();
                        $option[$p['sku']]['in_stock'] = $childStock->getIsInStock();
                    }

                    // print_r($attr);     
                    if ($option[$p['sku']]['in_stock'] && $option[$p['sku']]['stock_qty'] > 0) {
                        $configurableAttributes['_' . $p['attribute_code']][] = $p['option_title'];


                    }
                }

            }


            foreach ($configurableAttributes as $key => $value)
                $configurableAttributes[$key] = array_unique($value);

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

        $image_helper = $this->objectManager->create('Magento\Catalog\Helper\ImageFactory');
        $image = $image_helper->create()->init($product, 'category_page_list')->resize($this->imageWidth, $this->imageHeight)->getUrl();
        $thubnail_cache_image = $image_helper->create()->init($product, 'product_thumbnail_image')->resize($this->imageWidth, $this->imageHeight)->getUrl();
        $small_cache_image = $image_helper->create()->init($product, 'product_small_image')->resize($this->imageWidth, $this->imageHeight)->getUrl();
        $base_cache_image = $image_helper->create()->init($product, 'product_base_image')->resize($this->imageWidth, $this->imageHeight)->getUrl();

        //get currency code and symbol
        $currencyCode = $store->getCurrentCurrencyCode();
        $currencySymbol = $this->objectManager->create('Magento\Framework\Locale\CurrencyInterface')->getCurrency($currencyCode)->getSymbol();

        //get product categories
        $catpathArray = array();
        $catlevelArray = array();
        $_categories = array();

        $categories = $product->getCategoryCollection()
            ->setStoreId($this->storeId)
            ->addAttributeToFilter('is_active', true)
            ->addAttributeToSelect('path');

        if ($this->categoryIncludeInMenu)
            $categories->addAttributeToFilter('include_in_menu', array('eq' => 1));

        $skipIds = array();

        if ($this->skipCategoryIds) {
            $skipIds = explode(",", $this->skipCategoryIds);
        }

        foreach ($categories as $cat1) {
            $pathIds = explode('/', $cat1->getPath());

            foreach ($skipIds as $id) {
                if (in_array($id, $pathIds)) {
                    continue 2;
                }
            }

            array_shift($pathIds);

            $categoryFactory = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\CollectionFactory');
            $collection_cat = $categoryFactory->create()
                ->addAttributeToSelect('*')
                ->setStoreId($this->storeId)
                ->addFieldToFilter('entity_id', array('in' => $pathIds));

            $pathByName = '';
            $level = 1;
            $path_name = array(array());
            $row = 0;
            foreach ($collection_cat as $cat) {
                if (!$cat->hasChildren()) {
                    if (!in_array($cat->getName(), $_categories))
                        $_categories[] = htmlspecialchars_decode($cat->getName());
                }
                if (!$cat->getIsActive())
                    continue 2;

                if ($this->categoryIncludeInMenu)
                    if (!$cat->getIncludeInMenu())
                        continue 2;

                $path_name[$row][0] = $cat->getId();
                $path_name[$row][1] = trim(htmlspecialchars_decode($cat->getName()));
                $path_name[$row][2] = trim(htmlspecialchars_decode($cat->getIncludeInMenu()));

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

                        if (!in_array($path_name[$j][1], $catlevelArray['_category_level_' . $level]))
                            $catlevelArray['_category_level_' . $level][] = $path_name[$j][1];
                        // $catlevelArray['_category_level_' . $level][][str_replace(' ','_', $path_name[$j][1])]=$product->getId();
                    }
                }

                if (!in_array($pathByName, $catpathArray['categories_level_' . $level]))
                    $catpathArray['categories_level_' . $level][] = $pathByName;

                $level++;
            }
        }
        //  foreach($catlevelArray as $key=>$val){
        //  $catlevelArray[$key]=array_unique($val, SORT_REGULAR);
        // }

        // print_r($catlevelArray);

        //get custom attributes
        $selected = explode(',', $this->selectedAttributes);

        $customAttributes = array();

        foreach ($selected as $attr) {
            $value = $product->hasData($attr);
            if ($value) {
                if ($product->getResource()->getAttribute($attr)->getFrontendInput() == 'multiselect') {
                    $explodeAttrs = explode(',', $product->getResource()->getAttribute($attr)->getFrontend()->getValue($product));
                    $customAttributes[$attr] = array_map("htmlspecialchars_decode", $explodeAttrs);
                } else if ($product->getResource()->getAttribute($attr)->getFrontendInput() == 'boolean') {
                    $customAttributes[$attr] = (bool)$product->getData($attr);
                } else if ($product->getResource()->getAttribute($attr)->getFrontendInput() == 'price') {
                    $customAttributes[$attr] = (float)$product->getResource()->getAttribute($attr)->getFrontend()->getValue($product);
                } else if ($product->getResource()->getAttribute($attr)->getFrontendInput() == 'media_image') {
                    $image = $image_helper->create()->init($product, 'category_page_list', ['type' => $attr]);
                    $customAttributes[$attr] = $image->getUrl();
                } else {
                    $attribute_value = $product->getData($attr);
                    if ($attribute_value)
                        $customAttributes[$attr] = htmlspecialchars_decode($product->getResource()->getAttribute($attr)->getFrontend()->getValue($product));
                }
            }
        }

        $emulator->stopEnvironmentEmulation();

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
            'cache_image' => $image,
            'thumbnail_cache_image' => $thubnail_cache_image,
            'small_cache_image' => $small_cache_image,
            'base_cache_image' => $base_cache_image
        );
        if ($this->discountFilterEnabled) {
            if ($productPrice) {
                $discount_percentage = (($productPrice - $productSpecialPrice) / $productPrice) * 100;
                $productArray['discount_percentage'] = round($discount_percentage);
            }
        }
        $array = array_merge($productArray, $catpathArray, $catlevelArray, $customAttributes, $configurableAttributes);

        return $array;
    }

    public function getProductCollection($storeId)
    {
        $productIds = array();

        $productCollection = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');

        $collection = $productCollection->create()
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter('status', array('eq' => 1));

        $collection->getSelect()->joinLeft(array(
            'link_table' => 'catalog_product_super_link'),
            'link_table.product_id = e.entity_id',
            array('product_id')
        );

        $collection->getSelect()->where('link_table.product_id IS NULL');

        $collection->load();

        foreach ($collection as $product)
            $productIds[] = $product->getId();

        return $productIds;
    }

    public function deleteFullSync($storeId)
    {
        $count = 1000;
        $skip = 0;
        $productIds = array();

        $dbIds = $this->getProductCollection($storeId);

        while (true) {
            $results = $this->st->searchtapCurlSearchRequest($count, $skip);
            if (!$results) break;

            foreach ($results as $object) {
                $productIds[] = $object->id;
            }

            $idsToBeDeleted = array_values(array_diff($productIds, $dbIds));
            if (count($idsToBeDeleted) > 0)
                $this->deleteInactiveProducts($idsToBeDeleted);

            unset($productIds);
            $skip += $count;
        }
        //check if there is any product ID in queue that need to be deleted
        $this->checkQueueForDelete();
    }

    public function deleteInactiveProducts($idsToBeDeleted)
    {
        date_default_timezone_set('Asia/Kolkata');
        $date = date('Y-m-d H:i:s');

        $response = $this->st->searchtapCurlDeleteRequest($idsToBeDeleted);
        if ($response["responseHttpCode"] != 200) {
            try {
                $model = $this->objectManager->create('Gs\Searchtap\Model\Queue');

                foreach ($idsToBeDeleted as $id) {
                    $data = array(
                        'product_id' => $id,
                        'action' => 'delete',
                        'last_sent_at' => $date
                    );

                    $model->setData($data)->save();
                }

            } catch (error $error) {
                $this->logger->info($error);
            }
        }
        return $response;
    }

    public function checkQueueForDelete()
    {
        $idsToBeDeleted = array();
        $model = $this->objectManager->create('Gs\Searchtap\Model\Queue');
        $collection = $model->getCollection()
            ->addFieldToSelect('product_id')
            ->addFieldToFilter('action', array('eq' => 'delete'));

        foreach ($collection as $entity) {
            $idsToBeDeleted[] = $entity->getId();
        }

        if (count($idsToBeDeleted) > 0) {
            $response = $this->deleteInactiveProducts($idsToBeDeleted);
            if ($response["responseHttpCode"] == 200) {
                foreach ($collection as $entity)
                    $entity->delete();
            }
        }
    }
}


