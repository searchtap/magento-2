<?php
namespace Gs\Searchtap\Model\ResourceModel;


class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected $_isPkAutoIncrement = false;

    public function _construct()
    {
        $this->_init('gs_searchtap_queue', 'product_id');
    }

}