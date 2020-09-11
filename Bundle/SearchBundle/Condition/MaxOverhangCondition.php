<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class MaxOverhangCondition implements ConditionInterface
{
    const NAME = 'data';

    protected $data;

    /**
     * @param float[] $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return float[]
     */
    public function getData()
    {
        return $this->data;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}