<?php


namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;


class ArticleFromAssign
{

    public function assignMissingAmountToShippingCostFreeBoarder($sBasket,$sShippingcostsDifference) {
        if (empty($sBasket['content']))
            return [];

        foreach ($sBasket['content'] as &$articleFromBasket) {
            $amount = ceil($sShippingcostsDifference / $articleFromBasket['price']);
            $articleFromBasket['MissingAmountToShippingCostFreeBoarder'] = $amount;
        }
        return $sBasket;
    }

    public function getArticleIdsFromBasket($sBasket) {
        if (empty($sBasket['content']))
            return [];

        $articleIDs = [];
        foreach ($sBasket['content'] as $articleFromBasket) {
            if (empty($articleFromBasket['articleID']))
                continue;

            $articleIDs[$articleFromBasket['articleID']] = $articleFromBasket['articleID'];
        }
        return $articleIDs;
    }

    public function getSupplierIdsFromBasket($sBasket) {
        if (empty($sBasket['content']))
            return [];

        $supplierIDs = [];
        foreach ($sBasket['content'] as $articleFromBasket) {

            if (empty($articleFromBasket['additional_details']))
                continue;

            $supplierID = $articleFromBasket['additional_details']['supplierID'];
            if (empty($supplierID))
                continue;

            $supplierIDs[$supplierID] = $supplierID;
        }
        return $supplierIDs;
    }
}