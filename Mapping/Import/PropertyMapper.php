<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace MxcDropshipIntegrator\Mapping\Import;

use MxcCommons\Plugin\Service\ClassConfigAwareTrait;
use MxcCommons\Plugin\Service\LoggerAwareTrait;
use MxcCommons\Plugin\Service\ModelManagerAwareTrait;
use MxcCommons\ServiceManager\AugmentedObject;
use MxcDropshipIntegrator\Mapping\Check\RegularExpressions;
use MxcDropshipIntegrator\Models\Model;
use MxcDropshipIntegrator\Models\Product;
use MxcDropshipIntegrator\Models\Variant;
use RuntimeException;

class PropertyMapper implements AugmentedObject
{
    use ModelManagerAwareTrait;
    use ClassConfigAwareTrait;
    use LoggerAwareTrait;

    /** @var array */
    protected $productMappers;

    /** @var array */
    protected $variantMappers;

    /** @var RegularExpressions $regularExpressions */
    protected $regularExpressions;

    /** @var AssociatedProductsMapper $associatedProductsMapper */
    protected $associatedProductsMapper;

    /** @var array */
    protected $mappings;

    /** @var array */
    protected $products = null;

    protected $models = null;

    public function __construct(
        ProductMappings $mappings,
        AssociatedProductsMapper $associatedProductsMapper,
        RegularExpressions $regularExpressions,
        array $productMappers,
            array $variantMappers
        ) {
        $this->productMappers = $productMappers;
        $this->associatedProductsMapper = $associatedProductsMapper;
        $this->mappings = $mappings;
        $this->regularExpressions = $regularExpressions;
        $this->variantMappers = $variantMappers;
        $this->reset();
    }

    public function reset()
    {
        $this->models = null;
        $this->products = null;
    }

    public function mapProperties(array $products, bool $remap)
    {
        if (@$this->classConfig['settings']['checkRegularExpressions'] === true) {
            if (! $this->regularExpressions->check()) {
                throw new RuntimeException('Regular expression failure.');
            }
        }
        $this->reset();
        $models = $this->getModels();
        if (! $models) {
            $this->log->debug(__FUNCTION__ . ': no importFromApi models found.');
            return;
        }

        /** @var Product $product */
        foreach ($products as $product) {
            $variants = $product->getVariants();
            $first = true;
            /** @var Variant $variant */
            foreach ($variants as $variant) {
                $model = $models[$variant->getIcNumber()];
                // do nothing if we do not know the model
                if (! $model) continue;
                if ($first) {
                    $this->mapModelToProduct($model, $product, $remap);
                    $first = false;
                }
                $this->mapModelToVariant($model, $variant);
            }
        }
        // todo: This has to be done at the end, but it consumes tons of time
        // $this->associatedProductsMapper->map($products);
        $this->report();
        $this->modelManager->flush();
    }

    /**
     * Set all properties of Product maintained by PropertyMapper
     *
     * @param Model $model
     * @param Product $product
     * @param bool $remap
     */
    public function mapModelToProduct(Model $model, Product $product, bool $remap)
    {
        foreach ($this->productMappers as $productMapper) {
            $productMapper->map($model, $product, $remap);
        }
    }

    /**
     * Set all properties of Variant maintained by PropertyMapper
     *
     * @param Model $model
     * @param Variant $variant
     */
    public function mapModelToVariant(Model $model, Variant $variant)
    {
        foreach ($this->variantMappers as $mapper) {
            $mapper->map($model, $variant);
        }
    }

    public function mapCategory(Model $model, Product $product) {
        $this->productMappers['category']->map($model, $product);
    }

    public function mapName(Model $model, Product $product) {
        $this->productMappers['name']->map($model, $product);
    }

    public function mapManufacturer(Model $model, Product $product)
    {
        $this->productMappers['manufacturer']->map($model, $product);
    }

    public function mapDescription(Model $model, Product $product)
    {
        $this->productMappers['description']->map($model, $product);
    }

    public function mapGroupName($name)
    {
        return $this->classConfig['group_names'][$name] ?? $name;
    }

    public function mapOptionName($name)
    {
        $mapping = $this->classConfig['option_names'][$name] ?? $name;
        return str_replace('weiss', 'weiß', $mapping);
    }

    protected function getModels()
    {
        return $this->models ?? $this->models = $this->modelManager->getRepository(Model::class)->getAllIndexed();
    }

    protected function report()
    {
        foreach ($this->productMappers as $productMapper) {
            $productMapper->report();
        }
    }
}