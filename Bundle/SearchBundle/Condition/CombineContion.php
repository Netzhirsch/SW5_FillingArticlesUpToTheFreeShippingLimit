<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class CombineContion implements ConditionInterface
{
    private const NAME = 'sShippingcostsDifference';

    protected $sShippingcostsDifference;

    /**
     * @param $sShippingcostsDifference
     */
    public function __construct($sShippingcostsDifference)
    {
        $this->sShippingcostsDifference = $sShippingcostsDifference;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return int
     */
    public function getShopwareShippingcostsDifference()
    {
        return $this->sShippingcostsDifference;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}