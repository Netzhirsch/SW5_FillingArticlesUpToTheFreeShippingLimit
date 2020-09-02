<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Struct;

class FillingArticleQueryInfos
{
    /**
     * @var array
     */
    private $pluginInfos;

    /**
     * @var array
     */
    private $supplierIds;

    /**
     * @var array
     */
    private $articleIdsFromBasket;

    /**
     * @var array|null
     */
    private $excludedArticlesByIds;

    /**
     * @var array|null
     */
    private $excludedArticlesByNames;


    /**
     * php 5.6 dont know float
     */
    private $sShippingcostsDifference;

    /**
     * @var array
     */
    private $fillingArticles;

    public function __construct(
        array $pluginInfos,
        $sShippingcostsDifference,
        array $supplierIds,
        array $articleIdsFromBasket,
        array $excludedArticlesByIds,
        array $excludedArticlesByNames,
        array $fillingArticles
    ) {
        $this->pluginInfos = $pluginInfos;
        $this->sShippingcostsDifference = $sShippingcostsDifference;
        $this->supplierIds = $supplierIds;
        $this->articleIdsFromBasket = $articleIdsFromBasket;
        $this->excludedArticlesByIds = $excludedArticlesByIds;
        $this->excludedArticlesByNames = $excludedArticlesByNames;
        $this->fillingArticles = $fillingArticles;
    }

    /**
     * @return array
     */
    public function getPluginInfos()
    {
        return $this->pluginInfos;
    }

    /**
     * @return array
     */
    public function getSupplierIds()
    {
        return $this->supplierIds;
    }

    /**
     * @return array
     */
    public function getArticleIdsFromBasket()
    {
        return $this->articleIdsFromBasket;
    }

    /**
     * @return array|null
     */
    public function getExcludedArticlesByIds()
    {
        return $this->excludedArticlesByIds;
    }

    /**
     * @return array|null
     */
    public function getExcludedArticlesByNames()
    {
        return $this->excludedArticlesByNames;
    }

    /**
     * @return float
     */
    public function getSShippingcostsDifference()
    {
        return $this->sShippingcostsDifference;
    }

    /**
     * @return array
     */
    public function getFillingArticles()
    {
        return $this->fillingArticles;
    }

    /**
     * @param $fillingArticles
     * @return array
     */
    public function addFillingArticles($fillingArticles)
    {
        if (!empty($this->getFillingArticles()))
            $this->fillingArticles = array_merge($this->getFillingArticles(),$fillingArticles);
        elseif(!empty($fillingArticles))
            $this->fillingArticles = $fillingArticles;
        else
            $this->fillingArticles = [];

        return $this->fillingArticles;
    }
}
