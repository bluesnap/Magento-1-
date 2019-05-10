<?php

$installer = $this;
$installer->startSetup();

try {
    $installer->getConnection()
        ->addColumn(
            $installer->getTable('sales/creditmemo'),
            'bluesnap_reversal_ref_num',
            Varien_Db_Ddl_Table::TYPE_INTEGER
        );
} catch (Exception $e) {
    // Do nothing.
}

$installer->endSetup();
