<?php

namespace Gs\Searchtap\Model\Config\Source;


class GetDiscountRange implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => "5", 'label' => __('5 (1% - 5%, 6% - 10%, ...)')],
            ['value' => "10", 'label' => __('10 (1% - 10%, 11% - 20%, ...)')],
            ['value' => "20", 'label' => __('20 (1% - 20%, 21% - 40%, ...)')],
            ['value' => "25", 'label' => __('25 (1% - 25%, 26% - 50%, ...)')],
            ['value' => "50", 'label' => __('50 (1% - 50%, 51% - 100%, ...)')]
        ];
    }
}