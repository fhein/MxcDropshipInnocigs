<?php /** @noinspection PhpUnusedParameterInspection */

namespace MxcDropshipInnocigs\Listener;

use Interop\Container\ContainerInterface;
use MxcDropshipInnocigs\Client\ApiClient;
use Zend\ServiceManager\Factory\FactoryInterface;

class InnocigsClientFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @param  null|array $options
     * @return object
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('config')->plugin->$requestedName;
        $apiClient = $container->get(ApiClient::class);
        $log = $container->get('logger');
        $modelManager = $container->get('modelManager');
        return new InnocigsClient($modelManager, $apiClient, $config, $log);
    }
}