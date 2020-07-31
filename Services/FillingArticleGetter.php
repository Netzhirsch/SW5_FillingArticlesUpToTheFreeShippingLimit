<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;

use Shopware\Models\Article\Article;

class FillingArticleGetter
{
    /**
     * @var FillingArticleRepository
     */
    private $fillingArticleRepository;

    /**
     * @var IdFromAssign
     */
    private $idFromAssign;

    public function __construct(FillingArticleRepository $fillingArticleRepository,IdFromAssign $idFromAssign)
    {
        $this->fillingArticleRepository = $fillingArticleRepository;
        $this->idFromAssign = $idFromAssign;
    }

    public function getFillingArticles($sBasket,$pluginInfos,$sShippingcostsDifference) {

        $articleIdsToExclude = $this->idFromAssign->getArticleIdsFromBasket($sBasket);
        //********* exlude articles by plugin setting *****************************************************************/
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                $articleIdsToExclude[$excludedArticle] = $excludedArticle;
            }
        }
        $fillingArticleRepository = $this->fillingArticleRepository;

        $fillingArticles = $fillingArticleRepository->getFillingArticlesFromTopseller(
            $pluginInfos,
            $articleIdsToExclude,
            $sShippingcostsDifference,
            $sBasket
        );

        if (!empty($fillingArticles))
            return $fillingArticles;

        $fillingArticles
            = $fillingArticleRepository->getFillingArticlesFromProductStreams(
            $pluginInfos,
            $articleIdsToExclude,
            $sShippingcostsDifference,
            $sBasket
        );

        if (!empty($fillingArticles))
            return $fillingArticles;

        //********* get article collection ****************************************************************************/
        $query = $fillingArticleRepository->getQuery($articleIdsToExclude,$pluginInfos,$sShippingcostsDifference,$sBasket);
        /** @var Article[] $articles */
        $articles = $query->getResult();

        //********* get the missing article data **********************************************************************/
        $fillingArticles = [];
        foreach ($articles as $article) {

            $return = $fillingArticleRepository->getOrdernumberAndFrontendArticle($article);

            if (empty($return))
                continue;

            $fillingArticles[$return['ordernumber']] = array_merge($fillingArticles,$return['articleForFrontend']);
        }

        return $fillingArticles;
    }


}