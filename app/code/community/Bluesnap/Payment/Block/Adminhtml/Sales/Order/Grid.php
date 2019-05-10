<?php

class Bluesnap_Payment_Block_Adminhtml_Sales_Order_Grid
    extends Mage_Adminhtml_Block_Sales_Order_Grid
{
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel($this->_getCollectionClass());

        $orderTable = Mage::getSingleton('core/resource')
            ->getTableName('sales/order');
        $paymentTable = Mage::getSingleton('core/resource')
            ->getTableName('sales/order_payment');

        $collection->getSelect()
            ->joinLeft(
                array('so' => $orderTable),
                'so.entity_id = main_table.entity_id',
                array(
                    'bluesnap_reference_number',
                    'bluesnap_currency_rate',
                    'bluesnap_grand_total',
                )
            )
            ->joinLeft(
                array('p' => $paymentTable),
                'p.parent_id = main_table.entity_id',
                array(
                    'method AS payment_method',
                )
            );

        $this->setCollection($collection);

        if ($this->getCollection()) {
            $this->_preparePage();

            $columnId = $this->getParam(
                $this->getVarNameSort(),
                $this->_defaultSort
            );

            $dir = $this->getParam($this->getVarNameDir(), $this->_defaultDir);
            $filter = $this->getParam($this->getVarNameFilter(), null);

            if ($filter === null) {
                $filter = $this->_defaultFilter;
            }

            if (is_string($filter)) {
                $data = $this->helper('adminhtml')->prepareFilterString($filter);
                $this->_setFilterValues($data);
            } else {
                if ($filter && is_array($filter)) {
                    $this->_setFilterValues($filter);
                } else {
                    if (count($this->_defaultFilter) !== 0) {
                        $this->_setFilterValues($this->_defaultFilter);
                    }
                }
            }

            if (isset($this->_columns[$columnId])
                && $this->_columns[$columnId]->getIndex()
            ) {
                $dir = (strtolower($dir) === 'desc') ? 'desc' : 'asc';
                $this->_columns[$columnId]->setDir($dir);
                $this->_setCollectionOrder($this->_columns[$columnId]);
            }

            if (!$this->_isExport) {
                $this->getCollection()->load();
                $this->_afterLoadCollection();
            }
        }

        return $this;
    }

    protected function _prepareColumns()
    {
        parent::_prepareColumns();

        $helper = Mage::helper('bluesnap');

        $this->getColumn('real_order_id')
            ->setFilterIndex('main_table.increment_id');

        $this->getColumn('created_at')
            ->setFilterIndex('main_table.created_at');

        $this->getColumn('base_grand_total')
            ->setFilterIndex('main_table.base_grand_total');

        $this->getColumn('grand_total')
            ->setFilterIndex('main_table.grand_total');

        $this->getColumn('status')
            ->setFilterIndex('main_table.status');

        $this->addColumnAfter(
            'payment_method',
            array(
                'header' => $helper->__('Payment Method'),
                'index' => 'payment_method',
                'filter_index' => 'p.method',
                'width' => '70px',
                'renderer' => 'Bluesnap_Payment_Block_Adminhtml_Sales_Order_Grid_Renderer_Paymentmethod',
            ),
            'grand_total'
        );

        $this->addColumnAfter(
            'bluesnap_reference_number',
            array(
                'header' => $helper->__('Bluesnap Reference Number'),
                'index' => 'bluesnap_reference_number',
                'filter_index' => 'so.bluesnap_reference_number',
                'type' => 'text',
            ),
            'payment_method'
        );

        $this->addColumnAfter(
            'bluesnap_currency_rate',
            array(
                'header' => $helper->__('Bluesnap Currency Rate'),
                'index' => 'bluesnap_currency_rate',
                'filter_index' => 'so.bluesnap_currency_rate',
                'type' => 'number',
            ),
            'grand_total'
        );


        $this->addColumnAfter(
            'bluesnap_grand_total',
            array(
                'header' => $helper->__('Bluesnap Grand Total'),
                'index' => 'bluesnap_grand_total',
                'filter_index' => 'so.bluesnap_grand_total',
                'type' => 'currency',
                'currency' => 'bluesnap_currency_code',
            ),
            'bluesnap_currency_rate'
        );

        $this->sortColumnsByOrder();

        return $this;
    }
}
