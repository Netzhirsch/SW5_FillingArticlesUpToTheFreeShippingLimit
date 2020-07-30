<?php


namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Condition;


use Exception;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\SeparatelyCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SeparatelyConditionHandler  implements ConditionHandlerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof SeparatelyCondition;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        $query->addState($condition->getName());

        foreach ($condition->getConditions() as $innerCondition) {
            $handler = $this->getConditionHandler($innerCondition);
            $handler->generateCondition($innerCondition, $query, $context);
        }
    }

    /**
     * @throws \Exception
     *
     * @return ConditionHandlerInterface
     */
    private function getConditionHandler(ConditionInterface $condition)
    {
        //initialize the condition handler collection service
        $this->container->get('shopware_searchdbal.dbal_query_builder_factory');

        $handlers = $this->container->get('shopware_searchdbal.condition_handlers');

        foreach ($handlers as $handler) {
            if ($handler->supportsCondition($condition)) {
                return $handler;
            }
        }

        throw new Exception(sprintf('Condition %s not supported', get_class($condition)));
    }
}