<?php
namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Subscriber;

use Doctrine\DBAL\Connection;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_View_Default;
use Shopware\Components\Plugin\ConfigReader;

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
     * @var Connection
     */
    private $connection;


    public function __construct($pluginName,ConfigReader $config,$pluginDirectory,Connection $connection)
    {
        $this->pluginName = $pluginName;
        $this->config = $config;
        $this->pluginDirectory = $pluginDirectory;
        $this->connection = $connection;
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
     */
    public function addTemplate(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_View_Default $view */
        $view = $args->get('subject')->View();
        $pluginInfos = $this->config->getByPluginName($this->pluginName);
        $consider = null;
        $fillingArticles = [];

        if (empty($pluginInfos['consider']))
            return;

        $consider = $pluginInfos['consider'];

        switch ($consider) {
            case 'category':
                $assign = $view->getAssign();
                $categoryIDs = [];
                if (empty($assign['sBasket']))
                    break;

                    $sBasket = $assign['sBasket'];
                if (empty($sBasket['content']))
                    break;

                    $articlesFromBasket = $sBasket['content'];
                    foreach ($articlesFromBasket as $articleFromBasket) {
                        if (empty($articleFromBasket['articleID']))
                            continue;

                        $articleModel = Shopware()->Modules()->Articles()->sGetArticleById($articleFromBasket['articleID']);

                        $categoryID = $articleModel['categoryID'];
                        if (in_array($categoryID,$categoryIDs))
                            continue;

                        $categoryIDs[] = $categoryID;

                        $sql = '
                            SELECT articleID
                            FROM s_articles_categories
                            WHERE categoryID = ?
                        ';
                        $articleIds = $this->connection->fetchAll($sql, [$categoryID]);

                        foreach ($articleIds as $articleId) {
                            if ($articleFromBasket['articleID'] == $articleId['articleID'])
                                continue;

                            $id = $articleId['articleID'];
                            $sql = '
                                SELECT ordernumber
                                FROM s_articles_details
                                WHERE articleID = ?
                            ';
                            $ordernumber = $this->connection->fetchColumn($sql, [$id]);
                            $articleFromBasket = Shopware()->Modules()->Articles()->sGetArticleById($id,$ordernumber);
                            $fillingArticles[$ordernumber]
                                = array_merge($fillingArticles,$articleFromBasket);
                        }
                    }
                break;
        }
        $view->assign(['fillingArticles' => $fillingArticles]);

        $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }

}