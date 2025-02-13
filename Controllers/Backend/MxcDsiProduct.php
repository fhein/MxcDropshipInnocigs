<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */

use MxcDropshipIntegrator\Models\Option;
use MxcDropship\Jobs\UpdateTrackingData;
use MxcDropshipIntegrator\Mapping\Import\BulkSupportMapper;
use MxcCommons\Toolbox\Shopware\TaxTool;
use MxcDropshipIntegrator\Mapping\Shopware\BulkPriceMapper;
use MxcWorkflow\MxcWorkflow;
use MxcWorkflow\Workflow\WorkflowEngine;
use MxcDropshipInnocigs\Api\ApiClient;
use MxcDropshipInnocigs\Article\ArticleRegistry;
use MxcCommons\Plugin\Controller\BackendApplicationController;
use MxcCommons\Plugin\Database\SchemaManager;
use MxcCommons\Toolbox\Config\Config;
use MxcCommons\Toolbox\Shopware\ArticleTool;
use MxcDropshipInnocigs\MxcDropshipInnocigs;
use MxcCommons\Toolbox\Shopware\SupplierTool;
use MxcDropshipIntegrator\Models\Model;
use MxcDropship\Dropship\DropshipManager;
use MxcDropshipIntegrator\Excel\ExcelExport;
use MxcDropshipIntegrator\Excel\ExcelProductImport;
use MxcDropshipIntegrator\Jobs\PullCategorySeoInformation;
use MxcDropshipIntegrator\Mapping\Check\NameMappingConsistency;
use MxcDropshipIntegrator\Mapping\Check\RegularExpressions;
use MxcDropshipIntegrator\Mapping\Check\VariantMappingConsistency;
use MxcDropshipIntegrator\Mapping\Import\AssociatedProductsMapper;
use MxcDropshipIntegrator\Mapping\Import\CategoryMapper;
use MxcDropshipIntegrator\Mapping\Import\DescriptionMapper;
use MxcDropshipIntegrator\Mapping\Import\ProductSeoMapper;
use MxcDropshipIntegrator\Mapping\Import\PropertyMapper;
use MxcDropshipIntegrator\Mapping\ImportMapper;
use MxcDropshipIntegrator\Mapping\MetaData\MetaDataExtractor;
use MxcDropshipIntegrator\Mapping\ProductMapper;
use MxcDropshipIntegrator\Mapping\Pullback\DescriptionPullback;
use MxcDropshipIntegrator\Mapping\Shopware\AssociatedArticlesMapper;
use MxcDropshipIntegrator\Mapping\Shopware\CategoryMapper as ShopwareCategoryMapper;
use MxcDropshipIntegrator\Mapping\Shopware\ImageMapper;
use MxcDropshipIntegrator\Mapping\Shopware\PriceEngine;
use MxcDropshipIntegrator\Mapping\Shopware\PriceMapper;
use MxcDropshipIntegrator\Models\Product;
use MxcDropshipIntegrator\Models\Variant;
use MxcDropshipIntegrator\MxcDropshipIntegrator;
use MxcCommons\Toolbox\Report\ArrayReport;
use Shopware\Components\Api\Resource\Article as ArticleResource;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Customer\Customer;
use MxcCommons\Plugin\Mail\MailTemplateManager;
use MxcDropshipInnocigs\Exception\ApiException;
use MxcDropshipIntegrator\Mapping\ImportClient;
use MxcDropship\MxcDropship;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class Shopware_Controllers_Backend_MxcDsiProduct extends BackendApplicationController implements CSRFWhitelistAware
{
    protected $model = Product::class;
    protected $alias = 'product';

    protected $emailTemplatesFile = __DIR__ . '/../../Config/AllMailTemplates.config.php.bak';

    public function getWhitelistedCSRFActions()
    {
        return [
            'excelExportPrices',
            'excelExportPriceIssues',
            'excelExportEcigMetaData',
            'csvExportCustomers',
            'csvExportPayingCustomers',
            'arrayExportDocumentationTodos',
            'zipExportSupplierLogos',
        ];
    }

    public function removeObsoleteProductsAction()
    {
        try {
            $this->removeDeletedProducts();
            $count = $this->removeDeletedArticles();
            $message = $count > 0 ? 'Successfully removed ' . $count . ' outdated products' : 'No outdated products found.';
            $this->view->assign([ 'success' => true, 'message' => $message ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function importAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $params = $this->request->getParams();
            $sequential = $params['sequential'] == 1;

            /** @var ImportClient $client */
            $client = $services->get(ImportClient::class);
            /** @var ImportMapper $mapper */
            $mapper = $services->get(ImportMapper::class);
            $mapper->import($client->importFromApi(true, $sequential));
            $this->view->assign(['success' => true, 'message' => 'Products were successfully updated.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function updatePricesAction()
    {
        try {
            $message = [
                true => 'Prices were successfully updated',
                false => 'Failed to update prices. See Dropship Log.',
            ];
            /** @var DropshipManager $dropshipManager */
            $dropshipManager = MxcDropship::getServices()->get(DropshipManager::class);
            $result = $dropshipManager->updatePrices();
            $this->view->assign(['success' => $result, 'message' => $message[$result]]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function updateVatAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            /** @var PriceMapper $priceMapper */
            $priceMapper = $services->get(PriceMapper::class);
            $priceMapper->updateVat();
            $this->view->assign(['success' => true, 'message' => 'Vat settings successfully updated.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function refreshAction() {
        try {
            $modelManager = $this->getModelManager();
            /** @noinspection PhpUndefinedMethodInspection */
            $modelManager->getRepository(Product::class)->refreshProductStates();
            $this->view->assign([ 'success' => true, 'message' => 'Product links were successfully updated.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    public function updateAssociatedProductsAction()
    {
        try {
            $modelManager = $this->getManager();
            /** @noinspection PhpUndefinedMethodInspection */
            $associatedProducts = $modelManager->getRepository(Product::class)->getLinkedProductIds();
            $services = MxcDropshipIntegrator::getServices();
            $productMapper = $services->get(ProductMapper::class);
            $productMapper->updateAssociatedProducts($associatedProducts);
            $modelManager->flush();

            $this->view->assign([ 'success' => true, 'message' => 'Associated products were successfully updated.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function updateImagesAction()
    {
        try {
            $modelManager = $this->getManager();
            $services = MxcDropshipIntegrator::getServices();
            $imageMapper = $services->get(ImageMapper::class);
            /** @noinspection PhpUndefinedMethodInspection */
            $products = $this->getSelectedProducts($this->request);
            foreach ($products as $product) {
                $imageMapper->setArticleImages($product);
            }
            $modelManager->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Images were successfully updated.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function computeCategories($products)
    {
        $services = MxcDropshipIntegrator::getServices();
        $categoryMapper = $services->get(CategoryMapper::class);
        /** @var Product $product */
        foreach ($products as $product) {
            $categoryMapper->remap($product);
        }
        $categoryMapper->report();
        $this->getManager()->flush();
    }

    protected function remapCategories($products)
    {
        $services = MxcDropshipIntegrator::getServices();

        $categoryMapper = $services->get(ShopwareCategoryMapper::class);
        //$categoryMapper->removeEmptyProductCategories();
        // $this->getManager()->flush();

        /** @var ShopwareCategoryMapper $categoryMapper */
        foreach ($products as $product) {
            $article = $product->getArticle();
            if ($article === null) continue;
            $article->getCategories()->clear();
            $categoryMapper->map($product, true);
        }
        $this->getManager()->flush();
    }

    public function computeCategoriesAction() {
        try {
            $products = $this->getSelectedProducts($this->request);
            // recompute the category attribute in our products
            $this->computeCategories($products);

            $this->view->assign([ 'success' => true, 'message' => 'Categories were successfully recalculated.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function remapCategoriesAction()
    {
        try {
            $products = $this->getSelectedProducts($this->request);
            // recompute the category attribute in our products
            $this->computeCategories($products);
            // assign computed categories to articles
            $this->remapCategories($products);

            $this->view->assign([ 'success' => true, 'message' => 'Categories were successfully remapped.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function removeEmptyCategoriesAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $mapper = $services->get(ShopwareCategoryMapper::class);
            $count = $mapper->removeEmptyProductCategories();
            switch ($count) {
                case 0: $message = 'No empty categories found.'; break;
                case 1: $message = 'One empty category was successfully removed.'; break;
                default: $message = $count . ' empty categories were successfully removed.'; break;
            }
            $this->view->assign([ 'success' => true, 'message' => $message]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function setLastStockAction()
    {
        try {
            $manager = $this->getManager();
            $articles = $manager->getRepository(Article::class)->findAll();
            /** @var Article $article */
            foreach ($articles as $article) {
                if (method_exists($article, 'setLastStock')) {
                    $article->setLastStock(1);
                }
                $details = $article->getDetails();
                /** @var Detail $detail */
                foreach ($details as $detail) {
                    $detail->setLastStock(1);
                }
            }
            $manager->flush();
            $this->view->assign(['success' => true, 'Laststock successfully globally set.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function buildCategoryTreeAction() {
        try {
            $this->view->assign([ 'success' => false, 'message' => 'Function was removed.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }

    }

    public function exportConfigAction()
    {
        try {
            $modelManager = $this->getModelManager();
            $modelManager->getRepository(Product::class)->exportMappedProperties();
            $modelManager->getRepository(Variant::class)->exportMappedProperties();
            $this->view->assign([ 'success' => true, 'message' => 'Product configuration was successfully exported.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function checkMissingModelsAction()
    {
        // find variants which do not have an associated model and log the numbers
        try {
            $modelManager = $this->getModelManager();
            $log = MxcDropshipIntegrator::getServices()->get('logger');
            $variants = $modelManager->getRepository(Variant::class)->getAllIndexed();
            $models = $modelManager->getRepository(Model::class)->getAllIndexed();
            $missingModels = array_keys(array_diff_key($variants, $models));
            if (empty($missingModels)) {
                $log->debug('No missing models.');
            } else {
                $log->debug('Start of missing models log.');
                foreach ($missingModels as $missingModel) {
                    $log->debug('Model missing for variant ' . $missingModel);
                }
                $log->debug('End of missing models log.');
            }

            $this->view->assign([ 'success' => true, 'message' => 'Missing models logged.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function excelExportPricesAction() {
        $this->doExcelExport(['Prices']);
    }

    public function excelExportPriceIssuesAction() {
        $this->doExcelExport(['Price Issues']);
    }

    public function excelExportEcigMetaDataAction() {
        $this->doExcelExport(['Ecig Meta Data']);
    }

    protected function doExcelExport(array $options)
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $excel = $services->build(ExcelExport::class, $options);
            $excel->export();

            $filepath = $excel->getExcelFile();
            $batchfile = file_get_contents($filepath);
            $size = filesize($filepath);
            $this->exportFile(
                'vapee.export.xlsx',
                $size,
                $batchfile,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function exportFile(string $filename, int $size, $content, $contentType) {
        $this->get('plugins')->Controller()->ViewRenderer()->setNoRender();
        $this->Front()->Plugins()->Json()->setRenderer(false);

        $response = $this->Response();
        $response->setHeader('Cache-Control', 'must-revalidate');
        $response->setHeader('Content-Description', 'File Transfer');
        $response->setHeader('Content-disposition', 'attachment; filename=' . $filename);
        $response->setHeader('Content-type', $contentType);
        $response->setHeader('Content-Transfer-Encoding', 'binary');
        $response->setHeader('Content-Length', $size);
        $response->setHeader('Pragma', 'public');

        echo $content;
    }

    public function excelImportPricesAction()
    {
        $this->excelImportSheet('Preise');
    }

    protected function excelImportSheet(string $sheet)
    {
        // Try to get the transferred file
        try {
            $file = $_FILES['file'];
            $services = MxcDropshipIntegrator::getServices();
            $log = $services->get('logger');

            if ($file === null) $log->debug('file is null');
            $fileName = $file['name'];
            $tmpName = $_FILES['file']['tmp_name'];
            $fileNamePos= strrpos ($tmpName, '/');
            $tmpPath= substr($tmpName, 0, $fileNamePos);
            $newFilePath = $tmpPath.'/' . $fileName; //'/../Config/' . $file['originalName'];
            move_uploaded_file($tmpName, $newFilePath);

            $services = MxcDropshipIntegrator::getServices();
            $excel = $services->get(ExcelProductImport::class);
            $result = $excel->importSheet($sheet, $newFilePath);

            unlink($newFilePath);
            if ($result){
                $msg = [
                    'Preise'        => 'Prices',
                    'Dosierung'     => 'Dosages',
                    'Geschmack'     => 'Flavours',
                    'Beschreibung'  => 'Descriptions',
                    'Mapping'       => 'Mappings',
                ];
                $this->view->assign([ 'success' => $result, 'message' => $msg[$sheet] . ' successfully imported from ' . $fileName . '.' ]);
            }else{
                $this->view->assign([ 'success' => $result, 'message' => 'File ' . $fileName . ' could not be imported.' ]);
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function setStatePropertyOnSelected()
    {
        $params = $this->request->getParams();
        $property = $params['field'];
        $value = $params['value'] === 'true';
        $ids = json_decode($params['ids'], true);
        $this->getRepository()->setStateByIds($property, $value, $ids);
        $products = $this->getRepository()->getProductsByIds($ids);
        return [$value, $products];
    }

    public function relinkProductsAction() {
        try {
            $params = $this->request->getParams();
            $ids = json_decode($params['ids'], true);
            $repository = $this->getRepository();
            $repository->setStateByIds('linked', false, $ids);
            $products = $this->getRepository()->getProductsByIds($ids);

            $services = MxcDropshipIntegrator::getServices();
            /** @var ProductMapper $productMapper */
            $productMapper = $services->get(ProductMapper::class);
            $productMapper->deleteArticles($products);

            $repository->setStateByIds('linked', true, $ids);
            $productMapper->controllerUpdateArticles($products, true);

            $this->getRepository()->refreshProductStates();
            $this->view->assign(['success' => true, 'message' => 'Articles were successfully recreated.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function linkSelectedProductsAction()
    {
        try {
            [$value, $products] = $this->setStatePropertyOnSelected();
            $services = MxcDropshipIntegrator::getServices();
            /** @var ProductMapper $productMapper */
            $productMapper = $services->get(ProductMapper::class);
            switch ($value) {
                case true:
                    $productMapper->controllerUpdateArticles($products, true);
                    $message = 'Products were successfully created.';
                    break;
                case false:
                    $productMapper->deleteArticles($products);
                    $message = 'Products were successfully deleted.';
                    break;
                default:
                    $message = 'Nothing done.';
            }
            /** @noinspection PhpUndefinedMethodInspection */
            $this->getRepository()->refreshProductStates();
            $this->view->assign(['success' => true, 'message' => $message]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function acceptSelectedProductsAction() {
        try {
            [$value, $products] = $this->setStatePropertyOnSelected();
            $services = MxcDropshipIntegrator::getServices();
            $productMapper = $services->get(ProductMapper::class);
            switch ($value) {
                case true:
                    $productMapper->controllerUpdateArticles($products, false);
                    $message = 'Product states were successfully set to accepted.';
                    break;
                case false:
                    $productMapper->deleteArticles($products);
                    $message = 'Product states were successfully set to ignored '
                        . 'and associated Shopware products were deleted.';
                    break;
                default:
                    $message = 'Nothing done.';
            }
            /** @noinspection PhpUndefinedMethodInspection */
            $this->getRepository()->refreshProductStates();
            $this->view->assign(['success' => true, 'message' => $message]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function activateSelectedProductsAction()
    {
        try {
            [$value, $products] = $this->setStatePropertyOnSelected();
            $services = MxcDropshipIntegrator::getServices();
            $productMapper = $services->get(ProductMapper::class);
            switch ($value) {
                case true:
                    $productMapper->controllerActivateArticles($products, true, true);
                    $message = 'Shopware products were successfully created and activated';
                    break;
                case false:
                    $productMapper->controllerActivateArticles($products, false, false);
                    $message = 'Shopware products were successfully deactivated.';
                    break;
                default:
                    $message = 'Nothing done.';
            }
            /** @noinspection PhpUndefinedMethodInspection */
            $this->getRepository()->refreshProductStates();
            $this->view->assign(['success' => true, 'message' => $message]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function checkRegularExpressionsAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $regularExpressions = $services->get(RegularExpressions::class);
            if (! $regularExpressions->check()) {
                $this->view->assign(['success' => false, 'message' => 'Errors found in regular expressions. See log for details.']);
            } else {
                $this->view->assign(['success' => true, 'message' => 'No errors found in regular expressions.']);
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function checkNameMappingConsistencyAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $nameMappingConsistency = $services->get(NameMappingConsistency::class);
            $issueCount = $nameMappingConsistency->check();
            if ($issueCount > 0) {
                $issue = 'issue';
                if ($issueCount > 1) $issue .= 's';
                $this->view->assign(['success' => false, 'message' => 'Found ' . $issueCount . ' name mapping ' . $issue  . '. See log for details.']);
            } else {
                $this->view->assign(['success' => true, 'message' => 'No name mapping issues found.']);
            }

        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function checkVariantMappingConsistencyAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $variantMappingConsistency = $services->get(VariantMappingConsistency::class);
            $issueCount = $variantMappingConsistency->check();
            if ($issueCount > 0) {
                $issue = 'issue';
                if ($issueCount > 1) $issue .= 's';
                $this->view->assign(['success' => false, 'message' => 'Found ' . $issueCount . ' variant mapping ' . $issue  . '. See log for details.']);
            } else {
                $this->view->assign(['success' => true, 'message' => 'No variant mapping issues found.']);
            }

        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function createAllAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $productMapper = $services->get(ProductMapper::class);
            $products = $this->getRepository()->findAll();
            foreach ($products as $product) {
                $productMapper->createArticle($product);
            }
            $message = 'Products were successfully created.';
            $this->view->assign(['success' => true, 'message' => $message]);
            /** @noinspection PhpUndefinedMethodInspection */
            $this->getRepository()->refreshProductStates();
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function deleteAllAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $productMapper = $services->get(ProductMapper::class);
            /** @noinspection PhpUndefinedMethodInspection */
            $products = $this->getRepository()->getLinkedProducts();
            $productMapper->deleteArticles($products);
            $message = 'Products were successfully deleted.';
            $this->view->assign(['success' => true, 'message' => $message]);
            /** @noinspection PhpUndefinedMethodInspection */
            $this->getRepository()->refreshProductStates();
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function remapAction()
    {
        try {
            /** @var ImportMapper $client */
            $services = MxcDropshipIntegrator::getServices();
            $propertyMapper = $services->get(PropertyMapper::class);
            $categoryMapper = $services->get(CategoryMapper::class);
            /** @var ProductMapper $productMapper */
            $productMapper = $services->get(ProductMapper::class);
            $repository = $this->getModelManager()->getRepository(Product::class);

            /** @noinspection PhpUndefinedMethodInspection */
            $products = $repository->getAllIndexed();
            $propertyMapper->mapProperties($products, true);
            $categoryMapper->buildCategoryTree();

            /** @noinspection PhpUndefinedMethodInspection */
            $products = $repository->getLinkedProducts();
            $productMapper->updateArticles($products, false);
            $this->getModelManager()->flush();

            $this->view->assign([ 'success' => true, 'message' => 'Product properties were successfully remapped.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function remapSelectedAction() {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $propertyMapper = $services->get(PropertyMapper::class);
            $productMapper = $services->get(ProductMapper::class);
            $modelManager = $this->getManager();
            $repository = $modelManager->getRepository(Product::class);

            $params = $this->request->getParams();
            $ids = json_decode($params['ids'], true);

            $products = $repository->getProductsByIds($ids);
            $propertyMapper->mapProperties($products, true);
            $modelManager->flush();

            $products = $repository->getLinkedProductsFromProductIds($ids);
            $productMapper->updateArticles($products, false);
            $modelManager->flush();

            $this->view->assign([ 'success' => true, 'message' => 'Product properties were successfully remapped.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function updateStockInfoAction()
    {
        try {
            $message = [
                true => 'Stock data was successfully updated.',
                false => 'Failed to update stock data. See Dropship Log.',
            ];
            /** @var DropshipManager $dropshipManager */
            $dropshipManager = MxcDropship::getServices()->get(DropshipManager::class);
            $result = $dropshipManager->updateStock();
            $this->view->assign(['success' => $result, 'message' => $message[$result]]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }

    }

    protected function getSelectedProducts(Enlight_Controller_Request_RequestHttp $request) {
        $params = $request->getParams();
        $repository = $this->getManager()->getRepository(Product::class);
        if (empty($params['ids'])) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $repository->getAllIndexed();
        }
        $ids = json_decode($params['ids'], true);
        return $repository->getProductsByIds($ids);
    }

    public function remapDescriptionsAction() {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $mapper = $services->get(DescriptionMapper::class);

            $products = $this->getSelectedProducts($this->request);

            /** @var Product $product */
            foreach ($products as $product) {
                $mapper->remap($product);
                $article = $product->getArticle();
                /** @var Article $article */
                if ($article !== null) {
                    $description = $product->getDescription();
                    $article->setDescriptionLong($description);
                }
            }
            $this->getManager()->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Descriptions successfully remapped.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function pullCategorySeoInformationAction()
    {
        try {
            PullCategorySeoInformation::run();
            $this->view->assign([ 'success' => true, 'message' => 'Category SEO information saved.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function pullShopwareDescriptionsAction()
    {
        try {
            /** @var ImportMapper $client */
            $services = MxcDropshipIntegrator::getServices();
            $descriptions = $services->get(DescriptionPullback::class);
            $repository = $this->getManager()->getRepository(Product::class);

            $products = $this->getSelectedProducts($this->request);
            $descriptions->pullDescriptions($products);
            $repository->exportMappedProperties();

            $this->view->assign([ 'success' => true, 'message' => 'Successfully pulled Shopware descriptions.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function testImport1Action()
    {
        try {
            $testDir = __DIR__ . '/../../Test/';
            $xmlFile = $testDir . 'TESTErstimport.xml';
            $this->deleteShopwareArticles();
            $this->testImportFromFile($xmlFile, true);
            $this->view->assign([ 'success' => true, 'message' => 'Erstimport successful.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function testImport2Action()
    {
        try {
            $testDir = __DIR__ . '/../../Test/';
            $xmlFile = $testDir . 'TESTUpdateFeldwerte.xml';
            $this->testImportFromFile($xmlFile, false);
            $this->view->assign([ 'success' => true, 'message' => 'Values successfully updated.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function testImport3Action()
    {
        try {
            $testDir = __DIR__ . '/../../Test/';
            $xmlFile = $testDir . 'TESTUpdateVarianten.xml';
            $this->testImportFromFile($xmlFile, false);
            $this->view->assign([ 'success' => true, 'message' => 'Variants successfully updated.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function testImport4Action()
    {
        try {
            $xml = '<?xml version="1.0" encoding="utf-8"?><INNOCIGS_API_RESPONSE><PRODUCTS></PRODUCTS></INNOCIGS_API_RESPONSE>';
            $services = MxcDropshipIntegrator::getServices();
            /** @var ImportClient $client */
            $client = $services->get(ImportClient::class);
            $mapper = $services->get(ImportMapper::class);
            $mapper->import($client->importFromXml($xml, true, false));
            $this->view->assign([ 'success' => true, 'message' => 'Empty list successfully imported.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function testImport5Action()
    {
        try {
            $testDir = __DIR__ . '/../../Test/';
            $xmlFile = $testDir . 'TESTHugeImport.xml';
            $this->deleteShopwareArticles();
            $this->testImportFromFile($xmlFile, true);

            $services = MxcDropshipIntegrator::getServices();
            $products = $this->getManager()->getRepository(Product::class)->findAll();
            $productMapper = $services->get(ProductMapper::class);
            $productMapper->updateArticles($products, true);

            $this->view->assign([ 'success' => true, 'message' => 'Erstimport successful.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function testImport6Action()
    {
        try {
            $testDir = __DIR__ . '/../../Test/';
            $xmlFile = $testDir . 'TESTHugeImport.xml';
            $this->deleteShopwareArticles();
            $this->testImportFromFile($xmlFile, true);
            $services = MxcDropshipIntegrator::getServices();

            $products = $this->getManager()->getRepository(Product::class)->findAll();
            $productMapper = $services->get(ProductMapper::class);
            $productMapper->updateArticles($products, true);

            $this->view->assign([ 'success' => true, 'message' => 'Erstimport successful.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
   }

    public function checkInactiveVariantsAction()
    {
        try {
            $products = $this->getManager()->getRepository(Product::class)->findAll();
            /** @var Product $product */
            $log = MxcDropshipIntegrator::getServices()->get('logger');
            foreach ($products as $product) {
                $variants = $product->getVariants();
                /** @var Variant $variant */
                foreach ($variants as $variant) {
                    if ($variant->isValid() && $variant->getDetail() === null) {
                        $log->debug('Product with inactive variant: '. $product->getName());
                    }
                }
            }
            $this->view->assign([ 'success' => true, 'message' => 'Products with inactive variants logged.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function remapProductSeoInformationAction() {
        try {
            $products = $this->getManager()->getRepository(Product::class)->findall();
            /** @var Product $product */
            foreach ($products as $product) {
                /** @var Article $article */
                $article = $product->getArticle();
                if ($article === null) continue;
                $article->setDescription($product->getSeoDescription());
                $article->setMetaTitle($product->getSeoTitle());
                $article->setKeywords($product->getSeoKeywords());
                $seoUrl = $product->getSeoUrl();
                if (! empty($seoUrl)) {
                    ArticleTool::setArticleAttribute($article, 'attr4', $seoUrl);
                }
            }
            $this->getManager()->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Product SEO information was successfully refreshed.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    // Berechnet nach den implementierten Heuristiken die Zubehör- und ähnlichen Produkte
    // Shopware Artikel werden nicht angepasst.
    public function computeAssociatedProductsAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            /** @var AssociatedProductsMapper $mapper */
            $mapper = $services->get(AssociatedProductsMapper::class);
            $manager = $this->getManager();
            $products = $manager->getRepository(Product::class)->getAllIndexed();
            $mapper->map($products);
            $manager->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Associated products successfully computed.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function pullAssociatedProductsAction()
    {
        try {
            $articles = $this->getManager()->getRepository(Article::class)->findAll();
            $repo = $this->getManager()->getRepository(Product::class);

            $fileNameShort = 'Config/CrossSelling.config.phpx';
            $fileName = __DIR__ . '/../../' . $fileNameShort;
            /** @noinspection PhpIncludeInspection */
            $relations = include $fileName;
            $similarArticles = $relations['similar'];
            $relatedArticles = $relations['related'];

            /** @var Article $article */
            $relations = [];
            foreach ($articles as $article) {
                /** @var Product $product */
                $product = $repo->getProduct($article);
                if ($product === null) continue;
                $number = $product->getIcNumber();
                $related = $article->getRelated();
                $pool = [];
                foreach ($related as $relatedArticle) {
                    $product = $repo->getProduct($relatedArticle);
                    if ($product === null) continue;
                    $pool[] = $product->getIcNumber();
                }
                if (! empty($pool)) {
                    $relatedArticles[$number] = $pool;
                }

                $similar = $article->getSimilar();
                $pool = [];
                foreach ($similar as $similarArticle) {
                    $product = $repo->getProduct($similarArticle);
                    if ($product === null) continue;
                    $pool[] = $product->getIcNumber();
                }
                if (! empty($pool)) {
                    $similarArticles[$number] = $pool;
                }
            }
            $relations['related'] = $relatedArticles;
            $relations['similar'] = $similarArticles;
            Config::toFile($fileName, $relations);
            $this->view->assign([ 'success' => true, 'message' => 'Cross selling products saved to ' . $fileNameShort . '.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function pushAssociatedProductsAction()
    {
        try {
            $repository = $this->getManager()->getRepository(Product::class);
            $fileNameShort = 'Config/CrossSelling.config.phpx';
            /** @noinspection PhpIncludeInspection */
            $relations = include __DIR__ . '/../../' . $fileNameShort;
            $similar = $relations['similar'];
            foreach ($similar as $number => $similarList) {
                $product = $repository->findOneBy(['icNumber' => $number]);
                if (! $product) continue;
                /** @var Article $article */
                $article = $product->getArticle();
                if (! $article) continue;
                $similarArticles = $article->getSimilar();
                $similarArticles->clear();
                foreach ($similarList as $similarArticleNumber) {
                    $product = $repository->findOneBy(['icNumber' => $similarArticleNumber]);
                    if (! $product) continue;
                    $article = $product->getArticle();
                    if (! $article) continue;
                    $similarArticles->add($article);
                }
            }

            $related = $relations['related'];
            foreach ($related as $number => $relatedList) {
                $product = $repository->findOneBy(['icNumber' => $number]);
                if (! $product) continue;
                /** @var Article $article */
                $article = $product->getArticle();
                if (! $article) continue;
                $relatedArticles = $article->getRelated();
                $relatedArticles->clear();
                foreach ($relatedList as $relatedArticleNumber) {
                    $product = $repository->findOneBy(['icNumber' => $relatedArticleNumber]);
                    if (! $product) continue;
                    $article = $product->getArticle();
                    if (! $article) continue;
                    $relatedArticles->add($article);
                }
            }
            $this->getManager()->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Cross selling products restored from ' . $fileNameShort . '.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function remapCategorySeoInformationAction() {
        try {
            // create category seo information for InnoCigs products
            $services = MxcDropshipIntegrator::getServices();
            $seoCategoryMapper = $services->get(CategoryMapper::class);
            $products = $this->getManager()->getRepository(Product::class)->findAll();
            $model = new Model();
            foreach ($products as $product) {
                $seoCategoryMapper->map($model, $product, true);
            }

            // update Shopware articles
            $seoCategoryMapper->report();
            $categoryMapper = $services->get(ShopwareCategoryMapper::class);
            $categoryMapper->rebuildCategorySeoInformation();
            $this->getManager()->flush();

            $this->view->assign([ 'success' => true, 'message' => 'Category SEO information successfully refreshed.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function setReferencePricesAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $priceMapper = $services->get(PriceMapper::class);
            $products = $this->getManager()->getRepository(Product::class)->findAll();
            foreach ($products as $product) {
                $priceMapper->setReferencePrice($product);
            }
            $this->getManager()->flush();

            $this->view->assign([ 'success' => true, 'message' => 'Successfully set reference prices.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function checkSupplierLogoAction()
    {
        try {
            $missingLogos = [];
            $suppliers = $this->getManager()->getRepository(Supplier::class)->findBy(array('image' => ''), array('name' => 'ASC'));
            foreach ($suppliers as $supplier) {
                array_push($missingLogos, $supplier->getName());
            }
            $this->view->assign([ 'success' => true, 'message' => 'The following Logos are missing:', 'value' => $missingLogos ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function remapSupplierSeoInformationAction()
    {
        try {
            $suppliers = $this->getManager()->getRepository(Supplier::class)->findAll();
            /** @var Supplier $supplier */
            $title = 'E-Zigaretten: Unsere Produkte von %s';
            $description = 'Produkte für Dampfer von %s ✓ vapee.de bietet ein breites Sortiment von E-Zigaretten und E-Liquids zu fairen Preisen ► Besuchen Sie uns!';

            foreach ($suppliers as $supplier) {
                $name = $supplier->getName();
                $metaTitle = sprintf($title, $name);
                $metaDescription = sprintf($description, $name);
                SupplierTool::setSupplierMetaInfo($supplier, $metaTitle, $metaDescription, $name);
            }
            $this->getManager()->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Supplier meta information successfully applied.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function rebuildProductSeoInformationAction() {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $log = $services->get('logger');
            $seoMapper = $services->get(ProductSeoMapper::class);
            $repository = $this->getManager()->getRepository(Product::class);
            $products = $repository->getAllIndexed();
            $model = new Model();
            $seoUrls = [];
            /** @var Product $product */
            foreach ($products as $product) {
                $seoMapper->map($model, $product);
                $seoUrls[$product->getIcNumber()] = $product->getSeoUrl();
            }
            $this->getManager()->flush();
            ksort($seoUrls);
            $report = new ArrayReport();
            $report(['icSeoUrls' => $seoUrls]);

            $this->view->assign([ 'success' => true, 'message' => 'Product SEO information successfully rebuilt.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }

    }

    public function applyPriceRulesAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            /** @var PriceEngine $priceEngine */
            $priceEngine = $services->get(PriceEngine::class);
            /** @var PriceMapper $priceMapper */
            $priceMapper = $services->get(PriceMapper::class);
            $products = $this->getManager()->getRepository(Product::class)->findAll();
            /** @var Product $product */
            foreach ($products as $product) {
                $variants = $product->getVariants();
                /** @var Variant $variant */
                foreach ($variants as $variant) {
                    $correctedPrices = $priceEngine->getCorrectedRetailPrices($variant);
                    $priceEngine->setRetailPrices($variant, $correctedPrices);
                    $priceMapper->setPrices($variant);
                }
            }
            $this->getManager()->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Corrected prices were applied.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function updateSchemaAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $schemaManager = $services->get(SchemaManager::class);
            $schemaManager->updateSchema();
            $this->view->assign([ 'success' => true, 'message' => 'Database schema was updated.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function exportCustomers($customers)
    {
        $salutationMap = [
            'mr' => 'Herr',
            'ms' => 'Frau',
        ];

        /** @var Customer $customer */
        $lines[] = implode(';', [ 'Salutation','First Name','Last Name','eMail' ]);

        foreach ($customers as $customer) {
            $firstname = $customer->getFirstname();
            $firstname = iconv('UTF-8', 'WINDOWS-1252', $firstname);
            $lastname = $customer->getLastname();
            $lastname = iconv('UTF-8', 'WINDOWS-1252', $lastname);
            $salutation = $salutationMap[$customer->getSalutation()];
            $email = $customer->getEmail();
            $lines[] = implode(';', [$salutation, $firstname, $lastname, $email]);
        }
        $lines = implode("\n", $lines);
        $this->exportFile(
            'vapee.customers.csv',
            strlen($lines),
            $lines,
            'application/octet-stream'
        );
    }

    public function csvExportPayingCustomersAction()
    {
        try {
            $db = Shopware()->Db();
            $manager = $this->getManager();
            $log = MxcDropshipInnocigs::getServices()->get('logger');

            $dql = 'SELECT DISTINCT c FROM Shopware\Models\Customer\Customer c 
                   LEFT JOIN Shopware\Models\Order\Order o WITH o.customer = c.id
                   WHERE o.paymentStatus = 12';

            $query = $this->getManager()->createQuery($dql);
            $this->exportCustomers($query->getResult());
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function csvExportCustomersAction()
    {
        try {
            $manager = $this->getManager();
            $customers = $manager->getRepository(Customer::class)->findAll();
            $this->exportCustomers($customers);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function zipExportSupplierLogosAction()
    {
        $log = MxcDropshipIntegrator::getServices()->get('logger');
        $filePath = $this->zipSupplierLogos();
        $batchfile = file_get_contents($filePath);
        $size = filesize($filePath);
        $log->debug('Zip export: ' . $size);
        $this->exportFile('vapeeSupplierLogos.zip', $size, $batchfile, 'application/zip');
    }

    public function arrayExportDocumentationTodosAction()
    {
        try {

            $types = [
                'E_CIGARETTE',
                'POD_SYSTEM',
                'BOX_MOD',
                'BOX_MOD_CELL',
                'SQUONKER_BOX',
                'CLEAROMIZER',
                'CLEAROMIZER_ADA',
                'CLEAROMIZER_RTA',
                'CLEAROMIZER_RDA',
                'CLEAROMIZER_RDTA',
            ];

            $products = $this->getManager()->getRepository(Product::class)->findAll();
            /** @var Product $product */
            foreach ($products as $product) {
                $type = $product->getType();
                if (! in_array($type, $types)) continue;
                /** @var Shopware\Models\Article\Article $article */
                $article = $product->getArticle();
                if ($article === null) continue;
                $description = $article->getDescriptionLong();
                if (strpos($description, '<tbody>') !== false) continue;
                $productsToDo[$type][$product->getIcNumber()] = $product->getName();
            }
            foreach ($products as $product) {
                $article = $product->getArticle();
                if ($article === null) continue;
                $description = $article->getDescriptionLong();
                if (! empty($description)) continue;
                $productsTodo['_DESCRIPTION_EMPTY'][$product->getIcNumber()] = $product->getName();
            }
            foreach ($types as $type) {
                if (isset($productsToDo[$type]))
                    asort($productsToDo[$type]);
            }
            ksort($productsToDo);
            $file = var_export($productsToDo, true);
            $this->exportFile(
                'vapee.documentation.todos.txt',
                strlen($file),
                $file,
                'application/octet-stream'
            );

        } catch (Throwable $e) {
            $this->handleException($e);
        }

    }

    public function updateAssociatedLiquidsAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            /** @var AssociatedArticlesMapper $mapper */
            $mapper = $services->get(AssociatedArticlesMapper::class);
            $manager = $this->getManager();
            $products = $manager->getRepository(Product::class)->getAllIndexed();
            /** @var Product $product */
            foreach ($products as $product) {
                $type = $product->getType();
                if ($type != 'LQIUID' && $type != 'SHAKE_VAPE' && $type != 'AROMA') continue;
                $mapper->setRelatedArticles($product);
                $mapper->setSimilarArticles($product);
            }
            $manager->flush();
            $this->view->assign([ 'success' => true, 'message' => 'Similar and related articles set for liquids etc.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function saveEmailTemplatesAction()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            /** @var MailTemplateManager $mailManager */
            $mailManager = $services->get(MailTemplateManager::class);
            $templates = $mailManager->getMailTemplates(true);
            Config::toFile($this->emailTemplatesFile, $templates);
            $file = basename($this->emailTemplatesFile);
            $this->view->assign([
                'success' => true,
                'message' => 'Email templates successfully saved to.' . $file . '.',
            ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function restoreEmailTemplatesAction()
    {
        try {
            $fn = basename($this->emailTemplatesFile);
            if (! file_exists($this->emailTemplatesFile)) {
                $this->view->assign([ 'success' => false, 'message' => 'File ' . $fn . ' does not exist.']);
                return;
            }

            $services = MxcDropshipIntegrator::getServices();
            $mailManager = $services->get(MailTemplateManager::class);
            /** @var MailTemplateManager $mailManager */
            $templates = include $this->emailTemplatesFile;
            $mailManager->setMailTemplates($templates);

            $this->view->assign([
                'success' => true,
                'message' => 'Email templates successfully restored from ' . $fn . '.',
            ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function checkArticlesWithoutProductsAction()
    {
        try {
            $articles = $this->getManager()->getRepository(Article::class)->findAll();
            $repository = $this->getManager()->getRepository(Product::class);
            $list1 = [];
            foreach ($articles as $article) {
                $product = $repository->getProduct($article);
                if ($product === null) {
                    $list1[] = $article->getName();
                }
            }
            $articles = $repository->getArticlesWithoutProduct();
            $list2 = [];
            foreach ($articles as $article) {
                $list2[] = $article->getName();
            }
            $list = [
                'by repository->getProduct' => $list1,
                'by repositpory->getArticlesWithoutProduct' => $list2,
            ];
            $report = new ArrayReport();
            $report(['ckArticlesWithoutProduct' => $list]);

            $this->view->assign([ 'success' => true, 'message' => 'Articles without product logged to ckArticlesWithoutProducts.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function enableDropshipAction()
    {
        try {
            $variants = $this->getManager()->getRepository(Variant::class)->findAll();
            $services = MxcDropshipInnocigs::getServices();
            $apiClient = $services->get(ApiClient::class);
            /** @var ArticleRegistry $registry */
            $registry = $services->get(ArticleRegistry::class);
            $stockInfo = $apiClient->getStockInfo();
            /** @var Variant $variant */
            foreach ($variants as $variant) {
                $detail = $variant->getDetail();
                if ($detail === null) continue;
                $instock = $stockInfo[$variant->getIcNumber()] ?? 0;
                $detail->setInstock($instock);
                $registry->configureDropship($variant, $instock);
            }
            $this->view->assign([ 'success' => true, 'message' => 'Dropship for all variants enabled.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function dev1Action()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $log = $services->get('logger');
            $products = $this->getManager()->getRepository(Product::class)->findAll();
            $log->debug ('Found ' . count($products) . ' products.');

            /** @var MetaDataExtractor $metaDataExtractor */
            $metaDataExtractor = $services->get(MetaDataExtractor::class);

            /** @var Product $product */
            foreach ($products as $product) {
                $metaDataExtractor->extractMetaData($product);
            }
            $this->view->assign([ 'success' => true, 'message' => 'Metadata successfully extracted.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function dev2Action()
    {
        try {
            $services = MxcDropshipIntegrator::getServices();
            $log = $services->get('logger');
            /** @var CategoryMapper $categoryMapper */
            $products = $this->getManager()->getRepository(Product::class)->getProductsWithReleaseDate();
            foreach ($products as $product) {
                $log->debug('Release Date: ' . $product->getName());
            }
//            $categoryMapper = $services->get(CategoryMapper::class);
//            $categoryMapper->sortCategories();
            $this->view->assign([ 'success' => true, 'message' => 'Development 2 slot is currently free.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    // Delete all products which are accepted and not active
    protected function cleanupProducts()
    {
        $services = MxcDropshipIntegrator::getServices();
        $modelManager = $services->get('models');
        $products = $modelManager->getRepository(Product::class)->findBy(['active' => 0, 'accepted' => 1]);
        $variantRepository = $modelManager->getRepository(Variant::class);
        foreach ($products as $product) {
            $variants = $product->getVariants();
            foreach ($variants as $variant) {
                $product->removeVariant($variant);
                $variantRepository->removeOptions($variant);
                $modelManager->remove($variant);
            }
            $modelManager->remove($product);
        }
        $modelManager->flush();
    }

    public function setArticleType()
    {
        $log = MxcDropshipIntegrator::getServices()->get('logger');
        $config = include __DIR__ . '/../../Config/ProductMapper.config.php';
        $log->debug('Config: ' . var_export($config, true));

        $products = $this->getManager()->getRepository(Product::class)->findAll();
        foreach ($products as $product) {
            $article = $product->getArticle();
            if ($article === null) continue;
            $type = $product->getType();
            $filterType = $config['filter_product_type'][$type] ?? 'unbekannt';
            $log->debug('Mapping: ' . $type . ' => ' . $filterType);
            ArticleTool::setArticleAttribute($article, 'mxcbc_product_type', $filterType);
        }
    }

    // set flavor on all flavored products
    // this is a workaround because the flavor does not get set at detail creation erronously
    protected function adjustFlavor() {
        $services = MxcDropshipIntegrator::getServices();
        $modelManager = $services->get('models');
        $products = $modelManager->getRepository(Product::class)->findAll();
        /** @var Product $product */
        foreach ($products as $product) {
            $article = $product->getArticle();
            if ($article === null) continue;
            $flavor = $product->getFlavor();
            if (empty($flavor)) continue;
            $flavorFilter = array_map('trim', explode(',', $flavor));
            $flavorFilter = '|' . implode('|', $flavorFilter) . '|';
            ArticleTool::setArticleAttribute($article, 'mxcbc_flavor', $flavor);
            ArticleTool::setArticleAttribute($article, 'mxcbc_flavor_filter', $flavorFilter);
        }
    }

    protected function adjustOwnStockFlavors()
    {
        $db = Shopware()->Db();
        $sql = 'SELECT id, mxcbc_flavor FROM s_articles_attributes WHERE mxcbc_flavor IS NOT NULL AND mxcbc_flavor != \'\' AND mxcbc_flavor_filter IS NULL';
        $details = $db->query($sql);
        foreach ($details as $detail) {
            $flavorFilter = array_map('trim', explode(',', $detail['mxcbc_flavor']));
            $flavorFilter = '|' . implode('|', $flavorFilter) . '|';
            ArticleTool::setDetailAttribute($detail['id'], 'mxcbc_flavor_filter', $flavorFilter);
        }
    }

    protected function setSurmountFlavours()
    {
        $flavors = [
            'Surmount - Alter Tobi' => 'Tabak',
            'Surmount - Marokkan' => 'Orient-Tabak',
            'Surmount - Sherlock' => 'Tabak',
            'Surmount - Vanilla' => 'Vanille',
            'Surmount - Tuscany' => 'Zigarillo',
        ];
        $repository = $this->getManager()->getRepository(Article::class);
        foreach ($flavors as $name => $flavor) {
            /** @var Article $article */
            $article = $repository->findOneBy(['name' => $name]);
            if ($article === null) continue;
            ArticleTool::setArticleAttribute($article, 'mxcbc_flavor', $flavor);
            ArticleTool::setArticleAttribute($article, 'mxcbc_flavor_filter', '|' . $flavor . '|');
        }
    }

    public function setBlackNotePurchasePrice()
    {
        $names = [
            'Black Note - Solo - Liquid - 10 ml',
            'Black Note - Prelude - Liquid - 10 ml',
            'Black Note - Sonata - Liquid - 10 ml',
            'Black Note - Forte - Liquid - 10 ml',
            'Black Note - Legato - Liquid - 10 ml',
            'Black Note - Quartet - Liquid - 10 ml',
        ];
        $log = MxcDropshipIntegrator::getServices()->get('logger');

        $repository = $this->getManager()->getRepository(Article::class);
        foreach ($names as $name) {
            /** @var Article $article */
            $article = $repository->findOneBy(['name' => $name]);
            if ($article === null) continue;
            $log->debug("Modifying price for $name");
            $details = $article->getDetails();
            /** @var Detail $detail */
            foreach ($details as $detail) {
                $detail->setPurchasePrice(4.35);
            }
        }
        $this->getManager()->flush();
    }

    public function removeDeletedProducts()
    {
        $services = MxcDropshipIntegrator::getServices();
        $log = $services->get('logger');
        $modelManager = $services->get('models');
        /** @var ImportMapper $importMapper */
        $importMapper = $services->get(ImportMapper::class);
        $products = $modelManager->getRepository(Product::class)->findAll();
        $models = $modelManager->getRepository(Model::class)->getAllIndexed();
        $deletedProducts = [];
        /** @var Product $product */
        foreach ($products as $product) {

            $variants = $product->getVariants();
            /** @var Variant $variant */
            foreach ($variants as $variant) {
                if (isset($models[$variant->getIcNumber()])) {
                    continue 2;
                }
            }
            $deletedProducts[$product->getName()] = $product;
        }
        $log->debug('Deleted products');
        $list = array_keys($deletedProducts);
        sort($list);

        $log->debug(var_export($list, true));
        foreach ($deletedProducts as $product) {
            $importMapper->removeProduct($product);
        }
        $log->debug('Deleted products removed.');
        $modelManager->flush();
    }

    public function removeDeletedArticles()
    {
        $services = MxcDropshipIntegrator::getServices();
        $log = $services->get('logger');
        $modelManager = $services->get('models');
        $articles = $modelManager->getRepository(Article::class)->findAll();
        $models = $modelManager->getRepository(Model::class)->getAllIndexed();
        $deletedArticles = [];
        /** @var Article $article */
        foreach ($articles as $article) {
            $details = $article->getDetails();
            /** @var Detail $detail */
            foreach ($details as $detail) {
                if (isset($models[$detail->getNumber()])) {
                    continue 2;
                }
            }
            $name = $article->getName();
            if (strpos($name, 'Black Note') === false && strpos($name, 'Surmount') === false) {
                $deletedArticles[$article->getName()] = $article;
            }
        }
        $log->debug('Deleted articles');
        $list = array_keys($deletedArticles);
        sort($list);
        $log->debug(var_export($list, true));
        $ar = new ArticleResource();
        $ar->setManager($modelManager);
        foreach ($deletedArticles as $article) {
            $ar->delete($article->getId());
        }
        $log->debug("Deleted articles removed.");
        return count($deletedArticles);
    }

    public function findArticlesWithoutDetails()
    {
        $services = MxcDropshipIntegrator::getServices();
        $db = $services->get('db');
        $log = $services->get('logger');
        $sql = 'SELECT * FROM s_articles a LEFT JOIN s_articles_details ad ON ad.articleID = a.id WHERE ad.id IS NULL';
        $articles = $db->fetchAll($sql);
        $log->debug('Articles without details:');
        foreach ($articles as $article) {
            $this->log->debug($article['name']);
        }
    }

    public function findUnavailableMainDetails()
    {
        $log = MxcDropshipIntegrator::getServices()->get('logger');
        $unavailableMainDetails = Shopware()->Db()->fetchAll('
            SELECT * FROM s_articles_details d 
            LEFT JOIN s_articles_attributes aa ON aa.articledetailsID = d.id
            WHERE d.kind = 1 AND aa.mxcbc_dsi_ic_registered = 1 AND aa.mxcbc_dsi_ic_instock = 0; 
        ');
        foreach ($unavailableMainDetails as $detail) {
            $variants = Shopware()->Db()->fetchall('
            SELECT * FROM s_articles_details d 
            LEFT JOIN s_articles_attributes aa ON aa.articledetailsID = d.id
            WHERE d.kind = 2 AND d.articleID = ? 
            ', [$detail['articleID']]
            );
            if (empty($variants)) continue;
            $log->debug('Unavailable main detail: '. $detail['mxcbc_dsi_ic_productname']);
        }
    }

    // copies all mxcbc_dsi_ic_instock to articles instock
    protected function importDroshipStock()
    {
        $sql = '
            UPDATE s_articles_details d
            LEFT JOIN s_articles_attributes aa ON d.id = aa.articledetailsID
            SET d.instock = aa.mxcbc_dsi_ic_instock
        ';
        Shopware()->Db()->executeUpdate($sql);
    }

    protected function setupFlavors()
    {
        $products = $this->getManager()->getRepository(Product::class)->findAll();
        /** @var Product $product */
        foreach ($products as $product)
        {
            $flavor = $product->getFlavor();
            if (empty($flavor)) continue;
            $variants = $product->getVariants();
            /** @var Variant $variant */
            foreach ($variants as $variant)
            {
                $detail = $variant->getDetail();
                if ($detail === null) continue;
                ArticleTool::setDetailAttribute($detail, 'mxcbc_flavor', $flavor);
            }
        }
    }

    public function findPodSystemsWithZugautomatik()
    {
        $log = MxcDropshipIntegrator::getServices()->get('logger');
        $products = $this->getManager()->getRepository(Product::class)->findBy(['type' => 'POD_SYSTEM']);
        foreach ($products as $product) {
            if (strpos($product->getDescription(), 'Zugautomatik') !== false) {
                $log->debug('Zugautomatik: '. $product->getName());
            }
        }
    }

    public function findMultipackLiquids()
    {
        $repository = $this->getManager()->getRepository(Product::class);
        $products = $repository->getAllIndexed();
        $log = MxcDropshipIntegrator::getServices()->get('logger');
        $multiPacks = [];

        /** @var Product $product */
        foreach ($products as $product) {
            $type = $product->getType();
            if (! in_array($type, [
                'LIQUID',
                'SHAKE_VAPE',
                'AROMA',
//                'NICSALT_LIQUID',
//                'AROMA',
//                'SHOT',
            ])) continue;

            $variants = $product->getVariants();
            /** @var Variant $variant */
            foreach ($variants as $variant) {
                $options = $variant->getOptions();
                /** @var Option $option */
                foreach ($options as $option) {
                    $group = $option->getIcGroup();
                    if ($group->getName() !== 'Packungsgröße') continue;
                    $optionName = $option->getName();
                    if ($optionName != '1er Packung') {
                        $size = [];
                        preg_match('~(\d+)er Packung~', $optionName, $size);
                        $size = intval($size[1]);
                        if ($size > 20) continue;
                        $multiPacks[$type][$product->getBrand()][] = $optionName;
                    }
                }
            }
        }
        foreach ($multiPacks as $key => $value)
        {
            foreach ($value as $k => $v)
            $multiPacks[$key][$k] = array_unique($v);
            ksort($multiPacks[$key]);
        }
        $log->debug('Liquid Multipacks:');
        $log->debug(var_export($multiPacks, true));
    }

    public function remapAccepted()
    {
        $services = MxcDropshipIntegrator::getServices();
        $productMapper = $services->get(ProductMapper::class);
        /** @var BulkSupportMapper $bulkSupportMapper */
        $bulkSupportMapper = $services->get(BulkSupportMapper::class);
        $repository = $this->getManager()->getRepository(Product::class);
        $products = $repository->getAllIndexed();

        $model = new Model();
        foreach ($products as $product) {
            $bulkSupportMapper->map($model, $product, true);
        }
        $this->getManager()->flush();

//        $ids = [];
//        foreach ($products as $product) {
//            $ids[] = $product->getId();
//        }
//
//        $products = $repository->getLinkedProductsFromProductIds($ids);
//        $productMapper->updateArticles($products, false);
//        $this->getManager()->flush();
    }

    public function resetSinglePacks()
    {
        $services = MxcDropshipIntegrator::getServices();
        $bulkSupportMapper = $services->get(BulkSupportMapper::class);
        $repository = $this->getManager()->getRepository(Product::class);
        $products = $repository->getAllIndexed();

        $model = new Model();
        foreach ($products as $product) {
            $bulkSupportMapper->map2($model, $product, true);
        }
        $this->getManager()->flush();
    }

    public function setLiguidBulkPrice()
    {
        $repository = $this->getManager()->getRepository(Product::class);

        $vatFactor = 1 + TaxTool::getCurrentVatPercentage() / 100;
        $bulkPrices = [
            'LIQUID' => [
                [
                    'brand' => 'SC',
                    'option'   => '10er Packung',
                    'price'    => 17.95,
                ],
                [
                    'brand' => 'InnoCigs',
                    'option'   => '10er Packung',
                    'price'    => 22.9,
                ],
                [
                    'brand' => 'Erste Sahne',
                    'option'   => '10er Packung',
                    'price'    => 34.60,
                ],
                [
                    'brand' => 'Erste Sahne',
                    'option'   => '5er Packung',
                    'price'    => 18.25,
                ],
                [
                    'brand' => 'Vampire Vape',
                    'option'   => '20er Packung',
                    'price'    => 62.50,
                ],
            ],
        ];

        /** @var PriceEngine $priceEngine */
        $services = MxcDropshipIntegrator::getServices();
        $priceEngine = $services->get(PriceEngine::class);
        $log = $services->get('logger');

        $ids = [];
        foreach ($bulkPrices as $type => $data) {
            foreach ($data as $item) {
                $products = $repository->findBy(['type' => $type, 'brand' => $item['brand']]);
                $price = $item['price'] / $vatFactor;
                /** @var Product $product */
                foreach ($products as $product) {
                    $variants = $product->getVariants();
                    foreach ($variants as $variant) {
                        $options = $variant->getOptions();
                        foreach ($options as $option) {
                            $groupName = $option->getIcGroup()->getName();
                            if ($groupName != 'Packungsgröße') {
                                continue;
                            }
                            if ($item['option'] != $option->getName()) {
                                continue;
                            }
                            $priceEngine->setRetailPrice($variant, 'EK', $price);
                            $variant->setAccepted(true);
                            $log->debug('Price set to ' . $item['price'] . ' for ' . $item['brand'] . ' - ' . $variant->getName());
                            $ids[$product->getId()] = true;
                        }
                    }
                }
                $this->getManager()->flush();
            }
        }
        $ids = array_keys($ids);

        $productMapper = $services->get(ProductMapper::class);
        $propertyMapper = $services->get(PropertyMapper::class);
        /** @var PriceMapper $priceMapper */
        $priceMapper = $services->get(PriceMapper::class);

        $products = $repository->getProductsByIds($ids);
        foreach ($products as $product) {
            $log->debug('getProductsByIds: ' . $product->getName());
        }

        $propertyMapper->mapProperties($products, true);
        $this->getManager()->flush();
        $products = $repository->getLinkedProductsFromProductIds($ids);

        foreach ($products as $product) {
            $product->setActive(true);
            $log->debug('Modified price for :' . $product->getName());
        }
        $productMapper->updateArticles($products, false);
        $this->getManager()->flush();
        foreach ($products as $product) {
            $variants = $product->getVariants();
            foreach ($variants as $variant) {
                $priceMapper->setPrices($variant);
            }
        }

        $this->getManager()->flush();
    }

    protected function getSupplierProductCount()
    {
        $productCount = [];
        $suppliers = $this->getManager()->getRepository(Supplier::class)->findAll();
        /** @var Supplier $supplier */
        foreach($suppliers as $supplier) {
            $productCount[$supplier->getName()] = 0;
        }

        $articles = $this->getManager()->getRepository(Article::class)->findAll();
        /** @var Article $article */
        foreach ($articles as $article) {
            $productCount[$article->getSupplier()->getName()]++;
        }
        return $productCount;
    }

    protected function zipSupplierLogos()
    {
        $suppliers = $this->getManager()->getRepository(Supplier::class)->findAll();
        $media = MxcDropshipIntegrator::getServices()->get('shopware_media.media_service');
        $docPath = Shopware()->DocPath();
        $zip = new ZipArchive();
        $zipPath = __DIR__ . '/../../Config/vapee_supplier_logos.zip';
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $logoDir = [];

        /** @var Supplier $supplier */
        foreach ($suppliers as $supplier) {
            $img = $supplier->getImage();
            if (empty($img)) continue;
            $url = $media->getUrl($img);
            $path = explode('media', $url);
            $path = $docPath . 'media' . $path[1];
            $baseName = basename($path);
            $zip->addFile($path, basename($path));
            $logoDir[$supplier->getName()] = $baseName;
        }
        $zip->addFromString('directory.php', var_export($logoDir, true));
        $zip->close();
        return $zipPath;
    }

    protected function countSimilarProducts()
    {
        $log = MxcDropshipIntegrator::getServices()->get('logger');
        $products = $this->getManager()->getRepository(Product::class)->findAll();
        /** @var Article $article */
        $similarProducts = [];
        foreach ($products as $product) {
            $article = $product->getArticle();
            if ($article === null) continue;
            $similar = $article->getSimilar();
            $similarProducts[$product->getType()][$product->getName()] = $similar->count();
        }
        foreach ($similarProducts as $key => &$products)
        {
            ksort($products);
        }
        $log->debug('Similar product count: ' . var_export($similarProducts, true));
    }

    protected function createFlavorMappingFile()
    {
        $flavors = include __DIR__ . '/../../Config/FlavorMapper.config.php';
        $log = MxcDropshipIntegrator::getServices()->get('logger');

        $map = [];
        foreach ($flavors as $name => $categories) {
            foreach ($categories as $flavor) {
                $map[$flavor] = [$name];
            }
        }
        $log->debug(var_export($map, true));
    }

    public function dev3Action()
    {
        try {
            $orderId = 1129;
            $services = MxcDropshipIntegrator::getServices();
            /** @var \MxcCommons\Toolbox\Shopware\OrderTool $orderTool */
            $orderTool = $services->get(\MxcCommons\Toolbox\Shopware\OrderTool::class);
            $order = $orderTool->getOrder($orderId);
            $log = $services->get('logger');
            $calculator = $services->get('bestit_klarna_order_management.components.calculator.calculator');
            $capture = $services->get('bestit_klarna_order_management.components.facade.capture');
            $cents = $calculator->toCents($order['invoice_amount']);
            $response = $capture->create($order['transactionID'], $cents);
            if ($response->isError()) {
                $error = $response->getError();
                $log->err('Klarna Error: ' . $error->errorCode . ': ' . $error->errorMessages);
                foreach ($error->errorMessages as $msg) {
                    $log->err('MSG: ' . $msg);
                }
                /** @var WorkflowEngine $engine */
                $orderTool->setPaymentStatus($order['id'], Status::PAYMENT_STATE_REVIEW_NECESSARY);
            }


            /** @var \MxcCommons\Toolbox\Shopware\OrderTool $orderTool */
            // $orderTool = \MxcCommons\MxcCommons::getServices()->get(\MxcCommons\Toolbox\Shopware\OrderTool::class);
            // $orderTool->setOrderStatusDoctrine(34,\Shopware\Models\Order\Status::ORDER_STATE_COMPLETELY_DELIVERED);




            // $this->createFlavorMappingFile();
            //$this->countSimilarProducts();


//            $suppliers = $this->getSupplierProductCount();
//            $empty = [];
//            foreach ($suppliers as $name => $count) {
//                if ($count > 0) continue;
//                $empty[] = $name;
//            }
//            $log = MxcDropshipIntegrator::getServices()->get('logger');
//            $log->debug(var_export($suppliers, true));
//            $log->debug(implode(', ', $empty));


            //            $this->setArticleType();

//            $this->adjustFlavor();
//            $this->adjustOwnStockFlavors();
            /** @var BulkPriceMapper $bulkPriceMapper */
//--->            $bulkPriceMapper = MxcDropshipIntegrator::getServices()->get(BulkPriceMapper::class);
//--->            $bulkPriceMapper->mapBulkPrices();

            //            $this->resetSinglePacks();
//            $this->remapAccepted();
//            $this->findMultipackLiquids();
//            $repository = $this->getManager()->getRepository(Variant::class);
//            $variants = $repository->getAcceptedVariants();
//            $accepted = true;
//            /** @var Variant $variant */
//            foreach ($variants as $variant) {
//                if (! $variant->getAccepted()) {
//                    $accepted = false;
//                    break;
//                }
//            }
//
//            $count = count($variants);
//
//            if ($accepted === true)
//            {
//                $variants = $repository->getAllIndexed();
//                $count2 = 0;
//                foreach ($variants as $variant) {
//                    if ($variant->getAccepted() === true) $count2++;
//                }
//            }


            /** @var UpdateTrackingData $trackingUpdate */
//            $trackingUpdate = MxcDropship::getServices()->get(\MxcDropship\Jobs\UpdateTrackingData::class);
//            $trackingUpdate->run();
//
//            $workflow = MxcWorkflow::getServices()->get(WorkflowEngine::class);
//            $workflow->run();

//            /** @var \MxcDropshipInnocigs\Order\OrderProcessor $orderProcessor */
//            $sendOrders = MxcDropship::getServices()->get(SendOrders::class);
//            $sendOrders->run();

            $this->view->assign([ 'success' => true, 'message' => 'Development 3 slot is currently free.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function dev4Action()
    {
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $variants = $this->getManager()->getRepository(Variant::class)->getAllIndexed();
            $models = $this->getManager()->getRepository(Model::class)->getAllIndexed();
            $services = MxcDropshipIntegrator::getServices();
            $log = $services->get('logger');
            /** @var Variant $variant */
            foreach ($variants as $variant) {
                $icNumber = $variant->getIcNumber();
                /** @var Model $model */
                $model = $models[$icNumber];
                if ($model === null || strpos($model->getOptions(), '1er Packung') === false) continue;
                if (! $variant->isActive() && $variant->isValid()) {
                    $log->debug('Inactive valid variant: ' . $model->getName());
                }
            }
            $this->view->assign([ 'success' => true, 'message' => 'Inactive variant check done.' ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function dev5Action() {
        try {
             $params = $this->request->getParams();
            /** @noinspection PhpUnusedLocalVariableInspection */
            $ids = json_decode($params['ids'], true);

            $engine = MxcDropshipIntegrator::getServices()->get(PriceEngine::class);
            $engine->createDefaultConfiguration();


            // Do something with the ids
            $this->view->assign([ 'success' => true, 'message' => 'Development slot #5 is currently free.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function dev6Action() {
        try {
            $services = MxcDropshipInnocigs::getServices();
            $log = $services->get('logger');
            /** @var ApiClient $apiClient */
            $apiClient = $services->get(ApiClient::class);
            $ss = $apiClient->getShippingStatus();
            $shippingStatus = [];
            foreach ($ss as $status) {
                $shippingStatus[$status]++;
            }
            $log->debug(var_export($shippingStatus, true));

            $this->view->assign([ 'success' => true, 'message' => 'Development slot #6 is currently free.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function dev7Action() {
        try {
            $params = $this->request->getParams();
            /** @noinspection PhpUnusedLocalVariableInspection */
            $ids = json_decode($params['ids'], true);

            $products = $this->getManager()->getRepository(Product::class)->findAll();
            /** @var Product $product */
            $productsIcDescription = [];
            foreach ($products as $product) {
                $type = $product->getType();
                if ($type != 'E_CIGARETTE') continue;
                /** @var Article $article */
                $article = $product->getArticle();
                if ($article === null) continue;
                $similar = $article->getSimilar();
                if ($similar->count() < 5) {
                    $productsIcDescription[$product->getIcNumber()] = $product->getName();
                }
            }
            krsort($productsIcDescription);
            $report = new ArrayReport();
            $report(['icNotEnoughSimilarECigs' => $productsIcDescription]);

            // Do something with the ids
            $this->view->assign([ 'success' => true, 'message' => 'Ecigs with less similar products exported.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    public function dev8Action() {
        try {
            $log = MxcDropshipIntegrator::getServices()->get('logger');
            $db = Shopware()->Db();

            // Find all articles without details
            $sql = 'SELECT a.id, a.name FROM s_articles a LEFT JOIN s_articles_details ad ON ad.articleID = a.id WHERE ad.id IS NULL';
            $result = $db->fetchAll($sql);
            $result = empty($result) ? 'none' : var_export($result, true);
            $log->debug('Defective articles (no details): ' . $result);

            // Find all details without articel
            $sql = 'SELECT d.id FROM s_articles_details d LEFT JOIN s_articles a ON d.articleID = a.id WHERE a.id IS NULL';
            $result = $db->fetchAll($sql);
            $result = empty($result) ? 'none' : var_export($result, true);
            $log->debug('Orphaned details (no article): ' . $result);

            // Find all details without attributes
            $sql = 'SELECT d.id FROM s_articles_details d LEFT JOIN s_articles_attributes a ON a.articledetailsID = d.id WHERE a.id IS NULL';
            $result = $db->fetchAll($sql);
            $result = empty($result) ? 'none' : var_export($result, true);
            $log->debug('Details without attributes: ' . $result);

            // Find all attributes without details
            $sql = 'SELECT a.articledetailsID FROM s_articles_attributes a LEFT JOIN s_articles_details d ON a.articledetailsID = d.id WHERE a.id IS NULL';
            $result = $db->fetchAll($sql);
            $result = empty($result) ? 'none' : var_export($result, true);
            $log->debug('Orphaned attributes (no detail): ' . $result);

            // Find all article relations where article articleID does not exist
            $sql = 'SELECT ar.articleID FROM s_articles_relationships ar LEFT JOIN s_articles a ON ar.articleID = a.id WHERE a.id IS NULL ';
            $result = $db->fetchAll($sql);
            $result = empty($result) ? 'none' : var_export($result, true);
            $log->debug('Related Article does not exist:' . $result);

            // Find all article relations where similar article of articleID does not exist
            $sql = 'SELECT ar.articleID FROM s_articles_similar ar LEFT JOIN s_articles a ON ar.articleID = a.id WHERE a.id IS NULL ';
            $result = $db->fetchAll($sql);
            $result = empty($result) ? 'none' : var_export($result, true);
            $log->debug('Similar Article does not exist:' . $result);

            $this->view->assign([ 'success' => true, 'message' => 'Development 8 slot is currently free.']);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function getStateUpdates(array $data)
    {
        $product = isset($data['id']) ? $this->getRepository()->find($data['id']) : null;
        if (! $product) return [null, null];

        $changes = [];
        foreach (['accepted', 'active', 'linked'] as $property) {
            $getState = 'is' . ucfirst($property);
            if ($product->$getState() === $data[$property]) continue;
            $changes[$property] = $data[$property];
        }
        return [$product, $changes];
    }

    protected function updateProductStates(Product $product, array $changes)
    {
        $services = MxcDropshipIntegrator::getServices();
        /** @var ProductMapper $productMapper */
        $productMapper = $services->get(ProductMapper::class);

        $change = $changes['linked'] ?? null;
        if ($change === false) {
            $productMapper->deleteArticles([$product]);
            return;
        } elseif ($change === true) {
            $productMapper->controllerUpdateArticles([$product], true);
        }

        $change = $changes['accepted'] ?? null;
        if ($change !== null) {
            $productMapper->acceptArticle($product, $change);
            if ($change === false) return;
        }

        $change = $changes['active'] ?? null;
        if ($change === true) {
            $productMapper->controllerActivateArticles([$product], true, true);
        } elseif ($change === false) {
            $productMapper->controllerActivateArticles([$product], false, false);
        }
    }

    public function save($data) {
        [$product, $changes] = $this->getStateUpdates($data);
        if (! $product) {
            return [ 'success' => false, 'message' => 'Creation of new products via GUI is not supported.'];
        }

        // Variant data is empty only if the request comes from the list view (not the detail view)
        // We prevent storing an article with empty variant list by unsetting empty variant data.
        $fromListView = isset($data['variants']) && empty($data['variants']);
        if ($fromListView) unset($data['variants']);

        // hydrate (new or existing) article from UI data
        $data = $this->resolveExtJsData($data);
        unset($data['relatedProducts']);
        unset($data['similarProducts']);
        $product->fromArray($data);
        $this->getManager()->flush();

        // @todo
        // This is a dirty hack to promote a change of the release date to all details
        //
        $article = $product->getArticle();
        if ($article !== null) {
            $releaseDate = $product->getReleaseDate();
            $details = $article->getDetails();
            foreach ($details as $detail) {
                $detail->setReleaseDate($releaseDate);
            }
        }
        $this->getManager()->flush();
        //
        // End Hack

        /** @var Product $product */
        $this->updateProductStates($product, $changes);


        // The user may have changed the accepted state of variants to false in the detail view of an product.
        // So we need to check and remove invalid variants when the detail view gets saved.
        $services = MxcDropshipIntegrator::getServices();
        /** @var ProductMapper $productMapper */
        $productMapper = $services->get(ProductMapper::class);
        if (! $fromListView) {
            $productMapper->updateArticleStructure($product);
            $this->getManager()->flush();
        }

        $this->getRepository()->exportMappedProperties();

        $detail = $this->getDetail($product->getId());
        return ['success' => true, 'data' => $detail['data']];
    }

    protected function getRepository()
    {
        return $this->getManager()->getRepository(Product::class);
    }

    protected function getAdditionalDetailData(array $data) {
        $data['variants'] = [];
        return $data;
    }

    protected function handleException(Throwable $e, bool $rethrow = false) {
        $showTrace = $e instanceof ApiException ? false : true;
        MxcDropshipIntegrator::getServices()->get('logger')->except($e, $showTrace, $rethrow);
        $this->view->assign([ 'success' => false, 'message' => $e->getMessage() ]);
    }

    public function checkVariantsWithoutOptionsAction()
    {
        try {
            $variants = $this->getManager()->getRepository(Variant::class)->findAll();
            $log = MxcDropshipIntegrator::getServices()->get('logger');
            $issues = [];
            /** @var Variant $variant */
            foreach ($variants as $variant) {
                if ($variant->getOptions() === null) {
                    $issues[] = $variant->getIcNumber();
                }
            }
            $c = count($issues);
            if (count($issues) > 0 ) {
                $msg = 'Found ' . $c . ' variants without options. See log for details.';
                $log->debug('Variants without options:');
                $log->debug(var_export($issues, true));

            } else {
                $msg = 'No variants without options were found.';
            }
            $this->view->assign([ 'success' => true, 'message' => $msg]);
            // Do something with the ids
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function deleteShopwareArticles(): void
    {
        // delete all Shopware articles
        $modelManager = $this->getManager();
        $articles = $modelManager->getRepository(Article::class)->findAll();
        $articleResource = new ArticleResource();
        $articleResource->setManager($modelManager);
        /** @var Article $article */
        foreach ($articles as $article) {
            $articleResource->delete($article->getId());
        }
    }

    protected function testImportFromFile(string $xmlFile, bool $recreateSchema): void
    {
        $services = MxcDropshipIntegrator::getServices();
        /** @var ImportClient $client */
        $client = $services->get(ImportClient::class);
        $mapper = $services->get(ImportMapper::class);

        // note: our models will be automatically deleted via param recreateSchema = true
        $mapper->import($client->importFromFile($xmlFile, true, $recreateSchema));

        $products = $this->getManager()->getRepository(Product::class)->findAll();
        $services = MxcDropshipIntegrator::getServices();
        $productMapper = $services->get(ProductMapper::class);
        $productMapper->updateArticles($products, true);
    }
}
