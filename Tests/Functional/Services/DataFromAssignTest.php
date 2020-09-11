<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Services;

use Enlight_Components_Test_Controller_TestCase;
use Enlight_Exception;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\DataFromAssign;
use Shopware\Models\Article\Article;

class DataFromAssignTest extends Enlight_Components_Test_Controller_TestCase
{

    /**
     * @group article_from_assign
     */
    public function testAssignMissingAmountToShippingCostFreeBoarder(){
       $articleFromAssign = new DataFromAssign();

       $return = $articleFromAssign->assignMissingAmountToShippingCostFreeBoarder([],10);
       $this->assertEmpty($return);

        $qb = Shopware()->Container()->get('models')->createQueryBuilder();
        /** @var Article[] $articles */
        $articles = $qb->select('article')
            ->from(Article::class,'article')
            ->getQuery()
            ->getResult();
        $basket = [];
        foreach ($articles as $article) {
            foreach ($article->getMainDetail()->getPrices() as $price) {
            $basket = [
                'content' => [
                    $article->getId() => ['price' => $price->getPrice()]
            ]];
            }
        }
        foreach ($basket['content'] as $key => $article) {
            $return = $articleFromAssign->assignMissingAmountToShippingCostFreeBoarder($basket,$article['price']);
            $this->assertEquals(1.0, $return['content'][$key]['missingAmountToShippingCostFreeBoarder'],$key);
        }
    }

    /**
     * @group article_from_assign
     */
    public function testGetArticleIdsFromBasket()
    {
        $articleFromAssign = new DataFromAssign();

        $articleIDs = $articleFromAssign->getArticleIdsFromBasket([]);
        $this->assertEmpty($articleIDs);

        $qb = Shopware()->Container()->get('models')->createQueryBuilder();
        /** @var Article[] $articles */
        $articles = $qb->select('article')
            ->from(Article::class,'article')
            ->getQuery()
            ->getResult();
        $articleIDs = ['content'];
        foreach ($articles as $key => $article) {
            $articleIDs['content'][] = [
                'articleID' => $article->getId()
            ];
        }
        $articleIDs = $articleFromAssign->getArticleIdsFromBasket($articleIDs);
        foreach ($articles as $key => $article) {
            $this->assertTrue(in_array($article->getId(),$articleIDs),$article->getId());
        }
    }

    /**
     * @group article_from_assign
     * @throws Enlight_Exception
     */
    public function testGetSupplierIdsFromBasket()
    {

        $articleFromAssign = new DataFromAssign();
        $supplierIdsFromBasket = $articleFromAssign->getSupplierIdsFromBasket([]);
        $this->assertEmpty($supplierIdsFromBasket);

        $qb = Shopware()->Container()->get('models')->createQueryBuilder();
        /** @var Article[] $articles */
        $articles = $qb->select('article')
            ->from(Article::class,'article')
            ->getQuery()
            ->getResult();
        $supplierIdsFromBasket = ['content'];
        foreach ($articles as $key => $article) {
            $supplierIdsFromBasket['content'][] = [
                'additional_details' => [
                    'supplierID' => $article->getSupplier()->getId()
                ]
            ];
        }
        $supplierIdsFromBasket = $articleFromAssign->getSupplierIdsFromBasket($supplierIdsFromBasket);
        foreach ($articles as $key => $article) {
            $this->assertTrue(in_array($article->getSupplier()->getId(),$supplierIdsFromBasket),$article->getId());
        }
    }
}