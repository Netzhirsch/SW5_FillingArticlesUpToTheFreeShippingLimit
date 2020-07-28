<?php
namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Exception;
use Enlight_View_Default;
use Shopware\Bundle\SearchBundle\StoreFrontCriteriaFactory;
use Shopware\Bundle\SearchBundle\VariantSearch;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Compatibility\LegacyStructConverter;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
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

    public function __construct(
        $pluginName,
        ConfigReader $config,
        $pluginDirectory,
        ModelManager $modelManager,
        ContextService $contextService,
        StoreFrontCriteriaFactory $criteriaFactory,
        RepositoryInterface $streamRepository,
        VariantSearch $variantSearch,
        LegacyStructConverter $legacyStructConverter
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
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'addTemplate',
        ];
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @throws Enlight_Exception
     */
    public function addTemplate(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_View_Default $view */
        $view = $args->get('subject')->View();

        // default no filling articles in ajax basket
        $template = $view->Template();
        $template_resource = $template->template_resource;
        if ($template_resource == 'frontend/checkout/ajax_cart.tpl')
            return;

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
            $view->assign(['fillingArticles' => $fillingArticles]);
            $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
        }
    }

    private function getFillingArticles($sBasket,$pluginInfos,$sShippingcostsDifference) {

        $fillingArticles = $this->getFillingArticlesFromProductStreams($pluginInfos['productStream']);
        if (!empty($fillingArticles))
            return $fillingArticles;

        $fillingArticles = $this->getFillingArticlesFromTopseller($pluginInfos['topSeller']);
        if (!empty($fillingArticles))
            return $fillingArticles;

        $articleIds = $this->getArticleIdsFromBasket($sBasket);

        //********* exlude articles by plugin setting *****************************************************************/
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                $articleIds[$excludedArticle] = $excludedArticle;
            }
        }

        //********* get article collection ****************************************************************************/
        $query = $this->getQuery($articleIds,$pluginInfos,$sShippingcostsDifference,$sBasket);
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
     * @param string $productStreamsNames
     * @return array $fillingArticles
     */
    private function getFillingArticlesFromProductStreams($productStreamsNames)
    {
        $fillingArticles = [];

        if (!empty($productStreamsNames)) {

            //********* get default criteria **************************************************************************/
            $contextService = $this->contextService;
            /** @var ProductContextInterface $context */
            $context = $contextService->getShopContext();

            $shop = $context->getShop();
            if (empty($shop))
                return $fillingArticles;

            $category = $shop->getCategory();
            if (empty($category))
                return $fillingArticles;

            $categoryId = $category->getId();
            $criteria = $this->criteriaFactory->createBaseCriteria([$categoryId], $context);
            $criteria->offset(0)
                ->limit(10);

            //********* get product stream model by name from plugin config *******************************************/
            $qb = $this->modelManager->createQueryBuilder();
            /** @var ProductStream[] $productSteams */
            $productSteams = $qb
                ->select('productStream')
                ->from(ProductStream::class,'productStream')
                ->where('productStream.name IN (:productStreamsIds)')
                ->setParameter('productStreamsIds',$productStreamsNames)
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

    private function getCategoryIdsFromArticleIds($articleIDs)
    {
        $qb = $this->modelManager->createQueryBuilder();
        $qb->select('category.id')
            ->from(Category::class,'category')
            ->leftJoin('category.articles','article')
            ->where('article.id IN (:articleIDs)')
            ->setParameter('articleIDs',$articleIDs);
        return $qb->getQuery()->getResult();
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
            $supplierID[$supplierID] = $supplierID;
        }
        return $supplierIDs;
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
            ->from(Article::class,'article')
            ->where('article.id NOT IN (:articleIDs)')
            ->setParameter('articleIDs',$articleIds);

        // article combinations forbidden, minimum article price, maximum article price, maximum overhang
        if (
            !$pluginInfos['isCombineAllowed']
            || !empty($pluginInfos['minimumArticlePrice'])
            || !empty($pluginInfos['maximumArticlePrice'])
            || !empty($pluginInfos['maximumOverhang'])
        ) {
            $qb->addSelect('prices')
                ->addSelect('detail')
                ->leftJoin('article.mainDetail','detail')
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
                $qb ->andWhere('(prices.price - '.$sShippingcostsDifference.') <= :over_hang')
                    ->setParameter(
                        'over_hang',$pluginInfos['maximumOverhang']
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

        return $qb->getQuery();
    }

    private function getFillingArticlesFromTopSeller($topSeller)
    {
        if (empty($topSeller))
            return [];

        return Shopware()->Modules()->Articles()->sGetArticleCharts();
    }
}