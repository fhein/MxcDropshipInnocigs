<?php

namespace MxcDropshipIntegrator\Mapping\Import;

use MxcCommons\Interop\Container\ContainerInterface;
use MxcDropshipIntegrator\Mapping\Check\RegularExpressions;
use MxcCommons\ServiceManager\Factory\FactoryInterface;

class PropertyMapperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        // mappers are applied in the order below, take care of mapper dependencies
        $productMappers = [
            // no requirements, sets Shopware number
            'code'          => $container->get(ProductNumberMapper::class),
            // requires product's manufacturer, sets brand and supplier
            'manufacturer'  => $container->get(ManufacturerMapper::class),
            // requires brand, sets name
            'name'          => $container->get(NameMapper::class),
            // requires name, sets piecesPerPack
            'piecesPerPack' => $container->get(ImportPiecesPerPackMapper::class),
            // requires name, sets commonName
            'commonName'    => $container->get(CommonNameMapper::class),
            // requires name, sets type
            'type'          => $container->get(TypeMapper::class),
            // requires name, type, sets content and capacity for liquid products
            'content'       => $container->get(CapacityMapper::class),
            // requires type, sets dosage
            'dosage'        => $container->get(DosageMapper::class),
            // requires manual config, sets flavor and flavorCategory
            'flavor'        => $container->get(FlavorMapper::class),
            // requires type and flavor, sets description
            'description'   => $container->get(DescriptionMapper::class),
            // requires supplier, brand and name, flavor and flavorCategory, sets category
            'category'      => $container->get(CategoryMapper::class),
            // requires name, sets commonName
            'seoInfo'       => $container->get(ProductSeoMapper::class),
            // requires type
            'bulkSupport'   => $container->get(BulkSupportMapper::class),
            // run last
            'ignoredOptions' => $container->get(IgnoredOptionRemover::class),
        ];

        $variantMappers = [
            // no requirements, sets Shopware number
            'code' => $container->get(VariantNumberMapper::class),
        ];

        $associatedProductsMapper = $container->get(AssociatedProductsMapper::class);
        $mappings = $container->get(ProductMappings::class);

        $regularExpressions = $container->get(RegularExpressions::class);

        return new PropertyMapper(
            $mappings,
            $associatedProductsMapper,
            $regularExpressions,
            $productMappers,
            $variantMappers
        );
    }
}

