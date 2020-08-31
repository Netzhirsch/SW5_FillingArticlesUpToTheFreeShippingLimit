<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition;

use JsonSerializable;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class NotInArticleNamesCondition implements ConditionInterface, JsonSerializable
{
    const NAME = 'productNames';

    /**
     * @var string[]
     */
    protected $productNames;

    /**
     * @param string[] $productNames
     */
    public function __construct(array $productNames)
    {
        $this->productNames = $productNames;
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
    public function getProductNames()
    {
        return $this->productNames;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
