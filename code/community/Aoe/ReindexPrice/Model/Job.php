<?php

class Aoe_ReindexPrice_Model_Job {

    /**
     * Method called by sheduler
     */
    public function process() {

        $result = 'Reindexed products from stores:' . PHP_EOL;
        /** @var $store Mage_Core_Model_Store */
        foreach (Mage::app()->getStores() as $store) {
            $ids = array();
            /** @var $appEmulation Mage_Core_Model_App_Emulation */
            $appEmulation = Mage::getSingleton('core/app_emulation');
            //Start environment emulation of the specified store
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($store->getId());

            $hour = date('H', Mage::getModel('core/date')->timestamp(time()));
            //it's just after midnight in the current timezone
            if ($hour <= 1 && $hour >= 0) {
                $ids = $this->getProductIds();
                $this->reindex($ids);
                $this->clearCache($ids);
            }
            $result .= 'Store: '.$store->getName() . ' products: '. count($ids) . PHP_EOL;

            //Stop environment emulation and restore original store
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
        return $result;
    }

    protected function getProductIds() {
        $date = Mage::app()->getLocale()->date();
        $dateArray = $date->toArray();
        $dateToday = mktime(0, 0, 0, $dateArray['month'], $dateArray['day'], $dateArray['year']);
        $dateToday = date('Y-m-d H:i:s', $dateToday);
        $yesterday = mktime(0, 0, 0, $dateArray['month'], $dateArray['day'] - 1, $dateArray['year']);
        $dateYesterday = date('Y-m-d H:i:s', $yesterday);

        /** @var $products Mage_Catalog_Model_Resource_Product_Collection */
        $products = Mage::getResourceModel('catalog/product_collection');

        $products->addAttributeToFilter('visibility', array('neq' => 1))
            ->addAttributeToFilter('special_price', array('neq' => ''))
            ->addAttributeToFilter(array(
                array(
                  'attribute' => 'special_to_date',
                  'eq' => $dateYesterday
                ),
                array(
                  'attribute' => 'special_from_date',
                  'eq' => $dateToday
                )
            )
        );
        //Mage::log((string)$products->getSelect());

        $ids = $products->getAllIds();
        return $ids;
    }

    protected function reindex($ids) {

        /** @var $indexProcess Mage_Index_Model_Process */
        $indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price');
        if ($indexProcess) {
          $indexer = $indexProcess->getIndexer();
          /** @var $indexer Mage_Catalog_Model_Product_Indexer_Price */
          $indexer->getResource()->reindexProductIds($ids);
        }
    }

    protected function clearCache($ids) {
        $tags = array();
        foreach ($ids as $id) {
            $tags[] = Mage_Catalog_Model_Product::CACHE_TAG . '_' . $id;
        }

        Mage::app()->cleanCache($tags);
    }
}