<?php

use MxcDropshipInnocigs\Application\Application;
use MxcDropshipInnocigs\Models\InnocigsArticle;
use MxcDropshipInnocigs\Mapping\ArticleMapper;

class Shopware_Controllers_Backend_MxcDropshipInnocigs extends \Shopware_Controllers_Backend_Application
{
    protected $model = InnocigsArticle::class;
    protected $alias = 'innocigs_article';

    protected $services;

    public function __construct(
        Enlight_Controller_Request_Request $request,
        Enlight_Controller_Response_Response $response
    ) {
        $this->services = Application::getServices();
        parent::__construct($request, $response);
    }

    public function updateAction()
    {
        // If the ArticleMapper does not exist already, it gets created via the
        // ArticleMapperFactory. This factory ties the article mapper to the
        // applications event manager. The ArticleMapper object lives in
        // the service manager only. It's operation gets triggered via
        // events only.
        $this->services->get(ArticleMapper::class);
        parent::updateAction();
    }
}
