<?php

namespace MxcDropshipInnocigs\Mapping\Csv;

use Doctrine\Common\Collections\Collection;
use Mxc\Shopware\Plugin\Service\LoggerInterface;
use MxcDropshipInnocigs\Mapping\ArticleMapper;
use MxcDropshipInnocigs\Models\Article;
use MxcDropshipInnocigs\Models\Model;
use MxcDropshipInnocigs\Models\Variant;
use MxcDropshipInnocigs\Toolbox\Shopware\PriceTool;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Writer;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Price;
use const MxcDropshipInnocigs\MXC_DELIMITER_L1;
use const MxcDropshipInnocigs\MXC_DELIMITER_L2;

class ArticlePrices
{
    protected $articlePricesFile = __DIR__ . '/../../Config/article.prices.xlsx';

    /** @var ArticleMapper $articleMapper */
    protected $articleMapper;

    /** @var ModelManager $modelManager */
    protected $modelManager;

    /** @var LoggerInterface $log */
    protected $log;

    /** @var array */
    protected $indexMap;

    /** @var array */
    protected $models;

    /** @var array */
    protected $articles;

    /** @var PriceTool $priceTool */
    protected $priceTool;

    public function __construct(
        ModelManager $modelManager,
        ArticleMapper $articleMapper,
        PriceTool $priceTool,
        LoggerInterface $log
    ) {
        $this->log = $log;
        $this->modelManager = $modelManager;
        $this->articleMapper = $articleMapper;
        $this->priceTool = $priceTool;
    }

    public function import()
    {
        $records = $this->readExcelSheet();
        if (! is_array($records) || empty($records)) return;

        $this->entitiesToArray($records);

        $this->log->debug(var_export($records[0], true));
        $keys = array_keys($records[0]);
        $this->indexMap = [];
        foreach ($keys as $key) {
            if (strpos($key, 'VK brutto') === 0) {
                $customerGroupKey = explode(' ', $key)[2];
                $this->indexMap[$key] = $customerGroupKey;
            }
        }

        foreach ($records as $record) {
            $this->updatePrices($record);
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->modelManager->flush();
    }

    public function export()
    {

        $articles = $this->getArticles();
        $data = [];
        $headers = null;
        foreach ($articles as $article) {
            $data[] = $this->getArticleInfo($article);
        }
        $headers[] = array_keys($data[0]);
        usort($data,
            function($one, $two) {
                $t1 = $one['type'];
                $t2 = $two['type'];
                if ($t1 > $t2) {
                    return true;
                }
                if ($t1 === $t2) {
                    $s1 = $one['supplier'];
                    $s2 = $two['supplier'];
                    if ($s1 > $s2) {
                        return true;
                    }
                    if ($s1 === $s2) {
                        return $one['brand'] > $two['brand'];
                    }
                }
                return false;
            }
        );
        $data = array_merge($headers, $data);

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->createExcelSheet($data);
//        $csv = new CsvTool();
//        $csv->export($this->articlePricesCsv, $data);
    }

    protected function updatePrices(array $record)
    {
        /** @var Article $article */
        $article = $this->getArticles()[$record['icNumber']];
        if (! $article) return;

        $prices = [];
        $uvp = $record['UVP brutto'];
        $uvp = $uvp = '' ? null : $uvp;

        foreach ($this->indexMap as $column => $customerGroup) {
            $price = $record[$column];
            $price = $price === '' ? null : $price;
            $price = $price ?? $uvp;
            if ($price) {
                $prices[] = $customerGroup . MXC_DELIMITER_L1 . $price;
            }
        }
        $prices = implode(MXC_DELIMITER_L2, $prices);

        $variants = $article->getVariants();
        /** @var Variant $variant */
        foreach ($variants as $variant) {
            if (! $this->isSinglePack($variant)) continue;
            $variant->setRetailPrices($prices);
            $this->priceTool->setRetailPrices($variant);
        }
    }

    protected function getArticles()
    {
        if (!$this->articles) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->articles = $this->modelManager->getRepository(Article::class)->getAllIndexed();
        }
        return $this->articles;
    }

