<?php

namespace MxcDropshipIntegrator\Mapping\Shopware;

use MxcCommons\Interop\Container\ContainerInterface;
use MxcCommons\Plugin\Service\ObjectAugmentationTrait;
use MxcDropshipIntegrator\Toolbox\Shopware\ArticleTool;
use MxcDropshipIntegrator\Toolbox\Shopware\Configurator\OptionSorter;
use Shopware\Components\Api\Resource\Article as ArticleResource;
use MxcCommons\ServiceManager\Factory\FactoryInterface;

class DetailMapperFactory implements FactoryInterface
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
        $priceMapper = $container->get(PriceMapper::class);
        $articleTool = $container->get(ArticleTool::class);
        $articleResource = new ArticleResource();
        $articleResource->setManager($container->get('modelManager'));
        $companion = $container->get(DropshippersCompanion::class);
        $optionMapper = $container->get(OptionMapper::class);

        return $this->augment($container, new DetailMapper($articleTool, $articleResource, $companion, $priceMapper, $optionMapper));
    }
}