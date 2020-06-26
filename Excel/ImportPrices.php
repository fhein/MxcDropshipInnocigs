<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace MxcDropshipInnocigs\Excel;

use Mxc\Shopware\Plugin\Service\LoggerAwareInterface;
use Mxc\Shopware\Plugin\Service\LoggerAwareTrait;
use MxcDropshipInnocigs\Mapping\Shopware\PriceMapper;
use MxcDropshipInnocigs\Models\Model;
use MxcDropshipInnocigs\Models\Variant;
use MxcDropshipInnocigs\MxcDropshipInnocigs;

class ImportPrices extends AbstractProductImport implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var array */
    protected $indexMap;

    /** @var array */
    protected $models;

    /** @var PriceMapper $priceMapper */
    protected $priceMapper;

    public function __construct(
        PriceMapper $priceMapper
    ) {
        $this->priceMapper = $priceMapper;
    }

    public function processImportData(array &$data)
    {
        $keys = array_keys($data[0]);
        $this->indexMap = [];
        foreach ($keys as $key) {
            if (strpos($key, 'VK Brutto') === 0) {
                $customerGroupKey = explode(' ', $key)[2];
                $this->indexMap[$key] = $customerGroupKey;
            }
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $variants = $this->modelManager->getRepository(Variant::class)->getAllIndexed();
        $this->models = $this->modelManager->getRepository(Model::class)->getAllIndexed();
        /** @var Variant $variant */

        foreach ($data as $record) {
            $variant = $variants[$record['icNumber']] ?? null;
            if (!$variant) continue;
            $variant->setRetailPriceDampfPlanet($record['Dampfplanet']);
            $variant->setRetailPriceMaxVapor($record['MaxVapor']);
            $variant->setRetailPriceOthers($record['andere']);
            $variant->setPurchasePrice($record['EK Netto']);
            $variant->setRecommendedRetailPrice($record['UVP Brutto']);

            $this->updateVariantPrice($variant, $record);
        }

        $this->modelManager->flush();
    }

    protected function updateVariantPrice(Variant $variant, array $record)
    {
        $prices = [];
        $customerPrice = $record['VK Brutto EK'];
        // if no price is specified we take the UVP
        if (! $customerPrice || $customerPrice === '') $customerPrice = $record['UVP Brutto'];

        $customerPrice = $customerPrice === '' ? null : $customerPrice;

        foreach ($this->indexMap as $column => $customerGroup) {
            $price = $record[$column];
            $price = $price === '' ? null : $price;
            $price = $price ?? $customerPrice;
            if ($price) {
                $prices[] = $customerGroup . MxcDropshipInnocigs::MXC_DELIMITER_L1 . $price;
            }
        }
        $this->log->debug(var_export($prices, true));
        $prices = implode(MxcDropshipInnocigs::MXC_DELIMITER_L2, $prices);
        $variant->setRetailPrices($prices);
        $this->priceMapper->setRetailPrices($variant);
    }
}