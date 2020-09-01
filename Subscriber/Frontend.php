<?php
namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_View_Default;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\ArticleFromAssign;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\FillingArticleGetter;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Theme\LessDefinition;
use Shopware_Components_Snippet_Manager;

// SCFB = Shipping cost free boarder

class Frontend implements SubscriberInterface
{
    /**
     * @var ConfigReader
     */
    private $config;

    /**
     * @var string
     */
    private $pluginName;

    /**
     * @var FillingArticleGetter
     */
    private $fillingArticleGetter;
    /**
     * @var string pluginDirectory
     */
    private $pluginDirectory;

    /**
     * @var Shopware_Components_Snippet_Manager
     */
    private $snippetManager;

    /**
     * @var ContextService
     */
    private $contextService;

    /**
     * @var ArticleFromAssign
     */
    private $articleFromAssign;

    public function __construct(
        ConfigReader $config,
        $pluginName,
        FillingArticleGetter $fillingArticleGetter,
        $pluginDirectory,
        Shopware_Components_Snippet_Manager $snippetManager,
        ContextService $contextService,
        ArticleFromAssign $articleFromAssign
    )
    {
        $this->config = $config;
        $this->pluginName = $pluginName;
        $this->fillingArticleGetter = $fillingArticleGetter;
        $this->pluginDirectory = $pluginDirectory;
        $this->snippetManager = $snippetManager;
        $this->contextService = $contextService;
        $this->articleFromAssign = $articleFromAssign;
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
     */
    public function addTemplate(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_View_Default $view */
        $view = $args->get('subject')->View();
        /**
         * template must set before any return because of the cache
         * see https://forum.shopware.com/discussion/49501/plugin-uncaught-smartyexception-directory-not-allowed-by-security-setting
         */
        $view->addTemplateDir($this->pluginDirectory . '/Resources/views');

        $assign = $view->getAssign();
        $sShippingcostsDifference = $assign['sShippingcostsDifference'];
        $view->assign(['message' => $this->getMessage($sShippingcostsDifference)]);
        //********* no filling articles if basket price is ofer SCFB **************************************************/
        if (empty($assign['sShippingcostsDifference']))
            return;

        //********* show hint to the filling articles *****************************************************************/
        $pluginInfos = $this->config->getByPluginName($this->pluginName);
        $noteAboveBasket = false;
        if (!empty($pluginInfos['noteAboveBasket']))
            $noteAboveBasket = true;

        $view->assign(['noteAboveBasket' => $noteAboveBasket]);

        $sBasket = $assign['sBasket'];
        $sBasket =
            $this->articleFromAssign->assignMissingAmountToShippingCostFreeBoarder(
                $sBasket,
                $sShippingcostsDifference
            );
        //********* show hint on basket article ***********************************************************************/
        $noteArticle = false;
        if (!empty($pluginInfos['noteArticle'])) {
            $noteArticle = true;
            $view->assign(['sBasket' => $sBasket]);
            $view->assign(['CartInfoFreeShipping' => $this->getCartInfoFreeShipping()]);
        }

        $view->assign(['noteArticle' => $noteArticle]);

        $fillingArticleGetter = $this->fillingArticleGetter;

        $fillingArticles
            = $fillingArticleGetter->getFillingArticles($sBasket,$pluginInfos,$sShippingcostsDifference);

        if (!empty($fillingArticles)) {
            $view->assign(['displayVariants' => $pluginInfos['displayVariants']]);
            $view->assign(['viewInAjaxBasket' => $pluginInfos['viewInAjaxBasket']]);
            $view->assign(['fillingArticles' => $fillingArticles]);
        }
    }

    private function getMessage($sShippingcostsDifference)
    {
        $message = '';

        $cartInfoFreeShipping = $this->getCartInfoFreeShipping();
        if (empty($cartInfoFreeShipping))
            return $message;

        $snippetForCart = $this->snippetManager->getNamespace('frontend/checkout/cart');
        if (empty($snippetForCart))
            return $message;
        $message .= '<strong>'.$cartInfoFreeShipping.'</strong>';

        $shopContext = $this->contextService->getShopContext();
        $country = $shopContext->getCountry();
        $countryName = 'Deutschland';
        if (!empty($country))
            $countryName = $country->getName();
        $currency = $shopContext->getCurrency();
        $currencySymbol = $currency->getSymbol();

        $cartInfoFreeShippingDifference = $snippetForCart->offsetGet('CartInfoFreeShippingDifference');
        if (empty($cartInfoFreeShippingDifference))
            return $message;

        $cartInfoFreeShippingDifference = str_replace('{$sShippingcostsDifference|',number_format($sShippingcostsDifference,2,",","."),$cartInfoFreeShippingDifference);
        $cartInfoFreeShippingDifference = str_replace('currency}',$currencySymbol,$cartInfoFreeShippingDifference);
        $cartInfoFreeShippingDifference = str_replace('{$sCountry.countryname}',$countryName,$cartInfoFreeShippingDifference);
        $message .= $cartInfoFreeShippingDifference;

        return $message;
    }

    private function getCartInfoFreeShipping() {

        $snippetForCart = $this->snippetManager->getNamespace('frontend/checkout/cart');
        if (empty($snippetForCart))
            return '';

        return (
            $snippetForCart->offsetGet('CartInfoFreeShipping')
                ? $snippetForCart->offsetGet('CartInfoFreeShipping')
                : ''
        );
    }
}
