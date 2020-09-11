<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition;

use JsonSerializable;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class AccessoriesCondition implements ConditionInterface, JsonSerializable
{
    const NAME = 'cross_selling';

    /**
     * @var string
     */
    protected $productName;

    /**
     * @var array
     */
    protected $productIds;

    /**
     * @param array $productIds
     * @param string $productName
     */
    public function __construct($productIds, $productName)
    {
        $this->productIds = $productIds;
        $this->productName = $productName;
    }

    /**
     * @return array
     */
    public function getProductIds()
    {
        return $this->productIds;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return string
     */
    public function getProductName()
    {
        return $this->productName;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
