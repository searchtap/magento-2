<?php

namespace Gs\Searchtap\Model\ResourceModel;

class Searchtap extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('gs_searchtap_queue', 'product_id');
        $this->_isPkAutoIncrement = false;
    }
}