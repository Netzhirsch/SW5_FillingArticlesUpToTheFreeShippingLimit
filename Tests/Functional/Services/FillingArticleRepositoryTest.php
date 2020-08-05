<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Services;

use Enlight_Components_Test_Controller_TestCase;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\FillingArticleRepository;
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
                $fillingArticles = [];

                if ($article->getMainDetail()->getShippingFree())
                    continue;

                $id = $article->getId();
                $articlePrice = $article->getMainDetail()->getPrices()[0]->getPrice();
                $articleTax = $article->getTax()->getTax();

                $sShippingcostsDifference =
                    (float)$shippingcost['shippingFree']-($articlePrice + ($articlePrice/100*$articleTax));

                if ($sShippingcostsDifference > 0) {
                    if ($pluginInfos['topSeller']) {
                        $fillingArticles = $this->topSeller(
                            $fillingArticlesRepository,
                            $pluginInfos,
                            $id,
                            $sShippingcostsDifference
                        );
                    }
                    if (!empty($pluginInfos['productStream'])) {
                        $this->productStreams(
                            $fillingArticlesRepository,
                            $fillingArticles,
                            $pluginInfos,
                            $id,
                            $sShippingcostsDifference
                        );
                    }
                    if (!empty(trim($pluginInfos['consider']))) {
                        $this->CategoryManufacture(
                            $fillingArticlesRepository,
                            $fillingArticles,
                            $id,
                            $pluginInfos,
                            $sShippingcostsDifference
                        );
                    }
                }
            }
        }
    }

    private function topSeller(
        FillingArticleRepository $fillingArticlesRepository,
        $pluginInfos,
        $id,
        $sShippingcostsDifference
    ){
        $fillingArticles
            = $fillingArticlesRepository->getFillingArticlesFromTopSeller(
            [],$pluginInfos,[$id=>$id],$sShippingcostsDifference,[]
        );
        $this->assertTrue(
            !empty($fillingArticles),
            'Zum Artikel: '
            .$id.
            ' werden keine TopSeller gefunden, die die VSKFG von '.
            $sShippingcostsDifference. ' erreichen.');

        return $fillingArticles;
    }

    private function productStreams(
        FillingArticleRepository $fillingArticlesRepository,
        $fillingArticles,
        $pluginInfos,
        $id,
        $sShippingcostsDifference
    ){
        $fillingArticles
            = $fillingArticlesRepository->getFillingArticlesFromProductStreams(
            $fillingArticles,$pluginInfos,[$id=>$id],$sShippingcostsDifference,[]
        );
        $this->assertTrue(
            !empty($fillingArticles),
            'Zum Artikel: '
            .$id.
            ' werden keine Artikel vom Product Streams gefunden, die die VSKFG von '.
            $sShippingcostsDifference. ' erreichen.');
    }

    private function CategoryManufacture(
        FillingArticleRepository $fillingArticlesRepository,
        $fillingArticles,
        $id,
        $pluginInfos,
        $sShippingcostsDifference
    ){
        $fillingArticles =
            $fillingArticlesRepository
                ->getQueryForCategoryManufacture($fillingArticles,[$id=>$id],$pluginInfos,$sShippingcostsDifference,[]);
        $this->assertTrue(
            !empty($fillingArticles),
            'Zum Artikel: '
            .$id.
            ' werden keine Artikel vom Kategorie gefunden, die die VSKFG von '.
            $sShippingcostsDifference. ' erreichen.');
    }
}