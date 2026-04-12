<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        return dirname(__DIR__) . '/var/cache/' .$this->environment;
    }

    public function getLogDir(): string
    {
        return dirname(__DIR__) . '/var/log';
    }

    public function registerBundles(): iterable
    {
        $contents = require dirname(__DIR__) . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.{php,xml,yaml,yml}');
        if (is_dir(dirname(__DIR__).'/config/packages/'.$this->environment)) {
            $container->import('../config/{packages}/'.$this->environment.'/**/*.{php,xml,yaml,yml}');
        }
        $container->import('../config/{services}.{php,xml,yaml,yml}');
        $container->import('../config/{services}_'.$this->environment.'.{php,xml,yaml,yml}');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        if (is_dir(dirname(__DIR__).'/config/routes/'.$this->environment)) {
            $routes->import('../config/{routes}/'.$this->environment.'/**/*.{php,xml,yaml,yml}');
        }
        if (is_dir(dirname(__DIR__).'/config/routes')) {
            $routes->import('../config/{routes}/*.{php,xml,yaml,yml}');
        }
        $routes->import('../config/{routes}.{php,xml,yaml,yml}');
    }
}
