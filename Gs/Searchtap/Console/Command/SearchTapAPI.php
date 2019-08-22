<?php

namespace Gs\Searchtap\Console\Command;

class SearchTapAPI {

    private $collectionName;
    private $adminKey;
    private $applicationId;
    private $apiUrl;
    private $searchAPIUrl;
    protected $logger;

    public function __construct($applicationId, $collectionName, $adminKey)
    {
        $this->applicationId = $applicationId;
        $this->collectionName = $collectionName;
        $this->adminKey = $adminKey;
        $this->apiUrl = "https://manage.searchtap.net/v2/collections/" . $this->collectionName . "/records";
        $this->searchAPIUrl = "https://" . $this->applicationId . "-fast.searchtap.net/v2";

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);
    }

    protected function _getCurlObject($requestType, $data, $operation) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $operation === "search" ? $this->searchAPIUrl : $this->apiUrl,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CAINFO => "",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $requestType,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/json",
                    "Authorization: Bearer " . $this->adminKey
                ),
            ));

            $results = curl_exec($curl);
            $responseHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) $this->logger->info($curlError);
            if ($responseHttpCode != 200 && $responseHttpCode != 201) {
                $this->logger->info('API is not responding : HTTP Code = ' . $responseHttpCode);
                return 0;
            }

            if ($results) {
                if ($operation === "search") {
                    return (json_decode($results)->results);
                }

                $this->logger->info("Unique ID for " . $operation . " operation : " . json_decode($results)->uniqueId);
                return [
                    "uniqueId" => json_decode($results)->uniqueId,
                    "responseHttpCode" => $responseHttpCode
                ];
            }

        } catch (error $error) {
            $this->logger->info($error);
        }

        return [];
    }

    public function searchtapCurlRequest($jsonData)
    {
        return $this->_getCurlObject("POST", $jsonData, "add");
    }

    public function searchtapCurlDeleteRequest($productIds)
    {
        $data = json_encode($productIds);
        return $this->_getCurlObject("DELETE", $data, "delete");
    }

    public function searchtapCurlSearchRequest($count, $skip)
    {
        $data = json_encode(array(
            'collection' => $this->collectionName,
            'fields' => ["id"],
            'count' => $count,
            'skip' => $skip
        ));

        return $this->_getCurlObject("POST", $data, "search");
    }
}