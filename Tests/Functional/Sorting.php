<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional;

use Enlight_Components_Test_Controller_TestCase;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\FillingArticleSearch;

class Sorting extends Enlight_Components_Test_Controller_TestCase
{

    public function sorting($pluginInfos,FillingArticleSearch $fillingArticlesRepository,array $fillingArticles)
    {
        $this->price($pluginInfos,$fillingArticlesRepository,$fillingArticles);
        $this->price($pluginInfos,$fillingArticlesRepository,$fillingArticles);
        $this->votes($pluginInfos,$fillingArticlesRepository,$fillingArticles);
        $this->stock($pluginInfos,$fillingArticlesRepository,$fillingArticles);
        $this->popularity($pluginInfos,$fillingArticlesRepository,$fillingArticles);

    }

    private function price($pluginInfos,FillingArticleSearch $fillingArticlesRepository,$fillingArticles)
    {
        $pluginInfos['sorting'] = 'price ascending';
        $this->foreachFillingArticlesAndTest(
            $pluginInfos,
            $fillingArticlesRepository,
            $fillingArticles,['direction' => 'ascending','type' => 'price_numeric']
        );

        $pluginInfos['sorting'] = 'price descending';
        $this->foreachFillingArticlesAndTest(
            $pluginInfos,
            $fillingArticlesRepository,
            $fillingArticles,
            ['direction' => 'descending','type' => 'price_numeric']
        );
    }

    private function votes($pluginInfos,FillingArticleSearch $fillingArticlesRepository,$fillingArticles)
    {
        $pluginInfos['sorting'] = 'votes descending';

        $this->foreachFillingArticlesLoadArticleAndTest(
            $fillingArticlesRepository,
            $fillingArticles,
            $pluginInfos,
            $fillingArticles,
            [0 => 'sVoteAverage',1 => 'average']
        );

    }

    private function stock($pluginInfos, FillingArticleSearch $fillingArticlesRepository, array $fillingArticles)
    {
        $pluginInfos['sorting'] = 'stock ascending';
        $this->foreachFillingArticlesLoadArticleAndTest(
            $fillingArticlesRepository,
            $fillingArticles,
            $pluginInfos,
            $fillingArticles,
            [0 => 'instock']
        );

        $pluginInfos['sorting'] = 'stock descending';
        $this->foreachFillingArticlesAndTest($pluginInfos,$fillingArticlesRepository,$fillingArticles,['direction' => 'descending','type' => 'stock']);
    }

    private function popularity($pluginInfos, FillingArticleSearch $fillingArticlesRepository, array $fillingArticles)
    {
        $pluginInfos['sorting'] = 'popularity ascending';
        $fillingArticlesSorted
            = $fillingArticlesRepository->getSortedFillingArticle($fillingArticles,$pluginInfos);
        if (!empty($fillingArticles)) {
            $this->assertNotEmpty($fillingArticlesSorted);
        }

        foreach ($fillingArticlesSorted as $fillingArticle) {
            $qb = Shopware()->Models()->getDBALQueryBuilder();
            $sales = $qb->select('topSeller.sales')
                ->from('s_articles','product')
                ->leftJoin(
                    'product',
                    's_articles_top_seller_ro',
                    'topSeller',
                    'topSeller.article_id = product.id'
                )
                ->where('product.id = :productId')
                ->setParameter('productId',$fillingArticle['articleID'])
                ->execute()->fetchColumn();
            ;
            if (!isset($before)) {
                $before = $sales;
            }
            if (strpos($pluginInfos['sorting'],'descending') !== false) {
                $this->assertTrue(
                    $before >= $sales
                    ,'before popularity'
                    .': '.$sales
                    .'after popularity'
                    .': '.$before
                    .' direction: descending'
                );
            } else {
                $this->assertTrue(
                    $before <= $sales
                    ,'before popularity'
                    .': '.$sales
                    .'after popularity'
                    .': '.$before
                    .' direction: descending'
                );
            }
            $before = $sales;
        }
    }

    private function foreachFillingArticlesAndTest($pluginInfos,FillingArticleSearch $fillingArticlesRepository, array $fillingArticlesUnsorted,$sortingInfo)
    {
        $sortingDirection = $sortingInfo['direction'];
        $sortingType = $sortingInfo['type'];

        $fillingArticlesSorted
            = $fillingArticlesRepository->getSortedFillingArticle($fillingArticlesUnsorted,$pluginInfos);
        if (!empty($fillingArticlesUnsorted))
            $this->assertNotEmpty($fillingArticlesSorted);
        foreach ($fillingArticlesSorted as $fillingArticle) {
            if (!isset($stock)) {
                $stock = $fillingArticle[$sortingType];
            }
            if ($sortingDirection == 'ascending') {
                $this->assertTrue(
                    $stock <= $fillingArticle[$sortingType],
                    'before '
                                .$sortingType
                                .': '.$fillingArticle[$sortingType]
                                .'after'.$sortingType.': '
                                .$stock.' direction: '
                                .$sortingDirection
                );
            }
            else {
                $this->assertTrue(
                    $stock >= $fillingArticle[$sortingType]
                    ,'before '
                                .$sortingType
                                .': '.$fillingArticle[$sortingType]
                                .'after '
                                .$sortingType
                                .': '.$stock
                                .' direction: '
                                .$sortingDirection
                );
            }
            $stock = $fillingArticle[$sortingType];
        }

    }

    /**
     * @param FillingArticleSearch $fillingArticlesRepository
     * @param $fillingArticles
     * @param $pluginInfos
     * @param $fillingArticlesUnsorted
     * @param $articleIndex
     */
    private function foreachFillingArticlesLoadArticleAndTest(
        FillingArticleSearch $fillingArticlesRepository,
        $fillingArticles,
        $pluginInfos,
        $fillingArticlesUnsorted,
        $articleIndex
    ): void {
        $fillingArticlesSorted = $fillingArticlesRepository->getSortedFillingArticle($fillingArticles, $pluginInfos);
        if (!empty($fillingArticlesUnsorted)) {
            $this->assertNotEmpty($fillingArticlesSorted);
        }

        foreach ($fillingArticlesSorted as $fillingArticle) {
            $article = Shopware()->Modules()->Articles()->sGetArticleById($fillingArticle['articleID']);

            if (!isset($before)) {
                $before = $article[$articleIndex[0]];
                if (isset($articleIndex[1])) {
                    $before = $article[$articleIndex[0]][$articleIndex[1]];
                }
            }
            $after = $article[$articleIndex[0]];
            if (isset($articleIndex[1])) {
                $after = $article[$articleIndex[0]][$articleIndex[1]];
            }
            if (strpos($pluginInfos['sorting'],'descending') !== false) {
                $this->assertTrue(
                    $before >= $after,
                    'before votes: '.$after.'after votes: '.$before.' direction: descending'
                );
            } else {
                $this->assertTrue(
                    $before <= $after,
                    'before votes: '.$after.'after votes: '.$before.' direction: ascending'
                );
            }
            $before = $after;
        }
    }

}