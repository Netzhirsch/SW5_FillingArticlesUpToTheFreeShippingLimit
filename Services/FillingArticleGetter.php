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
     * @var ArticleFromAssign
     */
    private $idFromAssign;

    public function __construct(FillingArticleRepository $fillingArticleRepository,ArticleFromAssign $idFromAssign)
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
        $fillingArticles = [];

        $fillingArticles = array_merge($fillingArticles,$fillingArticleRepository->getFillingArticlesFromTopseller(
            $fillingArticles,
            $pluginInfos,
            $articleIdsToExclude,
            $sShippingcostsDifference,
            $sBasket
        ));

        $fillingArticles
            = array_merge($fillingArticles,$fillingArticleRepository->getFillingArticlesFromProductStreams(
            $fillingArticles,
            $pluginInfos,
            $articleIdsToExclude,
            $sShippingcostsDifference,
            $sBasket
        ));


        //********* get article collection ****************************************************************************/
        $query
            = $fillingArticleRepository->getQueryForCategoryManufacture($articleIdsToExclude,$pluginInfos,$sShippingcostsDifference,$sBasket);
        /** @var Article[] $articles */
        if (!empty($query))
            $articles = $query->getResult();

        //********* get the missing article data **********************************************************************/
        $fillingArticlesWithMissingArticleData = [];
        foreach ($articles as $article) {

            $return = $fillingArticleRepository->getOrdernumberAndFrontendArticle($article);

            if (empty($return))
                continue;
            $array = [
                $return['ordernumber'] => $return['articleForFrontend']
            ];
            $fillingArticles = array_merge($fillingArticles,$array);
        }
        $fillingArticles = array_merge($fillingArticles,$fillingArticlesWithMissingArticleData);

        //********* sorting ****************************************************************************************/
        $fillingArticles
            = $fillingArticleRepository->getSortedFillingArticle($fillingArticles,$pluginInfos);

        return $fillingArticles;
    }


}