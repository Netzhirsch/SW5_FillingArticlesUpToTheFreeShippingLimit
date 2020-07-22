<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;

class NetzhirschFillingArticlesUpToTheFreeShippingLimit extends Plugin
{

    public function install(InstallContext $context)
    {
        parent::install($context);
    }

    public function uninstall(UninstallContext $context)
    {
        if ($context->keepUserData())
            return;

        $context->scheduleClearCache([
            UninstallContext::CACHE_TAG_CONFIG
        ]);
    }
}