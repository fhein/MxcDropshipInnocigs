<?php

namespace MxcDropshipIntegrator\Dropship;

use MxcCommons\Plugin\Plugin;
use MxcCommons\Plugin\Service\ClassConfigAwareInterface;
use MxcCommons\Plugin\Service\ClassConfigAwareTrait;
use MxcCommons\Plugin\Service\DatabaseAwareInterface;
use MxcCommons\Plugin\Service\DatabaseAwareTrait;
use MxcCommons\Plugin\Service\ModelManagerAwareInterface;
use MxcCommons\Plugin\Service\ModelManagerAwareTrait;
use MxcDropshipIntegrator\Exception\InvalidArgumentException;
use MxcDropshipIntegrator\MxcDropshipIntegrator;
use MxcDropshipInnocigs\MxcDropshipInnocigs;

class DropshipManager implements ClassConfigAwareInterface, ModelManagerAwareInterface, DatabaseAwareInterface
{
    use ClassConfigAwareTrait;
    use ModelManagerAwareTrait;
    use DatabaseAwareTrait;

    protected $automaticOrderProcessing = true;

    // constants for all available modules
    const SUPPLIER_INNOCIGS = 1;
    const SUPPLIER_DEMO = 2;

    protected $modules = [];

    public function init()
    {
        $moduleConfigs = @$this->classConfig['modules'] ?? [];
        $modules = [];
        foreach ($moduleConfigs as $supplierId => $module) {
            $v = @$module['namespace'];
            if ($v === null || !is_string($v)) {
                continue;
            }
            $v = @$module['name'];
            if ($v === null || !is_string($v)) {
                continue;
            }

            // do not register adapters which are not present and active
            $plugin = @$module['plugin'];
            if ($plugin === null || !is_string($plugin)) {
                continue;
            }
            if (!$this->db->fetchOne('SELECT active FROM s_core_plugins WHERE name = ?', [$plugin])) {
                continue;
            }

            $class = $plugin . '\\' . $plugin;
            if (!class_exists($class)) {
                continue;
            }
            if (!method_exists($class, 'getServices')) {
                continue;
            }

            // we eagerly load the services management of all active modules because we need them anyway
            $module['service_manager'] = $services = call_user_func($class . '::getServices');

            // services cache
            $module['services'] = [];

            // additional checks could be applied here later

            // at this point we have a properly configured active dropship adapter module
            $this->modules[$supplierId] = $module;
        }
    }

    public function getService(int $supplierId, string $service)
    {
        $module = $this->modules[$supplierId];
        if ($module === null) return null;

        $className = sprintf('%s\\%s',
            $module['namespace'],
            $service
        );

        $service = $module['services'][$className] ?? $module['service_manager']->get($className);
        $this->modules[$supplierId][$className] = $service;

        return $service;
    }

    public function getStockInfo($sArticle)
    {
        // ask each dropship adapter about the # of items in stock
        $stockData = [];
        foreach ($this->modules as $supplierId => $module) {
            $stockInfo = $this->getService($supplierId, 'StockInfo')->getStockInfo($sArticle);

            if (empty($stockInfo)) {
                continue;
            }

            $stockData[] = $stockInfo;
        }
        return $stockData;
    }

    public function isAutomaticOrderProcessing() {
        return $this->automaticOrderProcessing;
    }
}