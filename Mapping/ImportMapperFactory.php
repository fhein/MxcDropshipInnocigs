<?php /** @noinspection PhpUnusedParameterInspection */

namespace MxcDropshipIntegrator\Mapping;

use MxcCommons\Interop\Container\ContainerInterface;
use MxcDropshipIntegrator\Mapping\Import\CategoryMapper;
use MxcDropshipIntegrator\Mapping\Import\PropertyMapper;
use MxcDropshipIntegrator\Mapping\Shopware\DetailMapper;
use MxcCommons\Toolbox\Shopware\ArticleTool;
use MxcCommons\ServiceManager\Factory\FactoryInterface;

class ImportMapperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $articleTool = $container->get(ArticleTool::class);
        $propertyMapper = $container->get(PropertyMapper::class);
        $categoryMapper = $container->get(CategoryMapper::class);
        $productMapper = $container->get(ProductMapper::class);
        $detailMapper = $container->get(DetailMapper::class);
        return new ImportMapper(
            $articleTool,
            $propertyMapper,
            $categoryMapper,
            $productMapper,
            $detailMapper
        );
    }
}