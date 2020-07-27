<?php
namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Exception;
use Enlight_View_Default;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Category\Category;

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


    public function __construct(
        $pluginName,
        ConfigReader $config,
        $pluginDirectory,
        ModelManager $modelManager
    )
    {
        $this->pluginName = $pluginName;
        $this->config = $config;
        $this->pluginDirectory = $pluginDirectory;
        $this->modelManager = $modelManager;
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

        $articleIds = $this->getArticleIdsFromBasket($sBasket);

        // exlude articles by plugin setting
        if (!empty($pluginInfos['excludedArticles'])) {
            foreach ($pluginInfos['excludedArticles'] as $excludedArticle) {
                $articleIds[$excludedArticle] = $excludedArticle;
            }
        }

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

        // minimum article price


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
        /** @var Article[] $articles */
        $articles =$qb->getQuery()->getResult();
        //TODO*luhmann delete Debug
        file_put_contents('/var/www/html/shopware/custom/plugins/NetzhirschFillingArticlesUpToTheFreeShippingLimit/tmp.sql',$qb->getQuery()->getSQL());
        // get the missing article data
        $fillingArticles = [];
        foreach ($articles as $article) {

            /** @var Detail $details */
            $detail = $article->getMainDetail();
            $ordernumber = $detail->getNumber();

            if (empty($ordernumber))
                continue;

            $article
                = Shopware()
                ->Modules()
                ->Articles()
                ->sGetArticleById($article->getId(),$ordernumber);

            $fillingArticles[$ordernumber] = array_merge($fillingArticles,$article);
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
}