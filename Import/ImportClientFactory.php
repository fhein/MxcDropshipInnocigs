<?php /** @noinspection PhpUnusedParameterInspection */

namespace MxcDropshipInnocigs\Import;

use Interop\Container\ContainerInterface;
use Mxc\Shopware\Plugin\Database\SchemaManager;
use Mxc\Shopware\Plugin\Service\ObjectAugmentationTrait;
use MxcDropshipInnocigs\Mapping\ImportMapper;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImportClientFactory implements FactoryInterface
{
    use ObjectAugmentationTrait;
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
        $apiClient = $container->get(ApiClient::class);
        $importMapper = $container->get(ImportMapper::class);
        $schemaManager = $container->get(SchemaManager::class);
        $client = new ImportClient($schemaManager, $apiClient, $importMapper);
        return $this->augment($container, $client);
    }
}