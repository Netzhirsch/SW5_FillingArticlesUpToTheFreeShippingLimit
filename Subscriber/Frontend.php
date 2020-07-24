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


        $fillingArticles
                    = $this->getFillingArticles($assign['sBasket'],$pluginInfos,$assign['sShippingcostsDifference']);

        if (!empty($fillingArticles)) {
            $view->assign(['fillingArticles' => $fillingArticles]);
            $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
        }
    }

    private function getFillingArticles($sBasket,$pluginInfos,$sShippingcostsDifference) {

        $articleIds = $this->getArticleIdsFromBasket($sBasket);

        // default query
        $qb = $this->modelManager->createQueryBuilder();
        $qb->select('article')
            ->from(Article::class,'article')
            ->where('article.id NOT IN (:articleIDs)')
            ->setParameter('articleIDs',$articleIds);

        // article combinations forbidden
        if(!$pluginInfos['isCombineAllowed']) {
            $qb->addSelect('prices')
                ->addSelect('detail')
                ->leftJoin('article.mainDetail','detail')
                ->leftJoin('detail.prices','prices')
                ->andWhere('prices.price >= :sShippingcostsDifference')
                ->setParameter('sShippingcostsDifference',$sShippingcostsDifference);
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
        /** @var Article[] $articles */
        $articles =$qb->getQuery()->getResult();
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