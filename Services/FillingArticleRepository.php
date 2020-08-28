<?php


namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\CombineContion;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\MaxOverhangCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\NotInArticleIdsCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\SeparatelyCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Sorting\VoteSorting;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\CombinedCondition;
use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Bundle\SearchBundle\Condition\OrdernumberCondition;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\Condition\ProductIdCondition;
use Shopware\Bundle\SearchBundle\Sorting\PopularitySorting;
use Shopware\Bundle\SearchBundle\Sorting\PriceSorting;
use Shopware\Bundle\SearchBundle\Sorting\ProductStockSorting;
use Shopware\Bundle\SearchBundle\Sorting\RandomSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\SearchBundle\VariantSearch;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Compatibility\LegacyStructConverter;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\ProductStream\CriteriaFactory;
use Shopware\Components\ProductStream\RepositoryInterface;
use Shopware\Models\Category\Category;
use Shopware\Models\ProductStream\ProductStream;
use Shopware_Components_Config;

class FillingArticleRepository
{

    /**
     * @var VariantSearch
     */
    private $variantSearch;

    /**
     * @var LegacyStructConverter $legacyStructConverter
     */
    private $legacyStructConverter;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var RepositoryInterface
     */
    private $repositoryInterface;

    /**
     * @var ArticleFromAssign
     */
    private $idFromAssign;

    /**
     * @var ContextService
     */
    private $contextService;

    /**
     * @var CriteriaFactory
     */
    private $criteriaFactory;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    public function __construct(
        VariantSearch $variantSearch,
        LegacyStructConverter $legacyStructConverter,
        ModelManager $modelManager,
        RepositoryInterface $repositoryInterface,
        ArticleFromAssign $idFromAssign,
        ContextService $contextService,
        CriteriaFactory $criteriaFactory,
        Shopware_Components_Config $config
    )
    {
        $this->variantSearch = $variantSearch;
        $this->legacyStructConverter = $legacyStructConverter;
        $this->modelManager = $modelManager;
        $this->repositoryInterface = $repositoryInterface;
        $this->idFromAssign = $idFromAssign;
        $this->contextService = $contextService;
        $this->criteriaFactory = $criteriaFactory;
        $this->config = $config;
    }

    public function getFillingArticlesFromTopSeller(
        $fillingArticles,
        $pluginInfos, 
        $articlesInBasketIds,
        $sShippingcostsDifference
    ){
        if (empty($pluginInfos['topSeller']))
            return $fillingArticles;

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );
        
        if (empty($return))
            return $fillingArticles;

        //********* set max top seller ********************************************************************************/
        $sLimitChart = $this->config['sCHARTRANGE'];
        if (empty($sLimitChart)) {
            $sLimitChart = 20;
        }
        $criteria = $return['criteria'];
        $criteria->offset(0)
            ->limit($sLimitChart);

        $criteria->setFetchCount(false);

