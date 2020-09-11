<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Condition;

use Doctrine\DBAL\Connection;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\NotInArticleNamesCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class NotInArticleNamesConditionHandler implements ConditionHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof NotInArticleNamesCondition;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        /* @var NotInArticleNamesCondition $condition */
        if (empty($condition->getProductNames()))
            return;
        $key = ':productNames' . md5(json_encode($condition));

        $query->andWhere('product.name NOT IN (' . $key . ')');

        $query->setParameter(
            $key,
            $condition->getProductNames(),
            Connection::PARAM_STR_ARRAY
        );
    }
}
