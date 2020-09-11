<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Condition;

use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\AccessoriesCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class AccessoriesConditionHandler implements ConditionHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof AccessoriesCondition;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        /** @var AccessoriesCondition $condition */
        $productIds = $condition->getProductIds();
        $key = ':productIds' . md5(json_encode($condition));
        $query
            ->innerJoin(
                'product',
                's_articles_relationships',
                'accessories',
                'product.id = accessories.articleID')
            ->andWhere('accessories.articleID IN ('.$key.')')
            ->setParameter($key, $productIds)
        ;
    }
}
