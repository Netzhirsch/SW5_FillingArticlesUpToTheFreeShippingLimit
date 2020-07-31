<?php
namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundleDBAL\Sorting;

use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Sorting\VoteSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\SearchBundleDBAL\SortingHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class VoteSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(SortingInterface $sorting)
    {
        return ($sorting instanceof VoteSorting);
    }

    public function generateSorting(
        SortingInterface $sorting,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        $query->leftJoin(
            'product',
            's_articles_vote',
            'votes',
            'product.id = votes.articleID'
        );

        $query->addOrderBy('SUM(votes.points)/COUNT(votes.articleID)', 'DESC')
            ->addOrderBy('COUNT(votes.articleID)', 'DESC');
    }
}