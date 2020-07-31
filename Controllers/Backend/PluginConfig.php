<?php

use Shopware\Bundle\SitemapBundle\UrlFilter\Product;
use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Backend_PluginConfig extends Shopware_Controllers_Backend_Application implements CSRFWhitelistAware
{
//    model must be set even if none is used.
    protected $model = Product::class;

    public function getVariantGroupsAction()
    {
        $this->setDataFromDBToTemplate('SELECT id,name FROM s_article_configurator_groups');
    }

    public function getProductStreamsAction()
    {
        $this->setDataFromDBToTemplate('SELECT id,name FROM s_product_streams');
    }

    private function setDataFromDBToTemplate($sql)
    {
        $variantGroups = Shopware()->Db()->fetchAll($sql, []);
        $this->view->assign(['data' => $variantGroups,'total' => count($variantGroups)]);
    }

    /**
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'getVariantGroups',
            'getProductStreams'
        ];
    }
}