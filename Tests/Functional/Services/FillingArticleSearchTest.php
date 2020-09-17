<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Services;

use Doctrine\ORM\NonUniqueResultException;
use Enlight_Components_Test_Controller_TestCase;
use Enlight_Exception;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Struct\FillingArticleQueryInfos;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\FillingArticleSearch;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Sorting;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Models\Article\Article;

class FillingArticleSearchTest extends Enlight_Components_Test_Controller_TestCase
{

    /**
     * @group repository
     * @throws NonUniqueResultException
     * @throws Enlight_Exception
     */
    public function testFillingArticlesRepository()
    {

        $fillingArticlesRepository
            = Shopware()
            ->Container()
            ->get(
                'netzhirsch_filling_articles_up_to_the_free_shipping_limit.services.filling_article_repository'
            );

        $pluginInfos = $this->getDefaultConfig();

        Shopware()->Front()->setRequest($this->__get('request'));

        $qb = Shopware()->Container()->get('models')->createQueryBuilder();
        /** @var Article[] $articles */
        $articles = $qb->select('article')
            ->from(Article::class, 'article')
            ->getQuery()
            ->getResult();

        //********* fill articel filter *******************************************************************************/
        $shippingcosts = 30;
        //********* one filter option *********************************************************************************/
        $this->filterTest(
            $shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'topSeller'
        );
        $pluginInfos['topSeller'] = 0;
        $pluginInfos['productStream'] = ['Kunden kauften auch'];
        $this->filterTest(
            $shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'Kunden kauften auch'
        );

            //********* consider **************************************************************************************/
        $pluginInfos['productStream'] = null;
        $pluginInfos['consider'] = 'category';
        $this->filterTest($shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'category');

        $pluginInfos['consider'] = 'supplier';
        $this->filterTest($shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'category');

        $pluginInfos['consider'] = 'categoryAndSupplier';
        $this->filterTest(
            $shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'categoryAndSupplier'
        );

        $pluginInfos['consider'] = 'categoryOrSupplier';
        $this->filterTest(
            $shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'categoryAndSupplier'
        );
        $pluginInfos['consider'] = null;


        $pluginInfos['customersAlsoBought'] = true;
        $this->filterTest(
            $shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'customersAlsoBought'
        );
        $pluginInfos['customersAlsoBought'] = false;

        $pluginInfos['accessories'] = true;
        $this->filterTest(
            $shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'accessories'
        );
        $pluginInfos['accessories'] = false;

        $pluginInfos['similarArticles'] = true;
        $this->filterTest(
            $shippingcosts, $articles, $fillingArticlesRepository, $pluginInfos,'similarArticles'
        );
        $pluginInfos['similarArticles'] = false;

        //********* multi filter options ******************************************************************************/
        $pluginInfos['productStream'] = ['Kunden kauften auch','abverkauf'];
        $this->filterTest(
            $shippingcosts,
            $articles,
            $fillingArticlesRepository,
            $pluginInfos,
            'Kunden kauften auch,abverkauf'
        );

        $pluginInfos['productStream'] = ['Kunden kauften auch'];
        $pluginInfos['consider'] = 'category';
        $this->filterTest(
            $shippingcosts,
            $articles,
            $fillingArticlesRepository,
            $pluginInfos,
            'Kunden kauften auch,category'
        );
        $this->filterTest(
            $shippingcosts,
            $articles,
            $fillingArticlesRepository,
            $pluginInfos,
            'abverkauf,category');
        $pluginInfos['productStream'] = ['abverkauf'];
        $pluginInfos['consider'] = 'category';
    }

