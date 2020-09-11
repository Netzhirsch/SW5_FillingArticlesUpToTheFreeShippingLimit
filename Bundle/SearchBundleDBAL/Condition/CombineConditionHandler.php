<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Condition;

use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\CombineCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\CriteriaAwareInterface;
use Shopware\Bundle\SearchBundleDBAL\ListingPriceSwitcher;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class CombineConditionHandler implements ConditionHandlerInterface, CriteriaAwareInterface
{
    const LISTING_PRICE_JOINED = 'listing_price';

    /**
     * @var ListingPriceSwitcher
     */
    private $priceSwitcher;

    /**
     * @var Criteria
     */
    private $criteria;

    public function __construct(ListingPriceSwitcher $priceSwitcher)
    {
        $this->priceSwitcher = $priceSwitcher;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof CombineCondition;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        $this->priceSwitcher->joinPrice($query, $this->criteria, $context);

        $key = ':sShippingcostsDifference' . md5(json_encode($condition));

        /** @var CombineCondition $condition */
        $query->andWhere('listing_price.cheapest_price >= '.$key);
        $query->setParameter($key, $condition->getShopwareShippingcostsDifference());

    }

    public function setCriteria(Criteria $criteria)
    {
        $this->criteria = $criteria;
    }
}