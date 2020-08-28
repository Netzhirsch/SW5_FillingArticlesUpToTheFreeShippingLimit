<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Services;

use Doctrine\ORM\NonUniqueResultException;
use Enlight_Components_Test_Controller_TestCase;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\FillingArticleRepository;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Models\Article\Article;

class FillingArticleRepositoryTest extends Enlight_Components_Test_Controller_TestCase
{

    /**
     * @group repository
     * @throws NonUniqueResultException
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

        Shopware()->Container()->get('request_stack')->push($this->__get('request'));

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

    private function accessories(
        FillingArticleRepository $fillingArticlesRepository,
        $id,
        $pluginInfos,
        $sShippingcostsDifference
    ) {
        $fillingArticlesFromAccessories
            = $fillingArticlesRepository->getFillingArticlesFromAccessories(
            [],
            $pluginInfos,
            [$id => $id],
            $sShippingcostsDifference
        );

        if ($pluginInfos['accessories'] == 0) {
            $this->assertTrue(
                empty($fillingArticlesFromAccessories),
                'Zum Artikel: '
                .$id.
                ' werden keine Zugehör gefunden, obwohl in den Einstellungen aktiv'
            );
        } else {
            if ($id == 1)
                $this->assertCount(2,$fillingArticlesFromAccessories,$id);
            else
                $this->assertCount(0,$fillingArticlesFromAccessories,$id);
        }

        return $fillingArticlesFromAccessories;
    }

    private function topSeller(
        FillingArticleRepository $fillingArticlesRepository,
        $pluginInfos,
        $id,
        $sShippingcostsDifference,
        $filtername
    ) {
        $fillingArticles
            = $fillingArticlesRepository->getFillingArticlesFromTopSeller(
            [],
            $pluginInfos,
            [$id => $id],
            $sShippingcostsDifference
        );
        if ($sShippingcostsDifference > 0) {
            if ($filtername == 'topSeller') {
                $this->assertTrue(
                    !empty($fillingArticles),
                    'Zum Artikel: '
                    .$id.
                    ' werden keine TopSeller gefunden, die die VSKFG von '.
                    $sShippingcostsDifference.' erreichen. Mit dem Filter'.$filtername
                );
            } else {
                $this->assertTrue(
                    empty($fillingArticles),
                    'Zum Artikel: '
                    .$id.
                    ' werden TopSeller gefunden, die die VSKFG von '.
                    $sShippingcostsDifference.' erreichen. Mit dem Filter'.$filtername
                );
            }
        } else {
            $this->assertTrue(
                empty($fillingArticles),
                'Zum Artikel: '
                .$id.
                ' werden TopSeller gefunden, obwohl die Differenz '.
                $sShippingcostsDifference.' erreichen. Mit dem Filter'.$filtername
            );
        }

        return $fillingArticles;
    }

    /**
     * @param FillingArticleRepository $fillingArticlesRepository
     * @param $fillingArticles
     * @param $pluginInfos
     * @param $id
     * @param $sShippingcostsDifference
     * @param $filtername
     * @throws NonUniqueResultException
     */
    private function productStreams(
        FillingArticleRepository $fillingArticlesRepository,
        $fillingArticles,
        $pluginInfos,
        $id,
        $sShippingcostsDifference,
        $filtername
    ) {
        if ($pluginInfos['productStream'] != null) {
            foreach ($pluginInfos['productStream'] as $productStream) {

                $fillingArticles
                        = $fillingArticlesRepository->getFillingArticlesFromProductStreams(
                        $fillingArticles,
                        ['productStream' => $productStream],
                        [$id => $id],
                        $sShippingcostsDifference
                    );
                    $return = $fillingArticlesRepository->createContextAndConditionCriteria(
                        $pluginInfos,[$id => $id],$sShippingcostsDifference
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
                            ' Artikel im Warenkorb: '.$id.
                            ' Product Stream: '.$productStream.
                            ' Betrag zur VSKFG: '.$sShippingcostsDifference.
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
        } else {
            $this->assertEmpty(
                $fillingArticles,
                'Kein Product Stream ausgewählt aber Artikel gefunden'
            );
        }
    }

    private function categoryAndManufacture(
        FillingArticleRepository $fillingArticlesRepository,
        $fillingArticles,
        $id,
        $pluginInfos,
        $sShippingcostsDifference,
        $manufacture
    ) {
        $fillingArticles =
            $fillingArticlesRepository
                ->getFillingArticlesFromCategoryManufacture(
                    $fillingArticles,
                    [$id => $id],
                    $pluginInfos,
                    $sShippingcostsDifference,
                    []
                );
        $categoriesBasketArticle = $fillingArticlesRepository->getCategoryIdsFromArticleIds($id);
        foreach ($fillingArticles as $fillingArticle) {
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
                        $manufacture != $fillingArticle['supplier'],
                        'Hersteller im Warenkorb:'
                        .$categoryBasketArticle.' Hersteller im Füllartikel:'.$categoryFillingArticle
                    );
                } elseif ($pluginInfos['consider'] == 'categoryOrSupplier') {
                    if ($manufacture != $fillingArticle['supplier']) {
                        $this->assertTrue($isInCategory);
                    } else {
                        $this->assertFalse($isInCategory);
                    }
                }
            }
        }
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
            'variantsGroup' => null,
            'productStream' => null,
            'consider' => "",
            'customersAlsoBought' => 0,
            'similarArticles' => 0,
            'accessories' => 0,
            'topSeller' => 1,
        ];
    }

    /**
     * @param float $shippingcosts
     * @param array $articles
     * @param FillingArticleRepository $fillingArticlesRepository
     * @param array $pluginInfos
     * @param string $filtername
     * @throws NonUniqueResultException
     */
    public function filterTest(
        float $shippingcosts,
        array $articles,
        FillingArticleRepository $fillingArticlesRepository,
        array $pluginInfos,
        string $filtername
    ): void {
        foreach ($articles as $article) {

            if ($article->getMainDetail()->getShippingFree()) {
                continue;
            }

            $id = $article->getId();
            $articlePrice = round($article->getMainDetail()->getPrices()[0]->getPrice(),2);
            $articleTax = (int)$article->getTax()->getTax();
            $percent = $articlePrice / 100;
            $percent *= $articleTax;
            $articlePrice += $percent;
            $articlePrice = round($articlePrice,2);
            $sShippingcostsDifference = $shippingcosts - $articlePrice;

            if ($sShippingcostsDifference > 0) {
                $this->topSeller(
                    $fillingArticlesRepository,
                    $pluginInfos,
                    $id,
                    $sShippingcostsDifference,
                    $filtername
                );
                $fillingArticles = [];
                $this->productStreams(
                    $fillingArticlesRepository,
                    $fillingArticles,
                    $pluginInfos,
                    $id,
                    $sShippingcostsDifference,
                    $filtername
                );
                $fillingArticles = [];
                $this->categoryAndManufacture(
                    $fillingArticlesRepository,
                    $fillingArticles,
                    $id,
                    $pluginInfos,
                    $sShippingcostsDifference,
                    $article->getSupplier()->getId()
                );
                $fillingArticles = [];
                $this->alsoBought(
                    $fillingArticlesRepository,
                    $fillingArticles,
                    $id,
                    $pluginInfos,
                    $sShippingcostsDifference
                );
                $this->accessories(
                    $fillingArticlesRepository,
                    $id,
                    $pluginInfos,
                    $sShippingcostsDifference
                );
                $this->similarArticles(
                    $fillingArticlesRepository,
                    $fillingArticles,
                    $id,
                    $pluginInfos,
                    $sShippingcostsDifference,
                    $shippingcosts
                );
            }
        }
    }

    private function alsoBought(
        FillingArticleRepository $fillingArticlesRepository,
        array $fillingArticles,
        $id,
        array $pluginInfos,
        float $sShippingcostsDifference
    ) {
        if (!$pluginInfos['customersAlsoBought'])
            return;

        $fillingArticles = $fillingArticlesRepository->getFillingArticlesFromAlsoBought(
            $fillingArticles,
            $pluginInfos,
            [$id => $id],
            $sShippingcostsDifference
        );

        if ($id == 5) {
            $this->assertCount(1,$fillingArticles,$id);
        } else {
            $this->assertCount(0,$fillingArticles,$id);
        }
    }

    private function similarArticles(
        FillingArticleRepository $fillingArticlesRepository,
        array $fillingArticles,
        $id,
        array $pluginInfos,
        float $sShippingcostsDifference,
        $shippingcosts
    ) {

        $fillingArticles = $fillingArticlesRepository->getFillingArticlesFromSimilar(
            $fillingArticles,
            $pluginInfos,
            [$id => $id],
            $sShippingcostsDifference
        );

        if (!$pluginInfos['similarArticles']) {
            $this->assertTrue(
                empty($fillingArticlesFromAccessories),
                'Zum Artikel: '
                .$id.
                ' werden ähnliche Artikel gefunden, obwohl in den Einstellungen nicht aktiv.'
            );
            return;
        }

        $idsWithSimilar = [
          5,
          7,
          11,
          12
        ];
        if ($shippingcosts == 30) {
            if (in_array($id,$idsWithSimilar)) {
                $this->assertCount(1,$fillingArticles,$id);
            } else {
                $this->assertCount(0,$fillingArticles,$id);
            }
        }
    }
}