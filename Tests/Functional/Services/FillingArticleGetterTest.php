<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Services;

use Enlight_Components_Test_Controller_TestCase;
use Enlight_Exception;
use Shopware\Models\Article\Article;

class FillingArticleGetterTest extends Enlight_Components_Test_Controller_TestCase
{

    /**
     * @group filling_article_getter
     * @throws Enlight_Exception
     */
    public function testAssignMissingAmountToShippingCostFreeBoarder(){

        $fillingArticlesGetter
            = Shopware()
            ->Container()
            ->get(
                'netzhirsch_filling_articles_up_to_the_free_shipping_limit.services.filling_article_getter'
            );

        $fillingArticles = $fillingArticlesGetter->getFillingArticles([],[],10);

       $this->assertEmpty($fillingArticles);

        $qb = Shopware()->Container()->get('models')->createQueryBuilder();
        /** @var Article[] $articles */
        $articles = $qb->select('article')
            ->from(Article::class,'article')
            ->getQuery()
            ->getResult();
        $pluginInfos = FillingArticleSearchTest::getDefaultConfig();
        Shopware()->Container()->get('request_stack')->push($this->__get('request'));
        $expectedArticleAmount = [
            0 => 4,
            1 => 4,
            2 => 5,
            3 => 4,
            4 => 5,
            5 => 5,
            6 => 5,
            7 => 5,
            8 => 4,
            9 => 5,
            10 => 5,
            11 => 5,
            12 => 5,
            13 => 4,
            14 => 4,
        ];
        $this->assertFillingArticleCount($pluginInfos,$expectedArticleAmount,$articles,$fillingArticlesGetter,'default');

        $pluginInfos['topSeller'] = null;
        $expectedArticleAmount = array_fill(0,14,0);
        $this->assertFillingArticleCount($pluginInfos,$expectedArticleAmount,$articles,$fillingArticlesGetter,'kein filter');
        $pluginInfos['topSeller'] = true;

        $pluginInfos['maxArticle'] = 5;
        $expectedArticleAmount = array_fill(0,14,5);
        $expectedArticleAmount[0] = 4;
        $this->assertFillingArticleCount($pluginInfos,$expectedArticleAmount,$articles,$fillingArticlesGetter,'maxArtikel');
        $pluginInfos['maxArticle'] = 20;

    }

    /**
     * @param $pluginInfos
     * @param $expectedArticleAmount
     * @param $articles
     * @param $fillingArticlesGetter
     * @param $message
     * @throws Enlight_Exception
     */
    private function assertFillingArticleCount($pluginInfos,$expectedArticleAmount,$articles,$fillingArticlesGetter,$message)
    {
        foreach ($articles as $key => $article) {
            $basket = ['content'];
            $basket['content'][] = [
                'articleID' => $article->getId(),
                'additional_details' => [
                    'supplierID' => $article->getSupplier()->getId()
                ]
            ];
            Shopware()->Front()->setRequest($this->__get('request'));
            $fillingArticles = $fillingArticlesGetter->getFillingArticles($basket,$pluginInfos,20);
            if ($message != 'maxArtikel')
                $this->assertCount($expectedArticleAmount[$key],$fillingArticles
                    ,$key.' '.$message.$article->getId()
                );
            else
                $this->assertTrue((count($fillingArticles) <= 5 ),
                    'Für den Artikel '.$article->getId().' wurden mehr als die erwartetet 5 Füllartikel gefunden'
                );
        }
    }
}