<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Shopware\Components\Model\QueryBuilder;
use Shopware\Models\Category\Category;
use Shopware\Models\ProductStream\ProductStream;

class Repository
{

    public function getRelatedarticleId($query,$articleIdsFromBasket)
    {
        $query->select('similar.relatedarticle')
            ->from('s_articles_similar', 'similar')
            ->innerJoin('similar', 's_articles', 'product', 'product.id = similar.articleID')
            ->innerJoin(
                'similar',
                's_articles',
                'similarArticles',
                'similarArticles.id = similar.relatedArticle'
            )
            ->innerJoin('similarArticles',
                's_articles_details',
                'similarVariant',
                'similarVariant.id = similarArticles.main_detail_id'
            )
            ->where('product.id IN (:ids)')
            ->setParameter(
                ':ids',
                $articleIdsFromBasket,
                Connection::PARAM_INT_ARRAY
            )
        ;

        /** @var ResultStatement $statement */
        $statement = $query->execute();

        return $statement->fetch();
    }

    public function getAccessoriesIds($articlesInBasketIdsString,Connection $connection)
    {
        $sql = "
        SELECT relationships.relatedarticle
        FROM
            s_articles_relationships relationships
        LEFT JOIN
            s_articles articles ON articles.id = relationships.articleID
        WHERE relationships.relatedarticle NOT IN ('$articlesInBasketIdsString')
        AND relationships.articleID IN ('$articlesInBasketIdsString')
        ";

        return $connection->fetchAll($sql);
    }

    public function getProductSteam(QueryBuilder $qb, $productStream)
    {
        return $qb
            ->select('productStream')
            ->from(ProductStream::class,'productStream')
            ->where('productStream.name IN (:productStreamsIds)')
            ->setParameter('productStreamsIds',$productStream)
            ->getQuery()
            ->getResult();
    }

    public function getCategoriesIds(QueryBuilder $qb, $articleIDs)
    {
        $qb->select('category.id')
            ->from(Category::class,'category')
            ->leftJoin('category.articles','article')
            ->where('article.id IN (:articleIDs)')
            ->setParameter('articleIDs',$articleIDs);

        return $qb->getQuery()->getResult();
    }
}