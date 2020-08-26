<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional;

use NetzhirschFillingArticlesUpToTheFreeShippingLimitTestKernel;
use PHPUnit\Framework\TestCase;

class PluginConfigTest extends TestCase
{

    public function testIsPluginInstalledAndActivated()
    {
        $installiert = NetzhirschFillingArticlesUpToTheFreeShippingLimitTestKernel::isPluginInstalledAndActivated();
        $this->assertTrue($installiert);
    }

    public function testConfig()
    {
        $configReader = Shopware()->Container()->get('shopware.plugin.cached_config_reader');
        $config = $configReader->getByPluginName('NetzhirschFillingArticlesUpToTheFreeShippingLimit');
        $defaultConfig = $this->getDefaultConfig();
        foreach ($defaultConfig as $keyDefault => $entryDefault) {
            $this->assertArrayHasKey($keyDefault,$config,$keyDefault);
        }
    }

    private function getDefaultConfig(){

        return array_fill_keys([
            'noteArticle',
            'noteAboveBasket',
            'viewInAjaxBasket',
            'displayVariants',
            'maxArticle',
            'isCombineAllowed',
            'minimumArticlePrice',
            'minimumArticlePriceUnit',
            'maximumArticlePrice',
            'maximumArticlePriceUnit',
            'maximumOverhang',
            'sorting',
            'excludedArticles',
            'variantsGroup',
            'fillingArticleSeparator',
            'productStream',
            'consider',
            'customersAlsoBought',
            'similarArticles',
            'accessories',
            'topSeller',
        ],null
        );
    }
}