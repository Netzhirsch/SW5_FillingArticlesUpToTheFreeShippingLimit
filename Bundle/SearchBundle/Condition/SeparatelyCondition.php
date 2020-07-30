<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition;

use JsonSerializable;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class SeparatelyCondition implements ConditionInterface, JsonSerializable
{
    /**
     * @var ConditionInterface[]
     */
    protected $conditions;

    /**
     * @param ConditionInterface[] $conditions
     */
    public function __construct(array $conditions)
    {
        $this->conditions = $conditions;
    }

    /**
     * @return string
     */
    public function getName()
    {
        $classes = array_map(function ($class) {
            return get_class($class);
        }, $this->conditions);

        return 'combined_facet_' . md5(json_encode($this->conditions) . json_encode($classes));
    }

    /**
     * @return ConditionInterface[]
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