    protected function getModels()
    {
        if (!$this->models) {
            $this->models = $this->modelManager->getRepository(Model::class)->getAllIndexed();
        }
        return $this->models;
    }

    protected function getArticleInfo(Article $article)
    {
        $info['icNumber'] = $article->getIcNumber();
        $info['type'] = $article->getType();
        $info['supplier'] = $article->getSupplier();
        $info['brand'] = $article->getBrand();
        $info['name'] = $article->getName();
        list($info['EK netto'], $info['UVP brutto']) = $this->getPrices($article->getVariants());
        $customerGroupKeys = array_keys($this->priceTool->getCustomerGroups());
        $shopwarePrices = $this->getCurrentPrices($article);
        foreach ($customerGroupKeys as $key) {
            $info['VK brutto ' . $key] = $shopwarePrices[$key] ?? '';
        }
        return $info;
    }

    protected function isSinglePack(Variant $variant)
    {
        $model = $this->getModels()[$variant->getIcNumber()];
        if (strpos($model->getOptions(), '1er Packung') !== false) {
            return true;
        }
        return false;
    }

    protected function getCurrentPrices(Article $article)
    {
        $variants = $article->getVariants();
        /** @var Variant $variant */
        $detailPrices = [];
        foreach ($variants as $variant) {
            if (! $this->isSinglePack($variant)) continue;
            $detail = $variant->getDetail();
            if (! $detail) continue;
            $shopwarePrices = $detail->getPrices();
            /** @var Price $price */
            foreach ($shopwarePrices as $price) {
                $detailPrices[$price->getCustomerGroup()->getKey()][$variant->getIcNumber()] = $price->getPrice();
            }
        }
        $prices = [];
        foreach ($detailPrices as $key => $price) {
            // $prices[$key] = str_replace ('.', ',', strval(max($price) * 1.19));
            $prices[$key] = max($price) * 1.19;
        }

        return $prices;
    }


    protected function getPrices(Collection $variants)
    {
        $purchasePrice = 0.0;
        $retailPrice = 0.0;
        /** @var Variant $variant */
        foreach ($variants as $variant) {
            /** @var Model $model */
            if (! $this->isSinglePack($variant)) continue;
            $price = $variant->getPurchasePrice();
            if ($price > $purchasePrice) {
//                $purchasePrice = str_replace('.', ',', strval($price));
                  $purchasePrice = $price;
            }
            $price = $variant->getRecommendedRetailPrice();
            if ($price > $retailPrice) {
//                $retailPrice = str_replace('.', ',', strval($price));
                $retailPrice = $price;
            }
        }
        return [$purchasePrice, $retailPrice];
    }

    /**
     * @param array $data
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function createExcelSheet(array $data): void
    {
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->fromArray($data);
        $sheet->setTitle('Preise');
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('E')->setWidth(80);
        foreach (range('F', 'K') as $col) {
            $sheet->getColumnDimension($col)->setWidth(16);

        }
        $sheet->freezePane('A2');
        $highest = $sheet->getHighestRowAndColumn();

        $sheet->getStyle('F2:'. $highest['column'] . $highest['row'])->getNumberFormat()->setFormatCode('0.00');
        $writer = new Writer($spreadSheet);
        $writer->save($this->articlePricesFile);
    }

    protected function readExcelSheet() : array
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $spreadSheet = (new Reader())->load($this->articlePricesFile);
        /** @noinspection PhpUnhandledExceptionInspection */
        $sheet = $spreadSheet->getSheet(0);
        $records = $this->entitiesToArray($sheet->toArray());
        $spreadSheet->disconnectWorksheets();
        $spreadSheet->garbageCollect();
        unset ($spreadSheet);
        return $records;
    }

    protected function entitiesToArray(array $entities)
    {
        $headers = null;
        foreach ($entities as &$entity) {
//            $entity = str_getcsv($entity, $delimiter);
            if (! $headers) {
                $headers = $entity;
                continue;
            }
            $entity = array_combine($headers, $entity);
        }
        // remove header entity
        array_shift($entities);
        return $entities;

    }
}