<?php
defined('_JEXEC') or die;

use FGcodework\Component\FgExtensionCleanup\Administrator\Extension\FgExtensionCleanupComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $namespace = 'FGcodework\\Component\\FgExtensionCleanup';
        $container->registerServiceProvider(new MVCFactory($namespace));
        $container->registerServiceProvider(new ComponentDispatcherFactory($namespace));
        $container->set(ComponentInterface::class, static function (Container $container) {
            $component = new FgExtensionCleanupComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            return $component;
        });
    }
};
