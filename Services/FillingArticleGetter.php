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

    /**
     * @param $sBasket
     * @param $pluginInfos
     * @param $sShippingcostsDifference
     * @return array
     */
    public function getFillingArticles($sBasket,$pluginInfos,$sShippingcostsDifference) {

        if (empty($sBasket))
            return [];

        $articlesInBasketIds = $this->idFromAssign->getArticleIdsFromBasket($sBasket);
        //********* exlude articles by plugin setting *****************************************************************/
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                $articlesInBasketIds[$excludedArticle] = $excludedArticle;
            }
        }
        $fillingArticleRepository = $this->fillingArticleRepository;
        $fillingArticles = [];

        $articlesFromAccessories = $fillingArticleRepository->getFillingArticlesFromAccessories(
            $fillingArticles,
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );

        if (!empty($articlesFromAccessories))
            $fillingArticles = array_merge($fillingArticles,$articlesFromAccessories);

        $articlesFromSimilarProducts = $fillingArticleRepository->getFillingArticlesFromSimilar(
            $fillingArticles,
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );

        if (!empty($articlesFromSimilarProducts))
            $fillingArticles = array_merge($fillingArticles,$articlesFromSimilarProducts);

        $articlesFromTopSeller = $fillingArticleRepository->getFillingArticlesFromTopseller(
            $fillingArticles,
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );

        if (!empty($articlesFromTopSeller))
            $fillingArticles = array_merge($fillingArticles,$articlesFromTopSeller);

        $articlesFromProductStream = $fillingArticleRepository->getFillingArticlesFromProductStreams(
            $fillingArticles,
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );

        if (!empty($articlesFromProductStream))
            $fillingArticles = array_merge($fillingArticles,$articlesFromProductStream);

        $articlesFromAlsoBought = $fillingArticleRepository->getFillingArticlesFromAlsoBought(
            $fillingArticles,
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );

        if (!empty($articlesFromAlsoBought))
            $fillingArticles = array_merge($fillingArticles,$articlesFromAlsoBought);

        //********* get article collection ****************************************************************************/
        $articlesFromCategoryManufacture = $fillingArticleRepository->getFillingArticlesFromCategoryManufacture(
            $fillingArticles,
            $articlesInBasketIds,
            $pluginInfos,
            $sShippingcostsDifference,
            $sBasket
        );
        if (!empty($articlesFromCategoryManufacture))
            $fillingArticles
                = array_merge($fillingArticles,$articlesFromCategoryManufacture);

        $fillingArticles = array_slice($fillingArticles, 0, $pluginInfos['maxArticle']);
        //********* sorting ****************************************************************************************/
        $fillingArticles
            = $fillingArticleRepository->getSortedFillingArticle($fillingArticles,$pluginInfos);

        return $fillingArticles;
    }


}