    /**
     * @param float $shippingcosts
     * @param array $articles
     * @param FillingArticleSearch $fillingArticlesRepository
     * @param array $pluginInfos
     * @param string $filtername
     */
    public function filterTest(
        float $shippingcosts,
        array $articles,
        FillingArticleSearch $fillingArticlesRepository,
        array $pluginInfos,
        string $filtername
    ) {
        $excludedArticles = $articles;
        foreach ($excludedArticles as $excludedArticle) {
            $pluginInfos['excludedArticles'] = [$excludedArticle->getId()];

            foreach ($articles as $article) {

                if ($article->getMainDetail()->getShippingFree()) {
                    continue;
                }

                $articlePrice = round($article->getMainDetail()->getPrices()[0]->getPrice(),2);
                $articleTax = (int)$article->getTax()->getTax();
                $percent = $articlePrice / 100;
                $percent *= $articleTax;
                $articlePrice += $percent;
                $articlePrice = round($articlePrice,2);
                $sShippingcostsDifference = $shippingcosts - $articlePrice;

                if ($sShippingcostsDifference > 0) {
                    $fillingArticles = [];
                    $id = $article->getId();

                    $fillingArticleQueryInfos = new FillingArticleQueryInfos(
                        $pluginInfos,
                        $sShippingcostsDifference,
                        [$article->getSupplier()->getId()],
                        [0 => $id],
                        [],
                        [],
                        $fillingArticles
                    );

                    $fillingArticleQueryInfos->addFillingArticles(
                        $this->topSeller(
                            $fillingArticlesRepository,
                            $fillingArticleQueryInfos,
                            $filtername,
                            $id
                        )
                    );

                    $fillingArticleQueryInfos->addFillingArticles(
                        $this->productStreams(
                            $fillingArticlesRepository,
                            $fillingArticleQueryInfos,
                            $filtername
                        )
                    );

                    $fillingArticleQueryInfos->addFillingArticles(
                        $this->categoryManufacture(
                            $fillingArticlesRepository,
                            $fillingArticleQueryInfos
                        )
                    );

                    $fillingArticleQueryInfos->addFillingArticles(
                        $this->alsoBought(
                            $fillingArticlesRepository,
                            $fillingArticleQueryInfos
                        )
                    );

                    $fillingArticleQueryInfos->addFillingArticles(
                        $this->accessories(
                            $fillingArticlesRepository,
                            $fillingArticleQueryInfos
                        )
                    );

                    $fillingArticleQueryInfos->addFillingArticles(
                        $this->similarArticles(
                            $fillingArticlesRepository,
                            $fillingArticleQueryInfos,
                            $shippingcosts
                        )
                    );
                    $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();
                    if (!empty($fillingArticles)) {
                        $sortingTest = new Sorting();
                        $sortingTest->sorting($pluginInfos,$fillingArticlesRepository,$fillingArticles);
                    }
                }
            }
        }

    }

    private function accessories(
        FillingArticleSearch $fillingArticlesRepository,
        FillingArticleQueryInfos $fillingArticleQueryInfos
    ) {

        $fillingArticlesFromAccessories = $fillingArticlesRepository->getFillingArticlesFromAccessories(
            $fillingArticleQueryInfos
        );
        $idsAsString = implode(",",$fillingArticleQueryInfos->getArticleIdsFromBasket());
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if ($pluginInfos['accessories'] == 0) {
            $this->assertTrue(
                empty($fillingArticlesFromAccessories),
                'Zum Artikel: '
                .$idsAsString.
                ' werden Zugehör gefunden, obwohl in den Einstellungen nicht aktiv ist.'
            );
        } else {
            if (in_array(1,$fillingArticleQueryInfos->getArticleIdsFromBasket()))
                $this->assertCount(2,$fillingArticlesFromAccessories,$idsAsString);
            else
                $this->assertCount(0,$fillingArticlesFromAccessories,$idsAsString);
        }

        return $fillingArticlesFromAccessories;
    }

    private function topSeller(
        FillingArticleSearch $fillingArticlesRepository,
        FillingArticleQueryInfos $fillingArticleQueryInfos,
        $filtername,
        $id
    ) {
        $fillingArticleQueryInfos->addFillingArticles(
            $fillingArticlesRepository->getFillingArticlesFromTopSeller($fillingArticleQueryInfos)
        );
        $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();

        $idsAsString = implode(',',$fillingArticleQueryInfos->getFillingArticles());
        $sShippingcostsDifference = $fillingArticleQueryInfos->getSShippingcostsDifference();

        if ($filtername == 'topSeller') {
            $this->assertTrue(
                !empty($fillingArticles),
                'Zum Artikel: '
                .$idsAsString.
                ' werden keine TopSeller gefunden, die die VSKFG von '.
                $fillingArticleQueryInfos->getSShippingcostsDifference().' erreichen. Mit dem Filter'.$filtername
            );
        } else {
            $this->assertTrue(
                empty($fillingArticles),
                'Zum Artikel: '
                .$idsAsString.
                ' werden TopSeller gefunden, die die VSKFG von '.
                $sShippingcostsDifference.' erreichen. Mit dem Filter'.$filtername
            );
        }
        $this->excludesArticles($fillingArticleQueryInfos,$id);
        return $fillingArticles;
    }

