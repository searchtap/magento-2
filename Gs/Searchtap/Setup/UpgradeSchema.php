<?php

namespace Gs\Searchtap\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '2.0.0', '<=')) {
            $table = $setup->getConnection()
                ->newTable($setup->getTable('gs_searchtap_queue'))
                ->addColumn(
                    'product_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'primary' => true],
                    'Product ID'
                )
                ->addColumn(
                    'action',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 300,
                    [
                        'nullable' => false
                    ],
                    'Action'
                )
                ->addColumn(
                    'last_sent_at',
                    \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                    null,
                    [],
                    'Last Sent At'
                )->setComment("SearchTap Queue Table");
            $setup->getConnection()->createTable($table);
        }
        $setup->endSetup();
    }
}