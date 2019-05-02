<?php

namespace MxcDropshipInnocigs\Mapping\Import;

use Mxc\Shopware\Plugin\Service\ClassConfigAwareInterface;
use Mxc\Shopware\Plugin\Service\ClassConfigAwareTrait;
use MxcDropshipInnocigs\Models\Model;
use MxcDropshipInnocigs\Models\Product;
use MxcDropshipInnocigs\Report\ArrayReport;

class ManufacturerMapper implements ProductMapperInterface, ClassConfigAwareInterface
{
    use ClassConfigAwareTrait;

    /** @var array */
    protected $report;

    /** @var array */
    protected $mappings;

    /** @var array */
    protected $innocigsBrands;

    public function __construct(array $mappings)
    {
        $this->mappings = $mappings;
    }

    public function map(Model $model, Product $product): void
    {
        $this->innocigsBrands = $this->classConfig['innocigs_brands'] ?? [];
        $this->mapBrand($model, $product);
        $this->mapSupplier($model, $product);
    }

    protected function mapSupplier(Model $model, Product $product)
    {
        $supplier = $this->mappings[$product->getIcNumber()]['supplier'] ?? null;
        if (! $supplier) {
            // @todo: Shark Attack und Military werden mit falschem Manufacturer Mule Fuel ausgeliefert
            $manufacturer = $model->getManufacturer();
            if (!in_array($manufacturer, $this->innocigsBrands)) {
                $supplier = @$this->classConfig['manufacturers'][$manufacturer]['supplier'] ?? $manufacturer;
            }
        }
        $product->setSupplier($supplier);
        $this->report['supplier'][$supplier] = true;
    }

    protected function mapBrand(Model $model, Product $product)
    {
        $brand = $this->mappings[$product->getIcNumber()]['brand'] ?? null;
        if (! $brand) {
            // @todo: Shark Attack und Military werden mit falschem Manufacturer Mule Fuel ausgeliefert
            $manufacturer = $model->getManufacturer();
            $brand = $this->classConfig['manufacturers'][$manufacturer]['brand'] ?? $manufacturer;
        }
        $product->setBrand($brand);
        $this->report['brand'][$brand] = true;
    }

    public function report()
    {
        $reporter = new ArrayReport();
        ksort($this->report['brand']);
        ksort($this->report['supplier']);
        $reporter([
            'pmBrands' => array_keys($this->report['brand']),
            'pmSupplier' => array_keys($this->report['supplier'])
        ]);
    }

}