    /**
     * @param FillingArticleSearch $fillingArticlesRepository
     * @param FillingArticleQueryInfos $fillingArticleQueryInfos
     * @param $filtername
     * @return array
     */
    private function productStreams(
        FillingArticleSearch $fillingArticlesRepository,
        FillingArticleQueryInfos $fillingArticleQueryInfos,
        $filtername
    ) {
        $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if ($pluginInfos['productStream'] != null)
            return $fillingArticles;

        $fillingArticlesAll = [];
        $fillingArticlesAll = array_merge($fillingArticles,$fillingArticlesAll);
        foreach ($pluginInfos['productStream'] as $productStream) {

            $fillingArticleQueryInfos->addFillingArticles(
                $fillingArticlesRepository->getFillingArticlesFromProductStreams(
                    $fillingArticleQueryInfos
                )
            );
            $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();
            $fillingArticlesAll = array_merge($fillingArticles,$fillingArticlesAll);

            $return = $fillingArticlesRepository->createContextAndConditionCriteria(
                $fillingArticleQueryInfos
            );

            /** @var Criteria $criteria */
            $criteria = $return['criteria'];

            $productStreamId = 3;
            if ($productStream == 'abverkauf')
                $productStreamId = 1;
            Shopware()->Container()->get('shopware_product_stream.repository')
                ->prepareCriteria($criteria, $productStreamId);

            $variantSearch = Shopware()->Container()->get('shopware_search.variant_search');
            $searchQuery = $variantSearch->search($criteria, $return['context']);

            $products = $searchQuery->getProducts();

            $this->assertCount(
                count($products),
                $fillingArticles,
                    'zu viele/wenige im Product Stream: expect: '
                    .count($products).' actual: '
                    .count($fillingArticles).
                    ' Artikel im Warenkorb: '.implode(',',$fillingArticleQueryInfos->getFillingArticles()).
                    ' Product Stream: '.$productStream.
                    ' Betrag zur VSKFG: '.$fillingArticleQueryInfos->getSShippingcostsDifference().
                    ' Filtername: '.$filtername
            );

            foreach ($products as $product) {
                foreach ($fillingArticles as $key => $fillingArticle) {
                    if ($fillingArticle['articleID'] == $product->getId()) {
                        unset($fillingArticles[$key]);
                    }
                }
            }
            $this->assertEmpty(
                $fillingArticles,
                'Die Artikel: '
                .implode(', ', array_keys($fillingArticles)).
                ' sind nicht im Product Streams.'
            );
        }
        return $fillingArticlesAll;
    }

    /**
     * @param FillingArticleSearch $fillingArticlesRepository
     * @param FillingArticleQueryInfos $fillingArticleQueryInfos
     * @return array
     */
    private function categoryManufacture(
        FillingArticleSearch $fillingArticlesRepository,
        FillingArticleQueryInfos $fillingArticleQueryInfos
    ) {
        $fillingArticleQueryInfos->addFillingArticles(
            $fillingArticlesRepository
                ->getFillingArticlesFromCategoryManufacture(
                    $fillingArticleQueryInfos
                )
        );

        $categoriesBasketArticle = $fillingArticlesRepository->getCategoryIdsFromArticleIds(
            $fillingArticleQueryInfos->getArticleIdsFromBasket()
        );
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();

        foreach ($fillingArticleQueryInfos->getFillingArticles() as $fillingArticle) {
            $categoryFillingArticle = $fillingArticlesRepository->getCategoryIdsFromArticleIds(
                $fillingArticle['articleID']
            );
            $largeArray
                = (
            count($categoriesBasketArticle) > count($categoryFillingArticle)
                ? $categoriesBasketArticle
                : $categoryFillingArticle
            );
            $smallArray = (
            count($categoriesBasketArticle) < count($categoryFillingArticle)
                ? $categoriesBasketArticle
                : $categoryFillingArticle
            );
            foreach ($categoriesBasketArticle as $categoryBasketArticle) {
                $isInCategory = false;
                foreach ($largeArray as $largeCategory) {
                    foreach ($smallArray as $smallCategory) {
                        if ($largeCategory == $smallCategory) {
                            $isInCategory = true;
                            break;
                        }
                    }
                }
                if ($pluginInfos['consider'] == 'categoryAndSupplier' || $pluginInfos['consider'] == 'category') {
                    $this->assertTrue(
                        $isInCategory,
                        'KategorieId im Warenkorb:'
                        .$categoryBasketArticle.' Kategorie im Füllartikel:'.$categoryFillingArticle
                    );
                } elseif ($pluginInfos['consider'] == 'categoryAndSupplier' || $pluginInfos['consider'] == 'supplier') {
                    $this->assertTrue(
                        $fillingArticleQueryInfos->getSupplierIds() != $fillingArticle['supplier'],
                        'Hersteller im Warenkorb:'
                        .$categoryBasketArticle.' Hersteller im Füllartikel:'.$categoryFillingArticle
                    );
                } elseif ($pluginInfos['consider'] == 'categoryOrSupplier') {
                    if ($fillingArticleQueryInfos->getSupplierIds() != $fillingArticle['supplier']) {
                        $this->assertTrue($isInCategory);
                    } else {
                        $this->assertFalse($isInCategory);
                    }
                }
            }
        }
        return $fillingArticleQueryInfos->getFillingArticles();
    }

