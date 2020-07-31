<?php
namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Exception;
use Enlight_View_Default;
use NetzhirschFillingArticlesUpToTheFreeShippingLimit\Services\FillingArticleGetter;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Theme\LessDefinition;
use Shopware_Components_Snippet_Manager;

// SCFB = Shipping cost free boarder

class Frontend implements SubscriberInterface
{
    //********* addTemplate() ****************************************************************************************/
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

    public function __construct(
        ConfigReader $config,
        $pluginName,
        FillingArticleGetter $fillingArticleGetter,
        $pluginDirectory,
        Shopware_Components_Snippet_Manager $snippetManager,
        ContextService $contextService
    )
    {
        $this->config = $config;
        $this->pluginName = $pluginName;
        $this->fillingArticleGetter = $fillingArticleGetter;
        $this->pluginDirectory = $pluginDirectory;
        $this->snippetManager = $snippetManager;
        $this->contextService = $contextService;
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

        $sShippingcostsDifference = $assign['sShippingcostsDifference'];
        $fillingArticleGetter = $this->fillingArticleGetter;

        $fillingArticles
            = $fillingArticleGetter->getFillingArticles($assign['sBasket'],$pluginInfos,$sShippingcostsDifference);

        if (!empty($fillingArticles)) {
            $view->assign(['displayVariants' => $pluginInfos['displayVariants']]);
            $view->assign(['fillingArticles' => $fillingArticles]);
            $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
        }

        //********* warning message for ajax cart *********************************************************************/
        $template = $view->Template();
        $template_resource = $template->template_resource;
        if ($template_resource != 'frontend/checkout/ajax_cart.tpl')
            return;

        if (empty($pluginInfos['notAboveBasket']))
            return;

        $message = '';
        $snippetForCart = $this->snippetManager->getNamespace('frontend/checkout/cart');
        if (empty($snippetForCart))
            return;

        $cartInfoFreeShipping = $snippetForCart->offsetGet('CartInfoFreeShipping');
        if (empty($cartInfoFreeShipping))
            return;

        $message .= '<strong>'.$cartInfoFreeShipping.'</strong>';

        $shopContext = $this->contextService->getShopContext();
        $country = $shopContext->getCountry();
        $countryName = $country->getName();
        $currency = $shopContext->getCurrency();
        $currencySymbol = $currency->getSymbol();

        $cartInfoFreeShippingDifference = $snippetForCart->offsetGet('CartInfoFreeShippingDifference');
        if (empty($cartInfoFreeShippingDifference))
            return;
        $cartInfoFreeShippingDifference = str_replace('{$sShippingcostsDifference|',$sShippingcostsDifference,$cartInfoFreeShippingDifference);
        $cartInfoFreeShippingDifference = str_replace('currency}',$currencySymbol,$cartInfoFreeShippingDifference);
        $cartInfoFreeShippingDifference = str_replace('{$sCountry.countryname}',$countryName,$cartInfoFreeShippingDifference);
        $message .= $cartInfoFreeShippingDifference;

        $view->assign(['message' => $message]);
    }
}