<?php

namespace Gs\Searchtap\Console\Command;

class SearchTapAPI {

    protected $logger;

    public function searchtapCurlRequest($product_json, $collectionName, $adminKey)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://manage.searchtap.net/v2/collections/" . $collectionName . "/records",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => "",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $product_json,
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json",
                "Authorization: Bearer " . $adminKey
            ),
        ));

        curl_exec($curl);
        $err = curl_error($curl);
        $this->logger->info($err);

        $this->logger->info( "SearchTap API response :: " . curl_getinfo($curl, CURLINFO_HTTP_CODE) );

        curl_close($curl);

//        if ($err) {
//            Mage::log("Exception occurred", null, $this->log_file_name);
//        }
    }

    public function searchtapCurlDeleteRequest($productIds, $collectionName, $adminKey)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtap.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);

        $curl = curl_init();

        if(count($productIds) == 0)
            return;

//        $data_json = json_encode($productIds);

        foreach ($productIds as $id) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://manage.searchtap.net/v2/collections/" . $collectionName . "/records/" . $id,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CAINFO => "",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/json",
                    "Authorization: Bearer " . $adminKey
                ),
            ));
        }

        $exec = curl_exec($curl);

        $err = curl_error($curl);

        $this->logger->info($err);

        $result = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $this->logger->info("SearchTap Delete API response :: " . $result);

        curl_close($curl);

//        if ($err) {
//            Mage::log("Exception occurred", null, $this->log_file_name);
//        }
        return;
    }
}