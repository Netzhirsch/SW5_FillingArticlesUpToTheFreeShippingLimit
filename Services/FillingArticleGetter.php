<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;

use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Models\FillingArticleQueryInfos;

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

        //********* shopware 5.2 save names in excludedArticles *******************************************************/
        $excludedArticlesByNames = [];
        $excludedArticlesByIds = [];
        //********* exlude articles by plugin setting *****************************************************************/
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                if (is_integer($excludedArticle)) {
                    $excludedArticlesByIds[$excludedArticle] = $excludedArticle;
                } else {
                    $excludedArticlesByNames = $excludedArticle;
                }
            }
        }

        $fillingArticleRepository = $this->fillingArticleRepository;

        $fillingArticleQueryInfos = new FillingArticleQueryInfos(
            $pluginInfos,
            $sShippingcostsDifference,
            $this->idFromAssign->getSupplierIdsFromBasket($sBasket),
            $this->idFromAssign->getArticleIdsFromBasket($sBasket),
            $excludedArticlesByIds,
            $excludedArticlesByNames,
            []
        );

        $fillingArticleQueryInfos
            ->addFillingArticles($fillingArticleRepository
                ->getFillingArticlesFromAccessories($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles($fillingArticleRepository
                ->getFillingArticlesFromSimilar($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles($fillingArticleRepository
                ->getFillingArticlesFromTopseller($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles($fillingArticleRepository
                ->getFillingArticlesFromProductStreams($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles($fillingArticleRepository
                ->getFillingArticlesFromAlsoBought($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles($fillingArticleRepository
                ->getFillingArticlesFromCategoryManufacture($fillingArticleQueryInfos)
            );

        $fillingArticles = array_slice($fillingArticleQueryInfos->getFillingArticles(), 0, $pluginInfos['maxArticle']);
        //********* sorting ****************************************************************************************/
        $fillingArticles
            = $fillingArticleRepository->getSortedFillingArticle($fillingArticles,$pluginInfos);

        return $fillingArticles;
    }


}
