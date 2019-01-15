<?php

use Mxc\Shopware\Plugin\Controller\BackendApplicationController;
use Mxc\Shopware\Plugin\Database\SchemaManager;
use MxcDropshipInnocigs\Import\ImportModifier;
use MxcDropshipInnocigs\Import\InnocigsClient;
use MxcDropshipInnocigs\Import\InnocigsUpdater;
use MxcDropshipInnocigs\Mapping\ArticleMapper;
use MxcDropshipInnocigs\Mapping\InnocigsEntityValidator;
use MxcDropshipInnocigs\Models\InnocigsArticle;

class Shopware_Controllers_Backend_MxcDsiArticle extends BackendApplicationController
{
    protected $model = InnocigsArticle::class;
    protected $alias = 'innocigs_article';

    public function indexAction() {
        $this->log->enter();
        /**
         * @var \Shopware\Components\Model\ModelManager $modelManager
         */
        try {
            $this->services->get(InnocigsClient::class)->import();
            parent::indexAction();
        } catch (Throwable $e) {
            $this->log->except($e);
        }
        $this->log->leave();
    }

    public function updateAction()
    {
        $this->log->enter();
        $data = $this->request->getParams();
        //$this->log->notice(var_export($data, true));
        try {
            // If the ArticleMapper does not exist already, it gets created via the
            // ArticleMapperFactory. This factory ties the article mapper to the
            // applications event manager. The ArticleMapper object lives in
            // the service manager only. It's operation gets triggered via
            // events only.
            $this->services->get(ArticleMapper::class);
            parent::updateAction();

            // Here all Doctrine lifecycle events are completed so we can
            // savely work with Doctrine again
            $this->services->get('events')->trigger('process_active_states', $this, []);;
        } catch (Throwable $e) {
            $this->log->except($e);
        }
        $this->log->leave();
    }

    public function importAction()
    {
        $this->log->enter();
        try {
            $sm = $this->services->get(SchemaManager::class);
            $client = $this->services->get(InnocigsClient::class);

            // $client->createConfiguratorConfiguration();

            // drop all database tables and remove all attributes
            // created by this plugin
            $sm->drop();
            // recreate database tables and attributes
            $sm->create();
            // import items from InnoCigs
            $client->import();
        } catch (Throwable $e) {
            $this->log->except($e);
        }
        $this->log->leave();
    }

    public function filterAction() {
        $this->log->enter();
        try {
            $importModifier = $this->services->get(ImportModifier::class);
            $importModifier->apply();
        } catch (Throwable $e) {
            $this->log->except($e);
        }
        $this->log->leave();
    }

    public function synchronizeAction() {
        $this->log->enter();
        $this->services->get(InnocigsUpdater::class);
        $this->log->leave();
    }

    protected function getAdditionalDetailData(array $data) {
        $data['variants'] = [];
        return $data;
    }

    public function save($data) {
        /* @var \Shopware\Components\Model\ModelEntity $storedModel */
        if (! empty($data['id'])) {
            $storedModel = $this->getRepository()->find($data['id']);
        } else {
            throw new RuntimeException('Creation of new instances of InnocigsArticle via GUI is not supported.');
        }
        if (isset($data['variants']) && empty($data['variants'])) {
            unset($data['variants']);
        }
        /** @var InnocigsArticle $updatedModel; */
        $updatedModel = new $this->model();
        $rdata = $this->resolveExtJsData($data);
        $updatedModel->fromArray($rdata);
        $uActive = $updatedModel->isActive();
        $sActive = $storedModel->isActive();
        if (! $uActive || $uActive === $sActive) {
            return parent::save($data);
        }
        $entityValidator = $this->services->get(InnocigsEntityValidator::class);
        if (! $entityValidator->validateArticle($updatedModel)) {
            return ['success' => false, 'message' => 'Article not activated because it does not have any accepted variant.'];
        }
        $this->log->debug(var_export($data, true));
        return parent::save($data);
    }
}