        return $this->getProductFromVariantSearch($criteria,$return['context'],$fillingArticles);
    }


    public function getFillingArticlesFromSimilar(
        $fillingArticles,
        $pluginInfos,
        $articlesInBasketIds,
        $sShippingcostsDifference
    ) {
        if (empty($pluginInfos['similarArticles']))
            return $fillingArticles;

        $query = $this->modelManager->getDBALQueryBuilder();
        $query->select('similar.relatedarticle')
            ->from('s_articles_similar', 'similar')
            ->innerJoin('similar', 's_articles', 'product', 'product.id = similar.articleID')
            ->innerJoin('similar', 's_articles', 'similarArticles', 'similarArticles.id = similar.relatedArticle')
            ->innerJoin('similarArticles', 's_articles_details', 'similarVariant', 'similarVariant.id = similarArticles.main_detail_id')
            ->where('product.id IN (:ids)')
            ->setParameter(':ids', $articlesInBasketIds, Connection::PARAM_INT_ARRAY);

        /** @var ResultStatement $statement */
        $statement = $query->execute();

        $similarIds = $statement->fetch();
        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );
        if (empty($return))
            return $fillingArticles;

        $criteria = $return['criteria'];

        $ids = [];
        foreach ($similarIds as $similarId) {
            $ids[] = $similarId['relatedarticle'];
        }

        $criteria->addCondition(new ProductIdCondition($ids));

        return $this->getProductFromVariantSearch($criteria,$return['context'],$fillingArticles);
    }

    public function getFillingArticlesFromAlsoBought(
        $fillingArticles,
        $pluginInfos,
        $articlesInBasketIds,
        $sShippingcostsDifference
    )
    {
        if (empty($pluginInfos['customersAlsoBought']))
            return $fillingArticles;

        $alsoBoughtArticleIdsArray = [];
        $marketing = Shopware()->Modules()->Marketing();
        foreach ($articlesInBasketIds as $articlesInBasketId) {
            $categoryID = Shopware()->Modules()->Categories()->sGetCategoryIdByArticleId($articlesInBasketId);
            $marketing->categoryId = $categoryID ;
            $articlesFromAlsoBought = $marketing->sGetAlsoBoughtArticles($articlesInBasketId);
            foreach ($articlesFromAlsoBought as $articleFromAlsoBought) {
                $alsoBoughtArticleIdsArray[] = $articleFromAlsoBought['id'];
            }
        }

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );
        if (empty($return))
            return $fillingArticles;

        $criteria = $return['criteria'];

        $criteria->addCondition(new ProductIdCondition($alsoBoughtArticleIdsArray));

        return $this->getProductFromVariantSearch($criteria,$return['context'],$fillingArticles);
    }
    public function getFillingArticlesFromAccessories(
        $fillingArticles,
        $pluginInfos,
        $articlesInBasketIds,
        $sShippingcostsDifference
    )
    {
        if (empty($pluginInfos['accessories']))
            return $fillingArticles;

        $articlesInBasketIdsString = implode(',',$articlesInBasketIds);
        // default query
        $sql = "
        SELECT relationships.relatedarticle
        FROM
            s_articles_relationships relationships
        LEFT JOIN 
            s_articles articles ON articles.id = relationships.articleID
        WHERE relationships.relatedarticle NOT IN ('$articlesInBasketIdsString')
        AND relationships.articleID IN ('$articlesInBasketIdsString')
        ";

        $relatedarticleIdsAssoc = $this->modelManager->getConnection()->fetchAll($sql);
        $relatedarticleIdsArray = [];
        foreach ($relatedarticleIdsAssoc as $relatedarticleId) {
            $relatedarticleIdsArray[] = $relatedarticleId['relatedarticle'];
        }

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );
        if (empty($return))
            return $fillingArticles;

        $criteria = $return['criteria'];

        $criteria->addCondition(new ProductIdCondition($relatedarticleIdsArray));

        return $this->getProductFromVariantSearch($criteria,$return['context'],$fillingArticles);
    }

    /**
     * Returns the fill articles according to the product streams.
     * @param $fillingArticles
     * @param $pluginInfos
     * @param $articlesInBasketIds
     * @param $sShippingcostsDifference
     * @return array $fillingArticles
     */
    public function getFillingArticlesFromProductStreams(
        $fillingArticles,
        $pluginInfos,
        $articlesInBasketIds,
        $sShippingcostsDifference
    ){
        if (empty($pluginInfos['productStream']))
            return $fillingArticles;



        //********* get product stream model by name from plugin config ***************************************************/
        /** @var ProductStream[] $productSteams */
        $qb = $this->modelManager->createQueryBuilder();
        $productSteams = $qb
            ->select('productStream')
            ->from(ProductStream::class,'productStream')
            ->where('productStream.name IN (:productStreamsIds)')
            ->setParameter('productStreamsIds',$pluginInfos['productStream'])
            ->getQuery()
            ->getResult();

            //********* get filling articles from product streams *****************************************************/
        if (!empty($productSteams)) {

            $fillingArticles = [];
            foreach ($productSteams as $productSteam) {

                //********* to reset we need to do this every product stream ******************************************/
                $return = $this->createContextAndConditionCriteria(
                    $pluginInfos,$articlesInBasketIds,$sShippingcostsDifference
                );
                $criteria = $return['criteria'];

                $this->repositoryInterface->prepareCriteria($criteria, $productSteam->getId());
                $variantSearch = $this->variantSearch;
                $searchQuery = $variantSearch->search($criteria,$return['context']);
                if (empty($searchQuery))
                    return $fillingArticles;

                $products = $searchQuery->getProducts();
                if (empty($products))
                    return $fillingArticles;

                // convert product model to frontend product array
                $articleFromProductStream = $this->legacyStructConverter->convertListProductStructList($products);
                if (empty($articleFromProductStream))
                    return $fillingArticles;

                $fillingArticles = array_merge($fillingArticles,$articleFromProductStream);
            }
        }

        return $fillingArticles;
    }

    public function getFillingArticlesFromCategoryManufacture(
        $fillingArticles,
        $articlesInBasketIds,
        $pluginInfos,
        $sShippingcostsDifference,
        $sBasket
    )
    {
        if (empty(trim($pluginInfos['consider'])))
            return null;

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $pluginInfos,
            $articlesInBasketIds,
            $sShippingcostsDifference
        );

        if (empty($return))
            return $fillingArticles;

        $criteria = $return['criteria'];

        //********* categories and suppliers condition ****************************************************************/
        $idFromAssign = $this->idFromAssign;
        switch ($pluginInfos['consider']) {
            case 'category':
                $criteria->addCondition(new CategoryCondition($this->getCategoryIdsFromArticleIds($articlesInBasketIds)));
                break;
            case 'supplier':
                $criteria->addCondition(new ManufacturerCondition($idFromAssign->getSupplierIdsFromBasket($sBasket)));
                break;
            case 'categoryAndSupplier':
                $criteria->addCondition(new CombinedCondition([
                    new CategoryCondition($this->getCategoryIdsFromArticleIds($articlesInBasketIds)),
                    new ManufacturerCondition($idFromAssign->getSupplierIdsFromBasket($sBasket))
                ]));
                break;
            case 'categoryOrSupplier':
                $criteria->addCondition(new SeparatelyCondition([
                    'categoryIDs' => $this->getCategoryIdsFromArticleIds($articlesInBasketIds),
                    'supplierIDs' => $idFromAssign->getSupplierIdsFromBasket($sBasket)
                ]));
                break;
            default:
                break;
        }

        return $this->getProductFromVariantSearch($criteria,$return['context'],$fillingArticles);
    }

    public function createContextAndConditionCriteria(
        $pluginInfos,
        $articlesInBasketIds,
        $sShippingcostsDifference
    ){
        //********* get default criteria ******************************************************************************/
        $contextService = $this->contextService;
        /** @var ProductContextInterface $context */
        $context = $contextService->getShopContext();

        //********* max filling article *******************************************************************************/
        $criteria = $this->criteriaFactory;
        $criteria = $criteria->createCriteria(
            Shopware()->Container()->get('request_stack')->getCurrentRequest(), $context
        );
        $criteria->offset(0)
            ->limit($pluginInfos['maxArticle']);


        //********* exlude article conditions ************************************************************************/
        $criteria->addBaseCondition(new NotInArticleIdsCondition($articlesInBasketIds));

        //********* price condition ***********************************************************************************/
        $minimumArticlePrice = ($pluginInfos['minimumArticlePrice'] ? $pluginInfos['minimumArticlePrice'] : 0.00);
        if ($pluginInfos['minimumArticlePriceUnit'] == '%')
            $minimumArticlePrice = $sShippingcostsDifference / 100 * $minimumArticlePrice;

        $maximumArticlePrice = ($pluginInfos['maximumArticlePrice'] ? $pluginInfos['maximumArticlePrice'] : 0.00);
        if ($pluginInfos['maximumArticlePriceUnit'] == '%')
            $maximumArticlePrice = $sShippingcostsDifference / 100 * $maximumArticlePrice;

        $criteria->addCondition(new PriceCondition($minimumArticlePrice, $maximumArticlePrice));

        //********* combine condition *********************************************************************************/
        if (!$pluginInfos['isCombineAllowed'])
            $criteria->addCondition(new CombineContion($sShippingcostsDifference));

        //********* maximun overhang condition ************************************************************************/
        if (!empty($pluginInfos['maximumOverhang'])) {
            $criteria->addCondition(new MaxOverhangCondition([
                'sShippingcostsDifference' => $sShippingcostsDifference,
                'maximumOverhang' => $pluginInfos['maximumOverhang']
            ]));
        }

        return [
            'criteria' => $criteria,
            'context' => $context
        ];
    }

    public function getCategoryIdsFromArticleIds($articleIDs)
    {
        $qb = $this->modelManager->createQueryBuilder();
        $qb->select('category.id')
            ->from(Category::class,'category')
            ->leftJoin('category.articles','article')
            ->where('article.id IN (:articleIDs)')
            ->setParameter('articleIDs',$articleIDs);

        $categoriesIds = $qb->getQuery()->getResult();
        $categoriesIdsWithoutAssoc = [];
        foreach ($categoriesIds as $categoriesId) {
            $categoriesIdsWithoutAssoc[] = $categoriesId['id'];
        }
        return $categoriesIdsWithoutAssoc;
    }

    /**
     * Goes through all filler articles and performs a sorted query.
     * @var array $pluginInfos
     * @var array $fillingArticles
     * @return array
     */
    public function getSortedFillingArticle(array $fillingArticles, array $pluginInfos)
    {
        //********* prepare search ************************************************************************************/
        $contextService = $this->contextService;
        /** @var ProductContextInterface $context */
        $context = $contextService->getShopContext();
        $criteria = $this->criteriaFactory;
        $criteria
            = $criteria->createCriteria(
                Shopware()->Container()->get('request_stack')->getCurrentRequest(),
                $context
        );
        $criteria->offset(0);

        //********* get ordernumber array for the search **************************************************************/
        $ordernumbers = [];
        foreach ($fillingArticles as $fillingArticle) {
            $ordernumbers[] = $fillingArticle['ordernumber'];
        }
        //********* ordernumber condition from ordernumber array create before ****************************************/
        $criteria->addCondition(new OrdernumberCondition($ordernumbers));

        //********* sort first by top seller **************************************************************************/
        if ($pluginInfos['topSeller']) {
            $criteria->addSorting(new PopularitySorting(SortingInterface::SORT_DESC));
        }

        //********* second sorting ************************************************************************************/
        if (!empty($pluginInfos['sorting'])) {
            switch($pluginInfos['sorting']) {
                case 'randomly':
                    $criteria->addSorting(new RandomSorting(SortingInterface::SORT_DESC));
                    break;
                case 'price ascending':
                    $criteria->addSorting(new PriceSorting(SortingInterface::SORT_ASC));
                    break;
                case 'price descending':
                    $criteria->addSorting(new PriceSorting(SortingInterface::SORT_DESC));
                    break;
                case 'votes descending':
                    $criteria->addSorting(new VoteSorting(SortingInterface::SORT_DESC));
                    break;
                case 'stock ascending':
                    $criteria->addSorting(new ProductStockSorting(SortingInterface::SORT_ASC));
                    break;
                case 'stock descending':
                    $criteria->addSorting(new ProductStockSorting(SortingInterface::SORT_DESC));
                    break;
                case 'popularity ascending':
                    $criteria->addSorting(new PopularitySorting(SortingInterface::SORT_ASC));
                    break;
                case 'popularity descending':
                    $criteria->addSorting(new PopularitySorting(SortingInterface::SORT_DESC));
                    break;
            }
        }

        //********* find produt models ********************************************************************************/
        $variantSearch = $this->variantSearch;
        $searchQuery = $variantSearch->search($criteria,$context);

        if (empty($searchQuery))
            return $fillingArticles;

        $fillingArticleSorted = $searchQuery->getProducts();
        if (empty($fillingArticleSorted))
            return $fillingArticles;

        // convert product model to frontend product array
        $fillingArticleSorted = $this->legacyStructConverter->convertListProductStructList($fillingArticleSorted);
        if (empty($fillingArticleSorted))
            return $fillingArticles;
        else
            return $fillingArticleSorted;

    }

    private function getProductFromVariantSearch($criteria,$context,$fillingArticles) {

        //********* return top seller *********************************************************************************/
        $variantSearch = $this->variantSearch;
        $searchQuery = $variantSearch->search($criteria,$context);

        if (empty($searchQuery))
            return $fillingArticles;

        $products = $searchQuery->getProducts();
        if (empty($products))
            return $fillingArticles;

        // convert product model to frontend product array
        $productsStructs = $this->legacyStructConverter->convertListProductStructList($products);

        if (empty($productsStructs))
            return $fillingArticles;

        return $productsStructs;
    }
}