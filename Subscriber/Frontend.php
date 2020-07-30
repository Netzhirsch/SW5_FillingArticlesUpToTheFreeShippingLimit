<?php
namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Exception;
use Enlight_View_Default;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\CombineContion;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\MaxOverhangCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\NotInArticleIdsCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Bundle\SearchBundle\Condition\SeparatelyCondition;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Sorting\VoteSorting;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\CombinedCondition;
use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\Condition\SimilarProductCondition;
use Shopware\Bundle\SearchBundle\Sorting\PopularitySorting;
use Shopware\Bundle\SearchBundle\Sorting\PriceSorting;
use Shopware\Bundle\SearchBundle\Sorting\ProductStockSorting;
use Shopware\Bundle\SearchBundle\Sorting\RandomSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\SearchBundle\StoreFrontCriteriaFactory;
use Shopware\Bundle\SearchBundle\VariantSearch;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactory;
use Shopware\Bundle\SearchBundleDBAL\SortingHandler\PriceSortingHandler;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Compatibility\LegacyStructConverter;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Theme\LessDefinition;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Category\Category;
use Shopware\Models\ProductStream\ProductStream;
use Shopware\Components\ProductStream\RepositoryInterface;

// SCFB = Shipping cost free boarder

class Frontend implements SubscriberInterface
{
    private $pluginName;

    /**
     * @var ConfigReader
     */
    private $config;

    /**
     * @var string pluginDirectory
     */
    private $pluginDirectory;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var ContextService
     */
    private $contextService;

    /**
     * @var RepositoryInterface
     */
    private $streamRepository;

    /**
     * @var StoreFrontCriteriaFactory
     */
    private $criteriaFactory;

    /**
     * @var  VariantSearch $variantSearch
     */
    private $variantSearch;

    /**
     * @var LegacyStructConverter $legacyStructConverter
     */
    private $legacyStructConverter;

    /**
     * @var PriceSortingHandler $priceSortingHandler
     */
    private $priceSortingHandler;

    private $queryBuilder;

