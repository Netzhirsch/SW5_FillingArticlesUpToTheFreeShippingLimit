<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition;

use JsonSerializable;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class NotInArticleIdsCondition implements ConditionInterface, JsonSerializable
{
    const NAME = 'productids';

    /**
     * @var int[]
     */
    protected $productIds;

    /**
     * @param int[] $productIds
     */
    public function __construct(array $productIds)
    {
        $this->productIds = $productIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return int[]
     */
    public function getProductIds()
    {
        return $this->productIds;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}