<?php

namespace MxcDropshipInnocigs\Mapping\Import;

use MxcDropshipInnocigs\Models\Model;
use MxcDropshipInnocigs\Models\Product;
use MxcDropshipInnocigs\Report\ArrayReport;

class CategoryMapper extends BaseImportMapper implements ProductMapperInterface
{
    /** @var array */
    protected $report;

    public function map(Model $model, Product $product) {
        $type = $product->getType();
        $category = $this->classConfig['categories'][$type] ?? null;

        switch ($type) {
            case 'LIQUID':
            case 'AROMA':
            case 'SHAKE_VAPE':
            case 'BASE':
            case 'SHOT':
                $category = $this->addSubCategory($category, $product->getBrand());
                break;

            case 'E_PIPE':
            case 'E_CIGARETTE':
            case 'BOX_MOD':
            case 'BOX_MOD_CELL':
            case 'SQUONKER_BOX':
            case 'CLEAROMIZER':
            case 'HEAD':
            case 'DRIP_TIP':
            case 'TANK':
            case 'SEAL':
            case 'TOOL':
            case 'CLEAROMIZER_RTA':
            case 'CLEAROMIZER_RDA':
            case 'CLEAROMIZER_RDTA':
            case 'CLEAROMIZER_RDSA':
                $category = $this->addSubCategory($category, $product->getSupplier());
                break;

            case 'CARTRIDGE':
            case 'POD':
            case 'BATTERY_CAP':
            case 'BATTERY_SLEEVE':
            case 'MAGNET':
            case 'MAGNET_ADAPTOR':
                $category = $this->addSubCategory($category, $product->getCommonName());
                break;
        }
        $product->setCategory($category);
        $this->report[$category][] = $product->getName();
    }

    protected function addSubCategory(string $category, ?string $subCategory)
    {
        return $subCategory = null ? $category : $category . ' > ' . $subCategory;
    }

    public function report()
    {
        ksort($this->report);
        foreach ($this->report as &$array) {
            sort($array);
        }

        (new ArrayReport())([
            'pmCategoryUsage' => $this->report,
            'pmCategory'      => array_keys($this->report),
        ]);
    }
}