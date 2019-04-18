<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace MxcDropshipInnocigs\Import;

use DateTime;
use DOMDocument;
use DOMElement;
use Exception;
use Mxc\Shopware\Plugin\Service\LoggerInterface;
use MxcDropshipInnocigs\Exception\ApiException;
use Zend\Http\Client;
use Zend\Http\Exception\RuntimeException as ZendClientException;
use Zend\Http\Response;

class ApiClient
{
    /**
     * @var string $apiEntry
     */
    protected $apiEntry;

    /**
     * @var string $authUrl
     */
    protected $authUrl;

    /**
     * @var Client $client
     */
    protected $client = null;

    /** @var LoggerInterface $log */
    protected $log;

    public function __construct(Credentials $credentials, LoggerInterface $log)
    {
        $this->log = $log;
        $this->apiEntry = 'https://www.innocigs.com/xmlapi/api.php';
        $this->authUrl = $this->apiEntry . '?cid=' . $credentials->getUser() . '&auth=' . $credentials->getPassword();
    }

    /**
     * @param string $model
     * @return array
     */
    public function getItemInfo($model)
    {
        $cmd = $this->authUrl . "&command=product&model=" . $model;
        return $this->modelsToArray($this->send($cmd)->getBody());
    }

    /**
     * @return array
     */
    public function getItemList()
    {
        $cmd = $this->authUrl . '&command=products&type=extended';
        return $this->modelsToArray($this->send($cmd)->getBody());
    }

    /**
     * @param DateTime $date
     * @return array
     * @throws Exception
     */
    public function getTrackingData($date = null)
    {
        if (!$date instanceof DateTime) {
            $date = (new DateTime())->format('Y-m-d');
        }
        $cmd = $this->authUrl . '&command=tracking&day=' . $date;
        return $this->xmlToArray($this->send($cmd)->getBody());
    }

    public function getStockInfo(string $model)
    {
        $cmd = $this->authUrl . '&command=quantity&model=' . urlencode($model);
        $data = $this->xmlToArray($this->send($cmd)->getBody());
        return $data['QUANTITIES']['PRODUCT']['QUANTITY'];
    }

    public function getAllStockInfo() {
        $cmd = $this->authUrl . '&command=quantity_all';
        return $this->xmlToArray($this->send($cmd)->getBody());
    }