    public static function getDefaultConfig()
    {

        return [
            'noteArticle' => 1,
            'noteAboveBasket' => 1,
            'viewInAjaxBasket' => 0,
            'displayVariants' => 'show under the basket',
            'maxArticle' => 20,
            'isCombineAllowed' => 0,
            'minimumArticlePrice' => null,
            'minimumArticlePriceUnit' => '€',
            'maximumArticlePrice' => null,
            'maximumArticlePriceUnit' => '€',
            'maximumOverhang' => null,
            'sorting' => 'randomly',
            'excludedArticles' => null,
            'productStream' => null,
            'consider' => "",
            'customersAlsoBought' => 0,
            'similarArticles' => 0,
            'accessories' => 0,
            'topSeller' => 1,
        ];
    }



    private function alsoBought(
        FillingArticleSearch $fillingArticlesRepository,
        FillingArticleQueryInfos $fillingArticleQueryInfos
    ) {
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if (!$pluginInfos['customersAlsoBought'])
            return $fillingArticleQueryInfos->getFillingArticles();

        $fillingArticleQueryInfos->addFillingArticles(
            $fillingArticlesRepository->getFillingArticlesFromAlsoBought(
                $fillingArticleQueryInfos
            )
        );
        $idsAsString = implode(",",$fillingArticleQueryInfos->getArticleIdsFromBasket());
        $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();
        if (in_array(5,$fillingArticleQueryInfos->getArticleIdsFromBasket())) {
            $this->assertCount(1,$fillingArticles,$idsAsString);
        } else {
            $this->assertCount(0,$fillingArticles,$idsAsString);
        }
        return $fillingArticles;
    }

    private function similarArticles(
        FillingArticleSearch $fillingArticlesRepository,
        FillingArticleQueryInfos $fillingArticleQueryInfos,
        $shippingcosts
    ) {

        $fillingArticles = $fillingArticlesRepository->getFillingArticlesFromSimilar(
            $fillingArticleQueryInfos
        );
        $idsAsString = implode(",",$fillingArticleQueryInfos->getArticleIdsFromBasket());
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if (!$pluginInfos['similarArticles']) {
            $this->assertTrue(
                empty($fillingArticlesFromAccessories),
                'Zum Artikel: '
                .$idsAsString.
                ' werden ähnliche Artikel gefunden, obwohl in den Einstellungen nicht aktiv.'
            );
            return $fillingArticles;
        }

        $idsWithSimilar = [
          5,
          7,
          11,
          12
        ];
        if ($shippingcosts == 30) {
            if (in_array($fillingArticleQueryInfos->getArticleIdsFromBasket()[0],$idsWithSimilar)) {
                $this->assertCount(1,$fillingArticles,$idsAsString);
            } else {
                $this->assertCount(0,$fillingArticles,$idsAsString);
            }
        }
        return $fillingArticles;
    }

    private function excludesArticles(FillingArticleQueryInfos $fillingArticleQueryInfos,$basketArticleId)
    {
        $excludedArticles = $fillingArticleQueryInfos->getPluginInfos()['excludedArticles'];
        foreach ($fillingArticleQueryInfos->getFillingArticles() as $fillingArticle) {

            ;
                $this->assertFalse(
                    in_array($fillingArticle['articleID'],$excludedArticles)
                    ,'Artikel Id:'.$fillingArticle['articleID'].' ist ein Füllartikel obwohl:'.
                    implode(',',$excludedArticles).' beim warenkorb ArtikelId: '.$basketArticleId
                );
        }
    }
}