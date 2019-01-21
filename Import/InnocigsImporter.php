<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace MxcDropshipInnocigs\Import;

use Mxc\Shopware\Plugin\Service\LoggerInterface;
use MxcDropshipInnocigs\Client\ApiClient;
use MxcDropshipInnocigs\Exception\InvalidArgumentException;
use MxcDropshipInnocigs\Models\Current\Article;
use MxcDropshipInnocigs\Models\Current\Group;
use MxcDropshipInnocigs\Models\Current\Image;
use MxcDropshipInnocigs\Models\Current\Option;
use MxcDropshipInnocigs\Models\Current\Variant;
use Shopware\Components\Model\ModelManager;
use Zend\Config\Config;

class InnocigsImporter
{
    /**
     * @var ApiClient $apiClient
     */
    protected $apiClient;
    /**
     * @var array $options
     */
    protected $options;
    /**
     * @var LoggerInterface $log
     */
    protected $log;
    /**
     * @var ModelManager $modelManager
     */
    protected $modelManager;
    /**
     * @var Config $config
     */
    protected $config;

    /**
     * InnocigsImporter constructor.
     *
     * @param ModelManager $modelManager
     * @param ApiClient $apiClient
     * @param Config $config
     * @param LoggerInterface $log
     */
    public function __construct(
        ModelManager $modelManager,
        ApiClient $apiClient,
        Config $config,
        LoggerInterface $log
    ) {
        $this->modelManager = $modelManager;
        $this->apiClient = $apiClient;
        $this->config = $config;
        $this->log = $log;
    }

    public function addArticleDetail(Article $article)
    {
        $variant = $article->getVariants()[0];
        $raw = $this->apiClient->getItemInfo($variant->getCode());
        $description = $this->getStringParam($raw['PRODUCTS']['PRODUCT']['DESCRIPTION']);
        if ($description === '') {
            $this->log->warn(sprintf('%s: No description available from InnoCigs for article %s.',
                __FUNCTION__,
                $article->getCode()
            ));
            return;
        }
        if ($article->getDescription() !== $description) {
            $this->log->info(sprintf('%s: Adding article description from InnoCigs to article %s.',
                __FUNCTION__,
                $article->getCode()
            ));
            $article->setDescription($description);
            $this->modelManager->persist($article);
        } else {
            $this->log->info(sprintf('%s: ImportArticle description from InnoCigs for article %s is up to date.',
                __FUNCTION__,
                $article->getCode()
            ));
        }
    }

    public function getStock(Variant $variant)
    {
        $raw = $this->apiClient->getStockInfo($variant->getCode());
        $this->log->debug(var_export($raw, true));
        return $raw['QUANTITIES']['PRODUCT']['QUANTITY'];
    }

