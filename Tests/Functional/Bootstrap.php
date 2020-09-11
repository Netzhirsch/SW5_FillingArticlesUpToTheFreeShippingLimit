<?php

require __DIR__ . '/../../../../../autoload.php';

use Doctrine\DBAL\Connection;
use Shopware\Kernel;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;

class NetzhirschFillingArticlesUpToTheFreeShippingLimitTestKernel extends Kernel
{
    /**
     * @throws Exception
     */
    public static function start()
    {
        $kernel = new self(getenv('SHOPWARE_ENV') ?: 'testing', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $container->get('plugins')->Core()->ErrorHandler()->registerErrorHandler(E_ALL | E_STRICT);

        /** @var Repository $repository */
        $repository = $container->get('models')->getRepository(Shop::class);

        if ($container->has('shopware.components.shop_registration_service')) {
            $container->get('shopware.components.shop_registration_service')->registerResources(
                $repository->getActiveDefault()
            );
        } else {
            $repository->getActiveDefault()->registerResources();
        }

        if (!self::isPluginInstalledAndActivated()) {
            die('Error: The plugin is not installed or activated, tests aborted!');
        }

        Shopware()->Loader()->registerNamespace('NetzhirschFillingArticlesUpToTheFreeShippingLimit',
            __DIR__.'/../'
        );
    }

    /**
     * @return bool
     */
    public static function isPluginInstalledAndActivated()
    {
        /** @var Connection $db */
        $db = Shopware()->Container()->get('dbal_connection');

        $sql = "SELECT active FROM s_core_plugins WHERE name='NetzhirschFillingArticlesUpToTheFreeShippingLimit'";
        $active = $db->fetchColumn($sql);

        return (bool) $active;
    }
}

NetzhirschFillingArticlesUpToTheFreeShippingLimitTestKernel::start();
