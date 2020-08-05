<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;

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

        $articlesInBasketIds = $this->idFromAssign->getArticleIdsFromBasket($sBasket);
        //********* exlude articles by plugin setting *****************************************************************/
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                $articlesInBasketIds[$excludedArticle] = $excludedArticle;
            }
        }
        $fillingArticleRepository = $this->fillingArticleRepository;
        $fillingArticles = [];

        $fillingArticles = array_merge($fillingArticles,$fillingArticleRepository->getFillingArticlesFromTopseller(
            $fillingArticles,
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference,
            $sBasket
        ));

        $fillingArticles
            = array_merge($fillingArticles,$fillingArticleRepository->getFillingArticlesFromProductStreams(
            $fillingArticles,
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference,
            $sBasket
        ));


        //********* get article collection ****************************************************************************/
        $fillingArticles
            = array_merge($fillingArticles,$fillingArticleRepository->getQueryForCategoryManufacture(
                $fillingArticles,
                $articlesInBasketIds,
                $pluginInfos,
                $sShippingcostsDifference,
                $sBasket
        ));

        //********* sorting ****************************************************************************************/
        $fillingArticles
            = $fillingArticleRepository->getSortedFillingArticle($fillingArticles,$pluginInfos);

        return $fillingArticles;
    }


}