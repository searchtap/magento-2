<?php
namespace Gs\Searchtap\Model\ResourceModel\Queue;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'product_id';
    protected $_eventPrefix = 'gs_searchtap_queue_collection';
    protected $_eventObject = 'queue_collection';

    public function _construct()
    {
        $this->_init('Gs\Searchtap\Model\Queue', 'Gs\Searchtap\Model\ResourceModel\Queue');
    }

}