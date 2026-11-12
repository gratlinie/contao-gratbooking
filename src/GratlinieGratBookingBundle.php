<?php

declare(strict_types=1);

namespace Gratlinie\GratBookingBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class GratlinieGratBookingBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        // Bezahlte Termine nur mit GratShop
        if (class_exists(\Gratlinie\GratShopBundle\GratlinieGratShopBundle::class)) {
            $container->import('../config/payment.yaml');
        }
    }
}
