<?php


namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Condition;


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

        /** @var SeparatelyCondition $condition */
        $data = $condition->getData();
        $categoryIDs = $data['categoryIDs'];
        $supplierIDs = $data['supplierIDs'];

        $query
            ->leftJoin(
            'product',
            's_articles_categories',
            'categories',
            'product.id = categories.articleID')
            ->leftJoin(
            'product',
            's_articles_supplier',
            'supplier',
            'product.supplierID = supplier.id')
            ->andWhere('categories.id IN (:categoryIDs) OR supplier.id IN (:supplierIDs)')
            ->setParameter('categoryIDs', $categoryIDs)
            ->setParameter('supplierIDs', $supplierIDs)
        ;
    }
}