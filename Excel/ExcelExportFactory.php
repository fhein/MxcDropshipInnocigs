<?php /** @noinspection PhpUnused */

namespace MxcDropshipIntegrator\Excel;

use MxcCommons\Interop\Container\ContainerInterface;
use MxcCommons\Plugin\Service\ObjectAugmentationTrait;
use MxcCommons\ServiceManager\Factory\FactoryInterface;

class ExcelExportFactory implements FactoryInterface
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
        $config = $container->get('config')['excel']['export'] ?? [];
        $config = $config[$options[0]];

        $exporters = [];
        foreach ($config as $idx => $service) {
            $exporters[$idx] = $container->get($service);
        }

        return $this->augment($container, new ExcelExport($exporters));
    }
}