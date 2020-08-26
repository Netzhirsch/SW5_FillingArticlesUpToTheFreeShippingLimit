<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Services;

use Doctrine\ORM\NonUniqueResultException;
use Enlight_Components_Test_Controller_TestCase;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\FillingArticleRepository;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
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

        $shippingcostsArray = [20,60];

        foreach ($shippingcostsArray as $shippingcosts) {

            foreach ($articles as $article) {

                if ($article->getMainDetail()->getShippingFree()) {
                    continue;
                }

                $id = $article->getId();
                $articlePrice = $article->getMainDetail()->getPrices()[0]->getPrice();
                $articleTax = $article->getTax()->getTax();

                $sShippingcostsDifference =
                    $shippingcosts - ($articlePrice + ($articlePrice / 100 * $articleTax));

                if ($sShippingcostsDifference > 0) {
                    $this->topSeller(
                        $fillingArticlesRepository,
                        $pluginInfos,
                        $id,
                        $sShippingcostsDifference
                    );
                    $fillingArticles = [];
                    $this->productStreams(
                        $fillingArticlesRepository,
                        $fillingArticles,
                        $pluginInfos,
                        $id,
                        $sShippingcostsDifference
                    );
                    $pluginInfos['consider'] = 'category';
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
                    $pluginInfos['consider'] = 'categoryAndSupplier';
                    $this->categoryAndManufacture(
                        $fillingArticlesRepository,
                        $fillingArticles,
                        $id,
                        $pluginInfos,
                        $sShippingcostsDifference,
                        $article->getSupplier()->getId()
                    );
                    $this->accessories(
                        $fillingArticlesRepository,
                        $fillingArticles,
                        $id,
                        $pluginInfos,
                        $sShippingcostsDifference
                    );
                    $pluginInfos['accessories'] = 1;
                    $this->accessories(
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

    private function accessories(
        FillingArticleRepository $fillingArticlesRepository,
        $fillingArticles,
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

        if (!empty($fillingArticlesFromAccessories)) {
            $fillingArticles = array_merge($fillingArticles,$fillingArticlesFromAccessories);
        }

        if ($pluginInfos['accessories'] == 0) {
            $this->assertTrue(
                empty($fillingArticlesFromAccessories),
                'Zum Artikel: '
                .$id.
                ' werden keine Zugehör gefunden, obwohl in den Einstellungen nicht aktiv'
            );
        } else {
            if ($id == 1 && $sShippingcostsDifference > 0.01)
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
        $sShippingcostsDifference
    ) {
        $fillingArticles
            = $fillingArticlesRepository->getFillingArticlesFromTopSeller(
            [],
            $pluginInfos,
            [$id => $id],
            $sShippingcostsDifference
        );
        $this->assertTrue(
            !empty($fillingArticles),
            'Zum Artikel: '
            .$id.
            ' werden keine TopSeller gefunden, die die VSKFG von '.
            $sShippingcostsDifference.' erreichen.'
        );

        return $fillingArticles;
    }

    /**
     * @param FillingArticleRepository $fillingArticlesRepository
     * @param $fillingArticles
     * @param $pluginInfos
     * @param $id
     * @param $sShippingcostsDifference
     * @throws NonUniqueResultException
     */
    private function productStreams(
        FillingArticleRepository $fillingArticlesRepository,
        $fillingArticles,
        $pluginInfos,
        $id,
        $sShippingcostsDifference
    ) {
        $fillingArticles
            = $fillingArticlesRepository->getFillingArticlesFromProductStreams(
            $fillingArticles,
            $pluginInfos,
            [$id => $id],
            $sShippingcostsDifference
        );

        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        /** @var ProductContextInterface $context */
        $context = $contextService->getShopContext();
        $criteria = Shopware()->Container()->get('shopware_product_stream.criteria_factory');
        $criteria = $criteria->createCriteria(
            Shopware()->Container()->get('request_stack')->getCurrentRequest(),
            $context
        );
        $criteria->limit(20);
        Shopware()->Container()->get('shopware_product_stream.repository')->prepareCriteria($criteria, 1);
        $variantSearch = Shopware()->Container()->get('shopware_search.variant_search');
        $searchQuery = $variantSearch->search($criteria, $context);

        $products = $searchQuery->getProducts();
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
                $this->assertTrue(
                    $isInCategory,
                    'KategorieId im Warenkorb:'
                    .$categoryBasketArticle.' Kategorie im Füllartikel:'.$categoryFillingArticle
                );
                if ($pluginInfos['consider'] == 'categoryAndSupplier') {
                    $this->assertTrue(
                        $manufacture != $fillingArticle['supplier'],
                        'Hersteller im Warenkorb:'
                        .$categoryBasketArticle.' Hersteller im Füllartikel:'.$categoryFillingArticle
                    );
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
}