<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Services;

use Enlight_Components_Test_Controller_TestCase;
use Shopware\Models\Article\Article;
use Shopware\Models\Dispatch\Dispatch;

class FillingArticleRepositoryTest extends Enlight_Components_Test_Controller_TestCase
{

    public function testGetFillingArticlesFromTopSeller(){

        $fillingArticlesRepository
            = Shopware()
            ->Container()
            ->get(
                'netzhirsch_filling_articles_up_to_the_free_shipping_limit.services.filling_article_repository'
            );

        $configReader = Shopware()->Container()->get('shopware.plugin.cached_config_reader');
        $pluginInfos = $configReader->getByPluginName('NetzhirschFillingArticlesUpToTheFreeShippingLimit');

        Shopware()->Container()->get('request_stack')->push($this->__get('request'));

        //********* top seller without combine and price over 30€ *****************************************************/

        $qb = Shopware()->Container()->get('models')->createQueryBuilder();
        $articles = $qb->select('article')
            ->from(Article::class,'article')
            ->getQuery()
            ->getResult();

        /** @var Article[] $articles */
        $shippingcosts
            = Shopware()->Models()->getRepository(Dispatch::class)->getShippingCostsQuery()->getArrayResult();

        foreach ($shippingcosts as $shippingcost) {
            foreach ($articles as $article) {
                $id = $article->getId();
                $articlePrice = $article->getMainDetail()->getPrices()[0]->getPrice();
                $shippingCostDifferent = (float)$shippingcost['shippingFree']-$articlePrice;
                if ($shippingCostDifferent > 0) {
                    $fillingArticles
                        = $fillingArticlesRepository->getFillingArticlesFromTopSeller(
                            [],$pluginInfos,[$id=>$id],$shippingCostDifferent,[]
                    );
                    $this->assertTrue(
                        empty($fillingArticles),
                        'Zum Artikel: '.$article->getId().' werden keine TopSeller gefunden, die die VSKFG von '.$shippingCostDifferent. 'erreichen.');
                }
            }
        }
    }
}