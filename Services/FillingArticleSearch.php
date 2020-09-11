<?php


namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services;


use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Struct\FillingArticleQueryInfos;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\CombineCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\MaxOverhangCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\NotInArticleIdsCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\NotInArticleNamesCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\SeparatelyCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Sorting\VoteSorting;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Repository\Repository;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\CombinedCondition;
use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Bundle\SearchBundle\Condition\OrdernumberCondition;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\Condition\ProductIdCondition;
use Shopware\Bundle\SearchBundle\Criteria;
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
use Shopware\Models\ProductStream\ProductStream;
use Shopware_Components_Config;

class FillingArticleSearch
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
     * @var DataFromAssign
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
        DataFromAssign $idFromAssign,
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
        FillingArticleQueryInfos $fillingArticleQueryInfos
    ){
        $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if (empty($pluginInfos['topSeller']))
            return $fillingArticles;

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $fillingArticleQueryInfos
        );

        if (empty($return))
            return $fillingArticles;

        //********* set max topseller ********************************************************************************/
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
        FillingArticleQueryInfos $fillingArticleQueryInfos
    ) {
        $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if (empty($pluginInfos['similarArticles']))
            return $fillingArticles;

        $repository = new Repository();

        $similarIds
            = $repository->getRelatedarticleId(
                $this->modelManager->getDBALQueryBuilder(),$fillingArticleQueryInfos->getArticleIdsFromBasket()
        );

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $fillingArticleQueryInfos
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
        FillingArticleQueryInfos $fillingArticleQueryInfos
    )
    {
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if (empty($pluginInfos['customersAlsoBought']))
            return $fillingArticleQueryInfos->getFillingArticles();

        $alsoBoughtArticleIdsArray = [];
        $marketing = Shopware()->Modules()->Marketing();
        foreach ($fillingArticleQueryInfos->getArticleIdsFromBasket() as $articlesInBasketId) {
            $categoryID = Shopware()->Modules()->Categories()->sGetCategoryIdByArticleId($articlesInBasketId);
            $marketing->categoryId = $categoryID ;
            $articlesFromAlsoBought = $marketing->sGetAlsoBoughtArticles($articlesInBasketId);
            foreach ($articlesFromAlsoBought as $articleFromAlsoBought) {
                $alsoBoughtArticleIdsArray[] = $articleFromAlsoBought['id'];
            }
        }

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $fillingArticleQueryInfos
        );
        if (empty($return))
            return $fillingArticleQueryInfos->getFillingArticles();

        $criteria = $return['criteria'];

        $criteria->addCondition(new ProductIdCondition($alsoBoughtArticleIdsArray));

        return $this->getProductFromVariantSearch(
            $criteria,$return['context'],$fillingArticleQueryInfos->getFillingArticles()
        );
    }

    public function getFillingArticlesFromAccessories(
        FillingArticleQueryInfos $fillingArticleQueryInfos
    )
    {
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if (empty($pluginInfos['accessories']))
            return [];


        $repository = new Repository();
        $relatedarticleIdsAssoc
            = $repository->getAccessoriesIds(
                implode(',',$fillingArticleQueryInfos->getArticleIdsFromBasket()),
                $this->modelManager->getConnection()
        );

        $relatedarticleIdsArray = [];
        foreach ($relatedarticleIdsAssoc as $relatedarticleId) {
            $relatedarticleIdsArray[] = $relatedarticleId['relatedarticle'];
        }

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria(
            $fillingArticleQueryInfos
        );
        if (empty($return))
            return $fillingArticleQueryInfos;

        $criteria = $return['criteria'];

        $criteria->addCondition(new ProductIdCondition($relatedarticleIdsArray));

        return $this->getProductFromVariantSearch(
            $criteria,$return['context'],$fillingArticleQueryInfos->getFillingArticles()
        );
    }

    /**
     * Returns the fill articles according to the product streams.
     * @param FillingArticleQueryInfos $fillingArticleQueryInfos
     * @return array $fillingArticles
     */
    public function getFillingArticlesFromProductStreams(
        FillingArticleQueryInfos $fillingArticleQueryInfos
    ){
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        if (empty($pluginInfos['productStream']))
            return $fillingArticleQueryInfos->getFillingArticles();

        $repository = new Repository();
        /** @var ProductStream[] $productStreams */
        $productStreams = $repository->getProductStream(
            $this->modelManager->createQueryBuilder(),$pluginInfos['productStream']
        );

        $fillingArticles = $fillingArticleQueryInfos->getFillingArticles();
        if (!empty($productStreams)) {

            foreach ($productStreams as $productStream) {

                //********* to reset we need to do this every product stream ******************************************/
                $return = $this->createContextAndConditionCriteria(
                    $fillingArticleQueryInfos
                );
                $criteria = $return['criteria'];

                $this->repositoryInterface->prepareCriteria($criteria, $productStream->getId());
                $variantSearch = $this->variantSearch;
                $searchQuery = $variantSearch->search($criteria,$return['context']);
                if (empty($searchQuery))
                    return $fillingArticleQueryInfos->getFillingArticles();

                $products = $searchQuery->getProducts();
                if (empty($products))
                    return $fillingArticleQueryInfos->getFillingArticles();

                // convert product model to frontend product array
                $articleFromProductStream = $this->legacyStructConverter->convertListProductStructList($products);
                if (empty($articleFromProductStream))
                    return $fillingArticleQueryInfos->getFillingArticles();

                $fillingArticles
                    = array_merge($fillingArticleQueryInfos->getFillingArticles(),$articleFromProductStream);
            }
        }

        return $fillingArticles;
    }

    public function getFillingArticlesFromCategoryManufacture(
        FillingArticleQueryInfos $fillingArticleQueryInfos
    )
    {
        if (empty(trim($pluginInfos['consider'])))
            return $fillingArticleQueryInfos->getFillingArticles();

        //********* set condition criteria ****************************************************************************/
        $return = $this->createContextAndConditionCriteria($fillingArticleQueryInfos);

        if (empty($return))
            return $fillingArticleQueryInfos->getFillingArticles();

        $criteria = $return['criteria'];

        //********* categories and suppliers condition ****************************************************************/
        $idFromAssign = $this->idFromAssign;
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        switch ($pluginInfos['consider']) {
            case 'category':
                $criteria->addCondition(new CategoryCondition(
                    $this->getCategoryIdsFromArticleIds($fillingArticleQueryInfos->getArticleIdsFromBasket()))
                );
                break;
            case 'supplier':
                $criteria->addCondition(new ManufacturerCondition($fillingArticleQueryInfos->getSupplierIds()));
                break;
            case 'categoryAndSupplier':
                $criteria->addCondition(new CombinedCondition([
                    new CategoryCondition($this->getCategoryIdsFromArticleIds(
                        $fillingArticleQueryInfos->getArticleIdsFromBasket())
                    ),
                    new ManufacturerCondition($idFromAssign->getSupplierIdsFromBasket(
                        $fillingArticleQueryInfos->getSupplierIds())
                    )
                ]));
                break;
            case 'categoryOrSupplier':
                $criteria->addCondition(new SeparatelyCondition([
                    'categoryIDs' => $this->getCategoryIdsFromArticleIds(
                        $fillingArticleQueryInfos->getArticleIdsFromBasket()
                    ),
                    'supplierIDs' => $fillingArticleQueryInfos->getSupplierIds()
                ]));
                break;
            default:
                break;
        }

        return $this->getProductFromVariantSearch(
            $criteria,$return['context'],$fillingArticleQueryInfos->getFillingArticles()
        );
    }

    public function createContextAndConditionCriteria(
        FillingArticleQueryInfos $fillingArticleQueryInfos
    ){
        //********* get default criteria ******************************************************************************/
        $contextService = $this->contextService;
        /** @var ProductContextInterface $context */
        $context = $contextService->getShopContext();

        //********* max filling article *******************************************************************************/
        $criteria = $this->criteriaFactory;
        $criteria = $criteria->createCriteria(
            Shopware()->Front()->Request(), $context
        );
        $pluginInfos = $fillingArticleQueryInfos->getPluginInfos();
        $sShippingcostsDifference = $fillingArticleQueryInfos->getSShippingcostsDifference();
        $criteria->offset(0)
            ->limit($pluginInfos['maxArticle']);


        //********* exlude article conditions shopware 5.2 use name ***************************************************/
        if (empty($articlesInBasketNames)) {
            $criteria->addBaseCondition(new NotInArticleIdsCondition(
                $fillingArticleQueryInfos->getArticleIdsFromBasket())
            );
        } else {
            $criteria->addBaseCondition(new NotInArticleNamesCondition($articlesInBasketNames));
        }
        $this->addPriceCondition($pluginInfos, $sShippingcostsDifference, $criteria);

        //********* combine condition *********************************************************************************/
        if (!$pluginInfos['isCombineAllowed'])
            $criteria->addCondition(new CombineCondition($sShippingcostsDifference));

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
        $categoriesIds = (new Repository())->getCategoriesIds($this->modelManager->createQueryBuilder(),$articleIDs);

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
                Shopware()->Front()->Request(),
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

        //********* second sorting ************************************************************************************/
        $this->addSortingToCriteria($pluginInfos, $criteria);

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

        //********* return topseller *********************************************************************************/
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

    /**
     * @param array $pluginInfos
     * @param Criteria $criteria
     */
    private function addSortingToCriteria(array $pluginInfos, Criteria $criteria): void
    {
        if (!empty($pluginInfos['sorting'])) {
            switch ($pluginInfos['sorting']) {
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
    }

    /**
     * @param array $pluginInfos
     * @param float $sShippingcostsDifference
     * @param Criteria $criteria
     */
    private function addPriceCondition(array $pluginInfos, float $sShippingcostsDifference, Criteria $criteria): void
    {
        $minimumArticlePrice = ($pluginInfos['minimumArticlePrice'] ? $pluginInfos['minimumArticlePrice'] : 0.00);
        if ($pluginInfos['minimumArticlePriceUnit'] == '%') {
            $minimumArticlePrice = $sShippingcostsDifference / 100 * $minimumArticlePrice;
        }

        $maximumArticlePrice = ($pluginInfos['maximumArticlePrice'] ? $pluginInfos['maximumArticlePrice'] : 0.00);
        if ($pluginInfos['maximumArticlePriceUnit'] == '%') {
            $maximumArticlePrice = $sShippingcostsDifference / 100 * $maximumArticlePrice;
        }

        $criteria->addCondition(new PriceCondition($minimumArticlePrice, $maximumArticlePrice));
    }
}
