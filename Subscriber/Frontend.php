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
        $pluginInfos = $this->config->getByPluginName($this->pluginName);
        $consider = null;
        $fillingArticles = [];
        $template = $view->Template();
        $template_resource = $template->template_resource;
        if ($template_resource == 'frontend/checkout/ajax_cart.tpl')
            return;
        if (empty($pluginInfos['consider']))
            return;

        $assign = $view->getAssign();
        if (empty($assign['sShippingcostsDifference']))
            return;

        $consider = $pluginInfos['consider'];
        if (!empty($consider)) {

            if (empty($assign['sBasket']))
                return;

            $sBasket = $assign['sBasket'];
            if (empty($sBasket['content']))
                return;

            $supplierIDs = [];
            $articleIDs = [];
            $categoryIDs = [];
            $articlesFromBasket = $sBasket['content'];
            foreach ($articlesFromBasket as $articleFromBasket) {
                if (empty($articleFromBasket['articleID']))
                    continue;

                $articleModel
                    = Shopware()->Modules()->Articles()->sGetArticleById($articleFromBasket['articleID']);

                $articleIDs[] = $articleFromBasket['articleID'];

                $categoryID = $articleModel['categoryID'];
                if (!in_array($categoryID,$categoryIDs))
                    $categoryIDs[] = $categoryID;

                if (empty($articleFromBasket['additional_details']))
                    continue;

                $additionalDetails = $articleFromBasket['additional_details'];
                if (empty($additionalDetails['supplierID']))
                    continue;
                $supplierID = $additionalDetails['supplierID'];

                if (!in_array($supplierID,$supplierIDs))
                    $supplierIDs[] = (string)$supplierID;
            }
            $fillingArticles = $this->getFillingArticles(
                [
                    'articleIDs' => $articleIDs,
                    'categoryIDs' => $categoryIDs,
                    'supplierIDs' => $supplierIDs,
                ],
                $consider
            );
        }
        $view->assign(['fillingArticles' => $fillingArticles]);

        $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }

    private function getFillingArticles(array $ids,$consider) {

        $fillingArticles = [];
        $qb = $this->modelManager->createQueryBuilder();

        $qb->select('article')
            ->from(Article::class,'article')
            ->where('article.id NOT IN (?1)')
            ->setParameter(1,$ids['articleIDs']);

        if (
            $consider == 'categoryAndSupplier'
            && !empty($ids['categoryIDs'])
            && !empty($ids['supplierIDs'])
            || $consider == 'categoryOrSupplier'
            && !empty($ids['categoryIDs'])
            && !empty($ids['supplierIDs'])
        ) {
            $qb->leftJoin('article.allCategories','allCategories')
                ->leftJoin('article.supplier','supplier')
                ->andWhere('allCategories.id IN (?3)');
            if ($consider == 'categoryAndSupplier')
                $qb->andWhere('supplier.id IN (?2)');
            else
                $qb->orWhere('supplier.id IN (?2)');
            $qb->setParameter(3,$ids['categoryIDs'])
                ->setParameter(2,$ids['supplierIDs']);

        }
        if ($consider == 'supplier' && !empty($ids['supplierIDs'])) {
            $qb->leftJoin('article.supplier','supplier')
                ->andWhere('supplier.id IN (?2)')
                ->setParameter(2,$ids['supplierIDs']);
        }

        if ($consider == 'category' && !empty($ids['categoryIDs'])) {
            $qb->leftJoin('article.allCategories','allCategories')
                ->andWhere('allCategories.id IN (?3)')
                ->setParameter(3,$ids['categoryIDs']);
        }

        $query =  $qb->getQuery();
        /** @var Article[] $articles */
        $articles = $query->getResult();

        foreach ($articles as $article) {
            /** @var Detail $details */
            $detail = $article->getMainDetail();
            $ordernumber = $detail->getNumber();
            $articleFromSupplier
                = Shopware()
                ->Modules()
                ->Articles()
                ->sGetArticleById($article->getId(),$ordernumber);

            if (!empty($ordernumber))
                $fillingArticles[$ordernumber] = array_merge($fillingArticles,$articleFromSupplier);
        }

        return $fillingArticles;
    }
}