    public function __construct(
        $pluginName,
        ConfigReader $config,
        $pluginDirectory,
        ModelManager $modelManager,
        ContextService $contextService,
        StoreFrontCriteriaFactory $criteriaFactory,
        RepositoryInterface $streamRepository,
        VariantSearch $variantSearch,
        LegacyStructConverter $legacyStructConverter,
        PriceSortingHandler $priceSortingHandler,
        QueryBuilderFactory $queryBuilder
    )
    {
        $this->pluginName = $pluginName;
        $this->config = $config;
        $this->pluginDirectory = $pluginDirectory;
        $this->modelManager = $modelManager;
        $this->contextService = $contextService;
        $this->criteriaFactory = $criteriaFactory;
        $this->streamRepository = $streamRepository;
        $this->variantSearch = $variantSearch;
        $this->legacyStructConverter = $legacyStructConverter;
        $this->priceSortingHandler = $priceSortingHandler;
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Theme_Compiler_Collect_Plugin_Less' => 'addLessFile',
            'Theme_Compiler_Collect_Plugin_Javascript' => 'addJsFile',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'addTemplate',
        ];
    }

    public function addLessFile()
    {
        $less = new LessDefinition(array(),
            array(
                $this->pluginDirectory . '/Resources/views/frontend/_public/src/less/netzhirschFillingArticlesUpToTheFreeShippingLimit.less'
            ), __DIR__);

        return new ArrayCollection(array(
            $less
        ));
    }

    public function addJsFile()
    {
        $jsFiles = array($this->pluginDirectory . '/Resources/views/frontend/_public/src/js/netzhirschFillingArticlesUpToTheFreeShippingLimit.js');
        return new ArrayCollection($jsFiles);
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @throws Enlight_Exception
     */
    public function addTemplate(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_View_Default $view */
        $view = $args->get('subject')->View();

        // no filling articles if basket price is ofer SCFB
        $assign = $view->getAssign();
        if (empty($assign['sShippingcostsDifference']))
            return;

        $pluginInfos = $this->config->getByPluginName($this->pluginName);

        $notAboveBasket = false;
        // show hint to the filling articles
        if (!empty($pluginInfos['notAboveBasket']))
            $notAboveBasket = true;

        $view->assign(['notAboveBasket' => $notAboveBasket]);

        $fillingArticles
            = $this->getFillingArticles($assign['sBasket'],$pluginInfos,$assign['sShippingcostsDifference']);

        if (!empty($fillingArticles)) {
            $view->assign(['displayVariants' => $pluginInfos['displayVariants']]);
            $view->assign(['fillingArticles' => $fillingArticles]);
            $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
        }
    }

    private function getFillingArticles($sBasket,$pluginInfos,$sShippingcostsDifference) {

        $articleIdsToExclude = $this->getArticleIdsFromBasket($sBasket);
        //********* exlude articles by plugin setting *****************************************************************/
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                $articleIdsToExclude[$excludedArticle] = $excludedArticle;
            }
        }

        $fillingArticles = $this->getFillingArticlesFromTopseller(
            $pluginInfos,
            $articleIdsToExclude,
            $sShippingcostsDifference,
            $sBasket
        );

        if (!empty($fillingArticles))
            return $fillingArticles;

        $fillingArticles
            = $this->getFillingArticlesFromProductStreams(
                $pluginInfos,
                $articleIdsToExclude,
                $sShippingcostsDifference,
                $sBasket
        );

        if (!empty($fillingArticles))
            return $fillingArticles;

        //********* get article collection ****************************************************************************/
        $query = $this->getQuery($articleIdsToExclude,$pluginInfos,$sShippingcostsDifference,$sBasket);
        /** @var Article[] $articles */
        $articles = $query->getResult();

        //TODO*luhmann delete Debug
        file_put_contents(
            '/var/www/html/shopware/custom/plugins/NetzhirschFillingArticlesUpToTheFreeShippingLimit/tmp.sql',
            $query->getSQL()
        );

        //********* get the missing article data **********************************************************************/
        $fillingArticles = [];
        foreach ($articles as $article) {

           $return = $this->getOrdernumberAndFrontendArticle($article);

            if (empty($return))
                continue;

            $fillingArticles[$return['ordernumber']] = array_merge($fillingArticles,$return['articleForFrontend']);
        }

        return $fillingArticles;
    }


    /**
     * Returns the fill articles according to the product streams.
     * @param array $pluginInfos
     * @param $articlesInBasketIds
     * @param $sShippingcostsDifference
     * @param $sBasket
     * @return array $fillingArticles
     */
    private function getFillingArticlesFromProductStreams($pluginInfos,$articlesInBasketIds,$sShippingcostsDifference,$sBasket)
    {
        $fillingArticles = [];

        if (!empty($pluginInfos['productStream'])) {
            $return = $this->createContextAndCriteria($pluginInfos,$articlesInBasketIds,$sShippingcostsDifference,$sBasket);
            $criteria = $return['criteria'];
            $context = $return['context'];

            //********* get product stream model by name from plugin config *******************************************/
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

                        $this->streamRepository->prepareCriteria($criteria, $productSteam->getId());
                        $variantSearch = $this->variantSearch;
                        $searchQuery = $variantSearch->search($criteria,$context);
                        if (empty($searchQuery))
                            continue;

                        $products = $searchQuery->getProducts();
                        if (empty($products))
                            continue;

                        $articleFromProductStream = $this->legacyStructConverter->convertListProductStructList($products);
                        if (empty($articleFromProductStream))
                            continue;

                        $fillingArticles = array_merge($fillingArticles,$articleFromProductStream);
                    }
                }
        }

        return $fillingArticles;
    }

    private function getFillingArticlesFromTopSeller($pluginInfos, $articlesInBasketIds,$sShippingcostsDifference,$sBasket) {
        if (empty($pluginInfos['topSeller']))
            return [];

        $return = $this->createContextAndCriteria($pluginInfos,$articlesInBasketIds,$sShippingcostsDifference,$sBasket);
        if (empty($return))
            return [];

        $criteria = $return['criteria'];
        $context = $return['context'];

        $criteria->addSorting(new PopularitySorting(SortingInterface::SORT_DESC));

        $criteria->setFetchCount(false);

        $variantSearch = $this->variantSearch;
        $searchQuery = $variantSearch->search($criteria,$context);

        if (empty($searchQuery))
            return [];

        $products = $searchQuery->getProducts();
        if (empty($products))
            return [];

        $topSeller = $this->legacyStructConverter->convertListProductStructList($products);

        if (empty($topSeller))
            return [];

        return $topSeller;
    }

    private function createContextAndCriteria($pluginInfos,$articlesInBasketIds,$sShippingcostsDifference,$sBasket)
    {
        //********* get default criteria **************************************************************************/
        $contextService = $this->contextService;
        /** @var ProductContextInterface $context */
        $context = $contextService->getShopContext();

        $shop = $context->getShop();
        if (empty($shop))
            return [];

        $category = $shop->getCategory();
        if (empty($category))
            return [];

        //********* max filling article ****************************************************************************/
        $categoryId = $category->getId();
        $criteria = $this->criteriaFactory->createBaseCriteria([$categoryId], $context);
        $criteria->offset(0)
            ->limit($pluginInfos['maxArticle']);


        //********* filling article conditions ************************************************************************/
        $criteria->addBaseCondition(new NotInArticleIdsCondition($articlesInBasketIds));

        $minimumArticlePrice = ($pluginInfos['minimumArticlePrice'] ? $pluginInfos['minimumArticlePrice'] : 0.00);

        if ($pluginInfos['minimumArticlePriceUnit'] == '%') {
            $minimumArticlePrice = $sShippingcostsDifference / 100 * $minimumArticlePrice;
        }

        $maximumArticlePrice = ($pluginInfos['maximumArticlePrice'] ? $pluginInfos['maximumArticlePrice'] : 0.00);
        if ($pluginInfos['maximumArticlePriceUnit'] == '%') {
            $maximumArticlePrice = $sShippingcostsDifference / 100 * $maximumArticlePrice;
        }

        $criteria->addCondition(new PriceCondition($minimumArticlePrice, $maximumArticlePrice));

        if (!$pluginInfos['isCombineAllowed'])
            $criteria->addCondition(new CombineContion($sShippingcostsDifference));

        // maximun overhang
        if (!empty($pluginInfos['maximumOverhang'])) {
            $criteria->addCondition(new MaxOverhangCondition([
                'sShippingcostsDifference' => $sShippingcostsDifference,
                'maximumOverhang' => $pluginInfos['maximumOverhang']
            ]));
        }

        // categories and suppliers
        switch ($pluginInfos['consider']) {
            case 'category':
                $criteria->addCondition(new CategoryCondition($this->getCategoryIdsFromArticleIds($articlesInBasketIds)));
                break;
            case 'supplier':
                $criteria->addCondition(new ManufacturerCondition($this->getSupplierIdsFromBasket($sBasket)));
                break;
            case 'categoryAndSupplier':
                $criteria->addCondition(new CombinedCondition([
                    new CategoryCondition($this->getCategoryIdsFromArticleIds($articlesInBasketIds)),
                    new ManufacturerCondition($this->getSupplierIdsFromBasket($sBasket))
                ]));
                break;
            case 'categoryOrSupplier':
                //TODO*luhmann geht so nicht
                $criteria->addCondition(new SeparatelyCondition([
                    new CategoryCondition($this->getCategoryIdsFromArticleIds($articlesInBasketIds)),
                    new ManufacturerCondition($this->getSupplierIdsFromBasket($sBasket))
                ]));

                break;
            default:
                break;
        }

        if (!empty($pluginInfos['similarArticles'])) {
            foreach ($articlesInBasketIds as $articlesInBasketId) {
                $criteria->addCondition(new SimilarProductCondition($articlesInBasketId,null));
            }
        }

        //********* filling article sorting ***********************************************************************/
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
            }
        }

        return [
            'criteria' => $criteria,
            'context' => $context
        ];
    }

    private function getArticleIdsFromBasket($basket) {
        if (empty($basket['content']))
            return [];

        $articleIDs = [];
        foreach ($basket['content'] as $articleFromBasket) {
            if (empty($articleFromBasket['articleID']))
                continue;

            $articleIDs[$articleFromBasket['articleID']] = $articleFromBasket['articleID'];
        }
        return $articleIDs;
    }

    private function getOrdernumberAndFrontendArticle(Article $article)
    {
        /** @var Detail $details */
        $detail = $article->getMainDetail();
        $ordernumber = $detail->getNumber();

        if (empty($ordernumber))
            return null;

        return [
            'ordernumber' => $ordernumber,
            'articleForFrontend' => Shopware()
                ->Modules()
                ->Articles()
                ->sGetArticleById($article->getId(),$ordernumber)
        ];

    }

    private function getQuery(
        $articleIds,
        $pluginInfos,
        $sShippingcostsDifference,
        $sBasket
    )
    {
        // default query
        $qb = $this->modelManager->createQueryBuilder();
        $qb->select('article')
            ->addSelect('detail')
            ->from(Article::class,'article')
            ->leftJoin('article.mainDetail','detail')
            ->where('article.id NOT IN (:articleIDs)')
            ->setParameter('articleIDs',$articleIds);

        // article combinations forbidden, minimum article price, maximum article price, maximum overhang
        if (
            !$pluginInfos['isCombineAllowed']
            || !empty($pluginInfos['minimumArticlePrice'])
            || !empty($pluginInfos['maximumArticlePrice'])
            || !empty($pluginInfos['maximumOverhang'])
            || $pluginInfos['sorting'] == 'price ascending'
            || $pluginInfos['sorting'] == 'price descending'
        ) {
            $qb->addSelect('prices')
                ->leftJoin('detail.prices','prices');
            // article combinations forbidden
            if (!$pluginInfos['isCombineAllowed']) {
                $qb->andWhere('prices.price >= :sShippingcostsDifference')
                    ->setParameter('sShippingcostsDifference',$sShippingcostsDifference);
            }
            // minimum article price
            if (!empty($pluginInfos['minimumArticlePrice'])) {
                $minimumArticlePrice = $pluginInfos['minimumArticlePrice'];
                if ($pluginInfos['minimumArticlePriceUnit'] == '%') {
                    $minimumArticlePrice = $sShippingcostsDifference / 100 * $minimumArticlePrice;
                }
                $qb->andWhere('prices.price >= :minimumArticlePrice')
                    ->setParameter('minimumArticlePrice',$minimumArticlePrice);
            }

            // maximum article price
            if (!empty($pluginInfos['maximumArticlePrice'])) {
                $maximumArticlePrice = $pluginInfos['maximumArticlePrice'];
                if ($pluginInfos['maximumArticlePriceUnit'] == '%') {
                    $maximumArticlePrice = $sShippingcostsDifference / 100 * $maximumArticlePrice;
                }
                $qb->andWhere('prices.price <= :maximumArticlePrice')
                    ->setParameter('maximumArticlePrice',$maximumArticlePrice);
            }

            // maximun overhang
            if (!empty($pluginInfos['maximumOverhang'])) {
                $qb ->andWhere('(prices.price - '.$sShippingcostsDifference.') <= :overhang')
                    ->setParameter(
                        'overhang',$pluginInfos['maximumOverhang']
                    )
                ;
            }
        }

        // categories and suppliers
        switch ($pluginInfos['consider']) {
            case 'category':
                $qb->leftJoin('article.allCategories', 'allCategories')
                    ->andWhere('allCategories.id IN (:categoryIDs)')
                    ->setParameter('categoryIDs', $this->getCategoryIdsFromArticleIds($articleIds));
                break;
            case 'supplier':
                $qb->leftJoin('article.supplier', 'supplier')
                    ->andWhere('supplier.id IN (:supplierIDs)')
                    ->setParameter('supplierIDs', $this->getSupplierIdsFromBasket($sBasket));
                break;
            case 'categoryAndSupplier':
                $qb->leftJoin('article.allCategories', 'allCategories')
                    ->leftJoin('article.supplier', 'supplier')
                    ->andWhere('allCategories.id IN (:categoryIDs) AND supplier.id IN (:supplierIDs)')
                    ->setParameter('categoryIDs', $this->getCategoryIdsFromArticleIds($articleIds))
                    ->setParameter('supplierIDs', $this->getSupplierIdsFromBasket($sBasket));
                break;
            case 'categoryOrSupplier':
                $qb->leftJoin('article.allCategories', 'allCategories')
                    ->leftJoin('article.supplier', 'supplier')
                    ->andWhere('allCategories.id IN (:categoryIDs) OR supplier.id IN (:supplierIDs)')
                    ->setParameter('categoryIDs', $this->getCategoryIdsFromArticleIds($articleIds))
                    ->setParameter('supplierIDs', $this->getSupplierIdsFromBasket($sBasket));
                break;
            default:
                break;
        }

        if (!empty($pluginInfos['similarArticles'])) {
            $qb->leftJoin('article.similar', 'similar')
                ->leftJoin('similar.related','related')
                ->andWhere('similar.active = 1')
                ->andWhere('similar.id IN (:articleIDs)')
            ;
        }

        if (!empty($pluginInfos['sorting'])) {
            switch ($pluginInfos['sorting']) {
                case 'price ascending':
                    $qb->orderBy('prices.price');
                    break;
                case 'price descending':
                    $qb->orderBy('prices.price','DESC');
                    break;
                case 'votes ascending':
                    $qb->leftJoin('article.votes','votes')
                        ->addOrderBy('SUM(votes.points)/COUNT(votes.articleID)', 'DESC')
                        ->addOrderBy('COUNT(votes.articleID)', 'DESC');
                    break;
                case 'stock ascending':
                    $qb->orderBy('detail.inStock','DESC');
                    break;
                case 'stock descending':
                    $qb->orderBy('detail.inStock');
                    break;
            }
        }

        if (!empty($pluginInfos['maxArticle'])) {
            $qb->setMaxResults($pluginInfos['maxArticle']);
        }

        return $qb->getQuery();
    }

    private function getCategoryIdsFromArticleIds($articleIDs)
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

    private function getSupplierIdsFromBasket($sBasket) {
        if (empty($sBasket['content']))
            return [];

        $supplierIDs = [];
        foreach ($sBasket['content'] as $articleFromBasket) {

            if (empty($articleFromBasket['additional_details']))
                continue;

            $supplierID = $articleFromBasket['additional_details']['supplierID'];
            if (empty($supplierID))
                continue;

            $supplierIDs[$supplierID] = $supplierID;
        }
        return $supplierIDs;
    }

}