    private function getStringParam($value)
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_array($value) && empty($value)) {
            return '';
        }
        throw new InvalidArgumentException(
            sprintf('String or empty array expected, got %s.',
                is_object($value) ? get_class($value) : gettype($value)
            )
        );
    }

    protected function createGroups(array $opts)
    {
        foreach ($opts as $groupName => $options) {
            $group = new Group();
            $group->setName($groupName);
            $group->setAccepted(true);
            $this->createOptions($group, array_keys($options));
            // this cascades persisting the options also
            $this->modelManager->persist($group);
        }
    }

    protected function createOptions(Group $group, $options)
    {
        foreach ($options as $optionName) {
            $option = new Option();
            $option->setName($optionName);
            $option->setAccepted(true);
            $group->addOption($option);
            $this->options[$group->getName()][$optionName] = $option;
        }
    }

    protected function createArticles(array $articles, int $limit = -1)
    {
        $i = 0;
        foreach ($articles as $articleCode => $articleData) {
            $article = new Article();
            $article->setActive(false);
            $article->setAccepted(true);
            $articleProperties = $this->createVariants($article, $articleData);
            $name = $articleProperties['name'];
            $article->setName($name);
            $article->setImageUrl($this->getStringParam($articleProperties['image']));
            $article->setManualUrl($this->getStringParam($articleProperties['manual']));
            $article->setCategory($this->getStringParam($articleProperties['category']));
            $article->setManufacturer($this->getStringParam($articleProperties['manufacturer']));
            $article->setCode($articleCode);
            $article->setDescription('n/a');
            if (isset($this->articleConfig[$articleCode]['brand'])) {
                $article->setBrand($this->articleConfig[$articleCode]['brand']);
            } else {
                $this->log->warn(sprintf('No brand info for article %s: %s',
                    $articleCode,
                    $name)
                );
            }
            if (isset($this->articleConfig[$articleCode]['supplier'])) {
                $article->setSupplier($this->articleConfig[$articleCode]['supplier']);
            } else {
                $this->log->warn(sprintf('No supplier info for article %s: %s',
                    $articleCode,
                    $name)
                );
            }
            // this cascades persisting the variants also
            $this->modelManager->persist($article);
            $i++;
            if ($limit !== -1 && $i === $limit) {
                break;
            }
        }
    }

    protected function removeOptionsFromArticleName(string $name, array $options) {
        // Innocigs variant names include variant descriptions
        // We take the first variant's name and remove the variant descriptions
        // in order to extract the real article name
        foreach ($options as $option) {
            $option = trim($option);
            if ($option === '1er Packung') continue;

            if (strpos($name, $option) !== false) {
                $name = str_replace($option, '', $name);
            } else {
                // They introduced some cases where the option name is not equal
                // to the string added to the article name, so we have to check
                // that, also. The implementation here is a hack right now.
                $o = $this->optionNameMapping[$option] ?? null;
                if ($o) {
                    $this->log->warn(sprintf(
                        'ImportArticle name \'%s\' does not contain the option name \'%s\'. ImportOption name mapping fix applied.',
                        $name,
                        $option
                    ));
                    $name = str_replace($o, '', $name);
                } else {
                    $this->log->warn(sprintf(
                        'ImportArticle name \'%s\' does not contain the option name \'%s\' and there is no option name mapping specified.',
                        $name,
                        $option
                    ));
                }
            }
        }
        return trim($name);
    }

    protected function createVariants(Article $article, array $variantArray): array
    {
        $articleProperties = null;
        // mark all variants of active articles active
        $active = $article->isActive();
        $accepted = $article->isAccepted();
        foreach ($variantArray as $variantCode => $variantData) {
            $variant = new Variant();

            $variant->setCode($variantCode);
            $variant->setActive($active);
            $variant->setAccepted($accepted);

            $variant->setEan($this->getStringParam($variantData['EAN']));
            $tmp = str_replace(',', '.', $variantData['PRODUCTS_PRICE']);
            $variant->setPriceNet(floatval($tmp));
            $tmp = str_replace(',', '.', $variantData['PRODUCTS_PRICE_RECOMMENDED']);
            $variant->setRetailPrice(floatval($tmp));
            $article->addVariant($variant);
            foreach($variantData['PRODUCTS_IMAGE_ADDITIONAL']['IMAGE'] as $imageData) {
                $image = new Image();
                $image->setImage($imageData);
                $variant->addImage($image);
            }
            if (null === $articleProperties) {
                $articleProperties['name'] = $this->removeOptionsFromArticleName($variantData['NAME'], $variantData['PRODUCTS_ATTRIBUTES']);
                $articleProperties['image'] = $variantData['PRODUCTS_IMAGE'];
                $articleProperties['manual'] = $variantData['PRODUCTS_MANUAL'];
                $articleProperties['category'] = $variantData['CATEGORY'];
                $articleProperties['manufacturer'] = $variantData['MANUFACTURER'];
            }
            foreach ($variantData['PRODUCTS_ATTRIBUTES'] as $group => $option) {
                $optionEntity = $this->options[$group][$option];
                $variant->addOption($optionEntity);
            }
        }
        return $articleProperties;
    }

    protected function readArticleConfiguration() {
        $this->articleConfig = [];
        if (file_exists($this->articleConfigFile)) {
            /** @noinspection PhpIncludeInspection */
            $this->articleConfig = include $this->articleConfigFile;
        }
    }

    public function importArticles(int $limit = -1)
    {
        $raw = $this->apiClient->getItemList();
        $items = [];
        $options = [];
        foreach ($raw['PRODUCTS']['PRODUCT'] as $item) {
            $items[$item['MASTER']][$item['MODEL']] = $item;
            foreach ($item['PRODUCTS_ATTRIBUTES'] as $group => $option) {
                $options[$group][$option] = 1;
            }
            if ($limit !== -1 && count($items) === $limit) {
                break;
            }
        }
        $this->log->info('Creating groups and options.');
        $this->createGroups($options);
        $this->log->info('Creating articles and variants.');
        $this->createArticles($items, $limit);
        $this->modelManager->flush();
    }

    public function import()
    {
        $this->log->enter();
        // only import articles if we do not have them
        $repository = $this->modelManager->getRepository(Article::class);
        $count = intval($repository->createQueryBuilder('a')->select('count(a.id)')->getQuery()->getSingleScalarResult());
        if ($count === 0) {
            /** @noinspection PhpUndefinedFieldInspection */
            if (true === $this->config->useArticleConfiguration) {
                $this->readArticleConfiguration();
            }
            /** @noinspection PhpUndefinedFieldInspection */
            $this->importArticles($this->config->numberOfArticles ?? -1);
            /** @noinspection PhpUndefinedFieldInspection */
            if ($this->config->applyFilters) {
                $this->log->notice('Applying import modifications.');
                $this->importModifier->apply();
            }
        }
        $this->log->leave();
        return true;
    }
}
