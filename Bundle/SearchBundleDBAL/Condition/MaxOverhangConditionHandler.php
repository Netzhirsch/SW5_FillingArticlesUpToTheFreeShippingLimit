<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Condition;

use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\MaxOverhangCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\CriteriaAwareInterface;
use Shopware\Bundle\SearchBundleDBAL\ListingPriceSwitcher;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class MaxOverhangConditionHandler implements ConditionHandlerInterface, CriteriaAwareInterface
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
        return $condition instanceof MaxOverhangCondition;
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

        /** @var MaxOverhangCondition $condition */
        $data = $condition->getData();
        $sShippingcostsDifference = $data['sShippingcostsDifference'];
        $maximumOverhang = $data['maximumOverhang'];

        $key = ':overhang';

        $query->andWhere('(listing_price.cheapest_price - '.$sShippingcostsDifference.') <= '.$key);
        $query->setParameter($key, $maximumOverhang);

    }

    public function setCriteria(Criteria $criteria)
    {
        $this->criteria = $criteria;
    }
}