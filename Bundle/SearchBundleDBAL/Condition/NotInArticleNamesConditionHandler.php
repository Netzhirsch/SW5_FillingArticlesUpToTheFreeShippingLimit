<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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
