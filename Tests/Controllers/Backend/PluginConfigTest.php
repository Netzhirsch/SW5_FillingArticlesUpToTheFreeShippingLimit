<?php

namespace NetzhirschFillingArticlesUpToTheFreeShippingLimit\Tests\Functional\Controllers\Backend;

use Enlight_Components_Test_Controller_TestCase;
use PHPUnit\Framework\TestCase;
use Shopware_Controllers_Backend_PluginConfig;
use Shopware_Controllers_Frontend_Checkout;
use Symfony\Component\Finder\Finder;

require_once __DIR__ . '/../../../Controllers/Backend/PluginConfig.php';


class PluginConfigTest extends TestCase
{

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $controller;

    /**
     * @var \ReflectionMethod
     */
    private $method;

    protected function setUp(): void
    {
        $this->controller = $this->createPartialMock(Shopware_Controllers_Frontend_Checkout::class, []);
        $class = new \ReflectionClass($this->controller);
        $this->method = $class->getMethod('postDispatch');
        $this->method->setAccessible(true);
    }

    public function testSortByNonePrefixedColumn()
    {
        $sorts = [
            ['property' => 'orderTime', 'direction' => 'ASC'],
        ];

        static::assertSame(
            [
                ['property' => 'orders.orderTime', 'direction' => 'ASC'],
            ],
            $this->method->invokeArgs($this->controller, [$sorts])
        );
    }
}