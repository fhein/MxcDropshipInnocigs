<?php

namespace MxcDropshipInnocigs\Excel;

use MxcDropshipInnocigs\Models\Product;

class ImportDosage extends AbstractProductImport
{
    protected function processImportData()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $products = $this->modelManager->getRepository(Product::class)->getAllIndexed();
        foreach ($this->data as $record) {
            /** @var Product $product */
            $product = $products[$record['icNumber']];
            if (! $product) continue;

            $values = explode('-', $record['dosage']);
            $values = array_map('trim', $values);
            $dosage = implode('-', $values);
            $product->setDosage($dosage);
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->modelManager->flush();
    }
}