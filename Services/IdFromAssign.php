<?php


namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;


class IdFromAssign
{
    public function getArticleIdsFromBasket($basket) {
        if (empty($basket['content']))
            return [];

        $articleIDs = [];
        foreach ($basket['content'] as $articleFromBasket) {
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