<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace MxcDropshipInnocigs\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Exception;
use Mxc\Shopware\Plugin\Service\LoggerInterface;
use MxcDropshipInnocigs\Listener\InnocigsClient;
use MxcDropshipInnocigs\Models\InnocigsArticle;
use MxcDropshipInnocigs\Models\InnocigsVariant;
use MxcDropshipInnocigs\Toolbox\Media\MediaTool;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Price;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Customer\Group;
use Shopware\Models\Plugin\Plugin;
use Shopware\Models\Tax\Tax;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\ListenerAggregateTrait;

class ArticleMapper implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /**
     * @var LoggerInterface $log
     */
    protected $log;

    /**
     * @var ArticleOptionMapper $optionMapper
     */
    protected $optionMapper;

    /**
     * @var PropertyMapper $propertyMapper
     */
    protected $propertyMapper;

    /**
     * @var InnocigsClient $client
     */
    protected $client;
    /**
     * @var ModelManager $modelManager
     */
    protected $modelManager;
    /**
     * @var MediaTool $mediaTool
     */
    protected $mediaTool;
    /**
     * @var array $unitOfWork
     */
    protected $unitOfWork = [];

    protected $shopwareGroups = [];
    protected $shopwareGroupRepository = null;
    protected $shopwareGroupLookup = [];

    public function __construct(
        ModelManager $modelManager,
        ArticleOptionMapper $optionMapper,
        PropertyMapper $propertyMapper,
        MediaTool $mediaTool,
        InnocigsClient $client,
        LoggerInterface $log)
    {
        $this->modelManager = $modelManager;
        $this->optionMapper = $optionMapper;
        $this->propertyMapper = $propertyMapper;
        $this->mediaTool = $mediaTool;
        $this->client = $client;
        $this->log = $log;
    }

    protected function createShopwareArticle(InnocigsArticle $article) {
        // do nothing if either the article or all of its variants are set to be ignored
        if ($article->isIgnored()) return;

        $swArticle = $this->getShopwareArticle($article) ?? new Article();
        $this->modelManager->persist($swArticle);

        $name = $this->propertyMapper->mapArticleName($article->getName());

        // this will get the product detail record from InnoCigs can hold the description
        $this->client->addArticleDetail($article);

        $article->setArticle($swArticle);

        $tax = $this->getTax();
        $supplier = $this->getSupplier($article);
        $swArticle->setName($name);
        $swArticle->setTax($tax);
        $swArticle->setSupplier($supplier);
        $swArticle->setMetaTitle('');
        $swArticle->setKeywords('');
        $swArticle->setDescription($article->getDescription());
        $swArticle->setDescriptionLong($article->getDescription());
        //todo: get description from innocigs

        $swArticle->setActive(true);

        $set = $this->optionMapper->createConfiguratorSet($article);
        $swArticle->setConfiguratorSet($set);

        $this->mediaTool->setArticleImages($article->getImageUrl(), $swArticle);

        //create details from innocigs variants
        $variants = $article->getVariants();

        $isMainDetail = true;
        foreach($variants as $variant){
            if ($variant->isIgnored()) continue;
            /**
             * @var Detail $swDetail
             */
            $code = $this->propertyMapper->mapVariantCode($variant->getCode());
            $swDetail = $this->modelManager->getRepository(Detail::class)->findOneBy([ 'number' => $code ])
                ?? $this->createShopwareDetail($variant, $swArticle, $isMainDetail);

            $this->modelManager->persist($swDetail);

            if($isMainDetail){
                $swArticle->setMainDetail($swDetail);
                $isMainDetail = false;
            }
        }

        $this->modelManager->flush();
        return;
    }

    /**
     * Gets the Shopware Article by looking for the Shopware detail of the first variant for the supplied $article.
     * If it exists, we assume that the article and all other variants exist as well
     *
     * @param InnocigsArticle $article
     * @return null|Article
     */
    protected function getShopwareArticle(InnocigsArticle $article){
        $swArticle = null;
        $variants = $article->getVariants();
        $codes = [];
        foreach ($variants as $variant) {
            $codes[] = $this->propertyMapper->mapVariantCode($variant->getCode());
        }
        $expr = Criteria::expr();
        /**
         * @var Criteria $criteria
         */
        $criteria = Criteria::create()->where($expr->in('number', $codes));
        $swDetails = $this->modelManager->getRepository(Detail::class)->matching($criteria);

        if (! $swDetails->isEmpty()){
            $swArticle = $swDetails->offsetGet(0)->getArticle();
        }
        return $swArticle;
    }

    protected function createShopwareDetail(InnocigsVariant $variant, Article $swArticle, bool $isMainDetail){
        $this->log->info(sprintf('%s: Creating detail record for InnoCigs variant %s',
            __FUNCTION__,
            $variant->getCode()
        ));

        $detail = new Detail();

        // The class \Shopware\Models\Attribute\Article ist part of the Shopware attribute system.
        // It gets (re)generated automatically by Shopware core, when attributes are added/removed
        // via the attribute crud service. It is located in \var\cache\production\doctrine\attributes.
        //
        if (class_exists('\Shopware\Models\Attribute\Article')) {
            $attribute = new \Shopware\Models\Attribute\Article();
            $detail->setAttribute($attribute);
            if ($isMainDetail) {
                $swArticle->setAttribute($attribute);
            }
        } else {
            throw new Exception(__FUNCTION__ . ': Shopware article attribute model does not exist.');
        }

        $detail->setNumber($this->propertyMapper->mapVariantCode($variant->getCode()));
        $detail->setEan($variant->getEan());
        $detail->setStockMin(0);
        $detail->setSupplierNumber('');
        $detail->setAdditionalText('');
        $detail->setPackUnit('');
        $detail->setShippingTime(5);
        $detail->setPurchasePrice($variant->getPriceNet());

        $isMainDetail ? $detail->setKind(1) : $detail->setKind(2);

        $detail->setActive(true);
        $detail->setLastStock(0);
        // Todo: $detail->setPurchaseUnit();
        // Todo: $detail->setReferenceUnit();

        $detail->setArticle($swArticle);

        $prices = $this->createPrice($variant, $swArticle, $detail);
        $detail->setPrices($prices);
        // Note: shopware options are added non persistently to variants when
        // configurator set is created
        $detail->setConfiguratorOptions(new ArrayCollection($this->optionMapper->getShopwareOptions($variant)));

        return $detail;
    }

    protected function enableDropship(Article $swArticle)
    {
        if (null === $this->modelManager->getRepository(Plugin::class)->findOneBy(['name' => 'wundeDcInnoCigs'])) {
            $this->log->warn(sprintf('%s: Could not prepare Shopware article "%s" for dropship orders. Dropshippers Companion is not installed.',
                __FUNCTION__,
                $swArticle->getName()
            ));
            return;
        }
    }

    protected function createPrice(InnocigsVariant $variant, Article $swArticle, Detail $detail){
        $tax = $this->getTax()->getTax();
        $netPrice = $variant->getPriceRecommended() / (1 + ($tax/100));

        $this->log->info(sprintf('%s: Creating price %.2f for detail record %s.',
            __FUNCTION__,
            $netPrice,
            $detail->getNumber()
        ));

        $price = new Price();
        $price->setPrice($netPrice);
        $price->setFrom(1);
        $price->setTo(null);
        $customerGroup = $this->modelManager->getRepository(Group::class)->findOneBy(['key' => 'EK']);
        $price->setCustomerGroup($customerGroup);
        $price->setArticle($swArticle);
        $price->setDetail($detail);

        $this->modelManager->persist($price);
        return new ArrayCollection([$price]);
    }

    /**
     * If supplied $article has a supplier then get it by name from Shopware or create it if necessary.
     * Otherwise do the same with default supplier name InnoCigs
     *
     * @param InnocigsArticle $article
     * @return null|object|Supplier
     */
    protected function getSupplier(InnocigsArticle $article) {
        $supplierName = $article->getSupplier() ?? 'InnoCigs';
        $supplier = $this->modelManager->getRepository(Supplier::class)->findOneBy(['name' => $supplierName]);
        if (! $supplier) {
            $this->log->info(sprintf('%s: Creating Shopware supplier "%s"',
                __FUNCTION__,
                $supplierName
            ));
            $supplier = new Supplier();
            $this->modelManager->persist($supplier);
            $supplier->setName($supplierName);
        } else {
            $this->log->info(sprintf('%s: Using existing Shopware supplier "%s"',
                __FUNCTION__,
                $supplierName
            ));
        }
        return $supplier;
    }

    protected function removeShopwareArticle(InnocigsArticle $article) {
        $this->log->info('Remove Shopware Article for ' . $article->getName());
    }

    protected function getTax(float $taxValue = 19.0) {
        $tax = $this->modelManager->getRepository(Tax::class)->findOneBy(['tax' => $taxValue]);
        if (! $tax instanceof Tax) {
            $name = sprintf('Tax (%.2f)', $taxValue);
            $this->log->info(sprintf('%s: Creating Shopware tax "%s" with tax value %.2f.',
                __FUNCTION__,
                $name,
                $taxValue
            ));

            $tax = new Tax();
            $this->modelManager->persist($tax);

            $tax->setName($name);
            $tax->setTax($taxValue);
        } else {
            $this->log->info(sprintf('%s: Using existing Shopware tax "%s" with tax value %.2f.',
                __FUNCTION__,
                $tax->getName(),
                $taxValue
            ));

        }
        return $tax;
    }

    public function onArticleActiveStateChanged(EventInterface $e) {
        /**
         * @var InnocigsArticle $article
         */
        $this->log->info(__CLASS__ . '#' . __FUNCTION__ . ' was triggered.');
        $this->unitOfWork[] = $e->getParams()['article'];
    }

    public function onProcessActiveStates()
    {
        $this->log->info(__CLASS__ . '#' . __FUNCTION__ . ' was triggered.');
        foreach ($this->unitOfWork as $article) {
            /**
             * @var InnocigsArticle $article
             */
            $this->log->info(sprintf('%s: Processing active state for %s.',
                __FUNCTION__,
                $article->getCode()
            ));
            $article->isActive() ?
                $this->createShopwareArticle($article) :
                $this->removeShopwareArticle($article);
        }
        $this->unitOfWork = [];
        // we have to reset this because groups may be deleted
        // by other modules or plugins
    }

    /**
     * Attach one or more listeners
     *
     * Implementors may add an optional $priority argument; the EventManager
     * implementation will pass this to the aggregate.
     *
     * @param EventManagerInterface $events
     * @param int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach('article_active_state_changed', [$this, 'onArticleActiveStateChanged'], $priority);
        $this->listeners[] = $events->attach('process_active_states', [$this, 'onProcessActiveStates'], $priority);
    }
}