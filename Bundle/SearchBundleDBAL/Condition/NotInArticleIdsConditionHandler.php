<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Condition;

use Doctrine\DBAL\Connection;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\NotInArticleIdsCondition;
use Shopware\Bundle\SearchBundle\Condition\ProductIdCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class NotInArticleIdsConditionHandler implements ConditionHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof NotInArticleIdsCondition;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        /* @var ProductIdCondition $condition */
        if (empty($condition->getProductIds()))
            return;
        $key = ':productIds' . md5(json_encode($condition));

        $query->andWhere('product.id NOT IN (' . $key . ')');

        $query->setParameter(
            $key,
            $condition->getProductIds(),
            Connection::PARAM_INT_ARRAY
        );
    }
}