    /**
     * @param string $cmd
     * @return Response
     */
    protected function send($cmd)
    {
        $client = $this->getClient();
        $client->setUri($cmd);
        try {
            $response = $client->send();
            if (! $response->isSuccess()) {
                throw new ApiException('HTTP status: ' . $response->getStatusCode());
            }
            return $client->send();
        } catch (ZendClientException $e) {
            // no response or response empty
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * @return Client
     */
    protected function getClient()
    {
        if (null === $this->client) {
            $this->client = new Client(
                "",
                [
                    'maxredirects' => 0,
                    'timeout'      => 30,
                    'useragent'    => 'maxence Dropship',
                ]
            );
        }
        return $this->client;
    }
    protected function checkXmlResult(string $xml)
    {
        if (strpos($xml, '<ERRORS>') !== false) {
            $this->xmlToArray($xml);
        }
    }

    protected function checkArrayResult(array $response)
    {
        $error = $response['ERRORS']['ERROR'];
        if ($error) {
            throw new ApiException('InnoCigs API: <br/>'.$error['MESSAGE']);
        }
    }

    public function modelsToArray(string $xml): array
    {
        $this->checkXmlResult($xml);
        $this->logXML($xml);
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $models = $dom->getElementsByTagName('PRODUCT');
        /** @var DOMElement $model */
        $import = [];
        foreach ($models as $model) {
            $item = [];
            $item['category']                   = $model->getElementsByTagName('CATEGORY')->item(0)->nodeValue;
            $item['model']                      = $model->getElementsByTagName('MODEL')->item(0)->nodeValue;
            $item['master']                     = $model->getElementsByTagName('MASTER')->item(0)->nodeValue;
            $item['ean']                        = $model->getElementsByTagName('EAN')->item(0)->nodeValue;
            $item['name']                       = $model->getElementsByTagName('NAME')->item(0)->nodeValue;
            $item['purchasePrice']              = $model->getElementsByTagName('PRODUCTS_PRICE')->item(0)->nodeValue;
            $item['recommendedRetailPrice']     = $model->getElementsByTagName('PRODUCTS_PRICE_RECOMMENDED')->item(0)->nodeValue;
            $item['manufacturer']               = $model->getElementsByTagName('MANUFACTURER')->item(0)->nodeValue;
            $item['manual']                     = $model->getElementsByTagName('PRODUCTS_MANUAL')->item(0)->nodeValue;
            $item['description']                = $model->getElementsByTagName('DESCRIPTION')->item(0)->nodeValue;
            $item['image']                      = $model->getElementsByTagName('PRODUCTS_IMAGE')->item(0)->nodeValue;
            $attributes                         = $model->getElementsByTagName('PRODUCTS_ATTRIBUTES')->item(0)->childNodes;
            $addlImages                         = $model->getElementsByTagName('PRODUCTS_IMAGE_ADDITIONAL')->item(0)->childNodes;
            $item['images']         = [];

            /** @var DOMElement $attribute */
            foreach ($attributes as $attribute) {
                if (! $attribute instanceof DOMElement) continue;
                $tagName = @$attribute->tagName;
                if ($tagName !== null) {
                    $item['options'][$tagName] = $attribute->nodeValue;
                }
            }
            /** @var DOMElement $image */
            foreach ($addlImages as $image) {
                if (! $attribute instanceof DOMElement) continue;
                $tagName = @$image->tagName;
                if ($tagName !== null) {
                    $item['images'][] = $image->nodeValue;
                }
            }
            $import[$item['master']][$item['model']] = $item;
        }
        return $import;
    }

    /**
     * @param string $xml
     * @return array
     */
    public function xmlToArray(string $xml): array
    {
        $this->logXML($xml);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml, 'SimpleXmlElement', LIBXML_NOERROR | LIBXML_NOWARNING);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $this->logXmlErrors($errors);
            $dump = Shopware()->DocPath() . 'var/log/invalid-innocigs-api-response-' . date('Y-m-d-H-i-s') . '.txt';
            file_put_contents($dump, $xml);
            $this->log->info('Invalid InnoCigs API response dumped to ' . $dump);
            throw new ApiException('InnoCigs API returned invalid XML. See log file for detailed information.');
        }
        $json = json_encode($xml);
        if ($json === false) {
            throw new ApiException('Failed to encode to JSON: ' . var_export($xml, true));
        }
        $result = json_decode($json, true);
        if ($result === false) {
            throw new ApiException('Failed to decode JSON: ' . var_export($json, true));
        }
        $this->checkArrayResult($result);
        return $result;
    }

    protected function logXML($xml)
    {
        $dom = new DOMDocument("1.0", "utf-8");
        $dom->loadXML($xml);
        $dom->formatOutput = true;
        $pretty = $dom->saveXML();

        $reportDir = Shopware()->DocPath() . 'var/log/mxc_dropship_innocigs';
        if (file_exists($reportDir) && !is_dir($reportDir)) {
            unlink($reportDir);
        }
        if (!is_dir($reportDir)) {
            mkdir($reportDir);
        }

        $fn = Shopware()->DocPath() . 'var/log/mxc_dropship_innocigs/api_data.xml';
        file_put_contents($fn, $pretty);
    }

    protected function logXMLErrors(array $errors)
    {
        foreach ($errors as $error) {
            $msg = str_replace(PHP_EOL, '', $error->message);
            $this->log->err(sprintf(
                'XML Error: %s, line: %s, column: %s',
                $msg,
                $error->line,
                $error->column));
        }
    }
}
