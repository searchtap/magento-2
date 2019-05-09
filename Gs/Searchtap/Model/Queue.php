<?php
namespace Gs\Searchtap\Model;

use Magento\Cron\Exception;
use Magento\Framework\Model\AbstractModel;

class Queue extends AbstractModel
{
    const CACHE_TAG = 'gs_searchtap_queue';

    protected $_cacheTag = 'gs_searchtap_queue';

    protected $_eventPrefix = 'gs_searchtap_queue';

    protected function _construct()
    {
        $this->_init(\Gs\Searchtap\Model\ResourceModel\Queue::class);
    }
}