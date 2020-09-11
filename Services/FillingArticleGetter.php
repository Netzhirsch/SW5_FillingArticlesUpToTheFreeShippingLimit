<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;

use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Struct\FillingArticleQueryInfos;

class FillingArticleGetter
{
    /**
     * @var FillingArticleSearch
     */
    private $fillingArticleRepository;

    /**
     * @var DataFromAssign
     */
    private $idFromAssign;

    public function __construct(FillingArticleSearch $fillingArticleRepository,DataFromAssign $idFromAssign)
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

        $fillingArticleQueryInfos
            = $this->createFillingArticleQueryInfos($pluginInfos,$sShippingcostsDifference,$sBasket);

        $fillingArticleQueryInfos = $this->CollectFillingArticle($fillingArticleQueryInfos);

        $fillingArticles = $this->cutFillingArticlesToMaxSize($fillingArticleQueryInfos, $pluginInfos);

        $fillingArticles
            = $this->fillingArticleRepository->getSortedFillingArticle($fillingArticles,$pluginInfos);

        return $fillingArticles;
    }

    /**
     * @param $pluginInfos
     * @param $sShippingcostsDifference
     * @param $sBasket
     * @return FillingArticleQueryInfos
     */
    private function createFillingArticleQueryInfos(
        $pluginInfos,$sShippingcostsDifference,$sBasket
    )
    {
        //********* shopware 5.2 save names in excludedArticles 5.6 ids ************************************************/
        $excludedArticlesByNames = [];
        $excludedArticlesByIds = [];
        //********* exlude articles by plugin setting *****************************************************************/
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                if (is_integer($excludedArticle)) {
                    $excludedArticlesByIds[$excludedArticle] = $excludedArticle;
                } else {
                    $excludedArticlesByNames[] = $excludedArticle;
                }
            }
        }

        return new FillingArticleQueryInfos(
            $pluginInfos,
            $sShippingcostsDifference,
            $this->idFromAssign->getSupplierIdsFromBasket($sBasket),
            $this->idFromAssign->getArticleIdsFromBasket($sBasket),
            $excludedArticlesByIds,
            $excludedArticlesByNames,
            []
        );
    }

    /**
     * @param FillingArticleQueryInfos $fillingArticleQueryInfos
     * @return FillingArticleQueryInfos
     */
    private function CollectFillingArticle(FillingArticleQueryInfos $fillingArticleQueryInfos)
    {
        $fillingArticleRepository = $this->fillingArticleRepository;

        $fillingArticleQueryInfos
            ->addFillingArticles(
                $fillingArticleRepository
                    ->getFillingArticlesFromAccessories($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles(
                $fillingArticleRepository
                    ->getFillingArticlesFromSimilar($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles(
                $fillingArticleRepository
                    ->getFillingArticlesFromTopseller($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles(
                $fillingArticleRepository
                    ->getFillingArticlesFromProductStreams($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles(
                $fillingArticleRepository
                    ->getFillingArticlesFromAlsoBought($fillingArticleQueryInfos)
            );

        $fillingArticleQueryInfos
            ->addFillingArticles(
                $fillingArticleRepository
                    ->getFillingArticlesFromCategoryManufacture($fillingArticleQueryInfos)
            );

        return $fillingArticleQueryInfos;
    }

    /**
     * @param FillingArticleQueryInfos $fillingArticleQueryInfos
     * @param $pluginInfos
     * @return array
     */
    private function cutFillingArticlesToMaxSize(
        FillingArticleQueryInfos $fillingArticleQueryInfos,
        $pluginInfos
    ) {

        return array_slice(
            $fillingArticleQueryInfos->getFillingArticles(),
            0,
            $pluginInfos['maxArticle']
        );
    }


}
