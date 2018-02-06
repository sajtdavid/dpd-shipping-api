<?php

namespace DPD;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\Validator\Validation;
use GuzzleHttp\Client;
use Symfony\Component\OptionsResolver\OptionsResolver;
use DPD\Exception\RequestErrorException;
use DPD\Exception\JSONParseException;

// Validation autoloading
AnnotationRegistry::registerLoader(function ($name) {
    return class_exists($name);
});
class API
{
    private $client;
    private $config;

    /**
     * Request constructor.
     * @param $options
     */
    public function __construct($options)
    {
        $this->config = $this->resolveOptions($options);
        $config_array = [
            'verify' => false,
            'base_uri' => $this->config['url'],
            'debug' => false
        ];
        if (!empty($this->config['log_dir'])) {
            $config_array['handler'] = $this->createLoggingHandlerStack($this->config['log_msg_format']);
        }

        $this->client = new Client($config_array);
    }

    /**
     * Get required options for the DPD API to work
     * @param $opts
     * @return mixed
     */
    protected function resolveOptions($opts)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('url', 'https://weblabel.dpd.hu/dpd_wow/');
        $resolver->setDefault('log_dir', '');
        $resolver->setDefault('log_msg_format', ['{method} {uri} HTTP/{version} {req_body}','RESPONSE: {code} - {res_body}',]);
        $resolver->setRequired(['url', 'username', 'password']);
        return $resolver->resolve($opts);
    }

    /**
     * @param array $parcel_data
     *
     * @throws Exception\ParcelGeneration
     * @throws Exception\Validation
     * @throws Exception\RequestErrorException
     * @throws Exception\JSONParseException
     *
     * @return string
     */
    public function generateParcel($parcel_data)
    {
        $form = Form\ParcelGeneration::newInstance();
        $form->setName1($parcel_data['setName1']);
        $form->setName2($parcel_data['setName2']);
        $form->setStreet($parcel_data['setStreet']);
        $form->setCity($parcel_data['setCity']);
        $form->setCountry($parcel_data['setCountry']);
        $form->setPcode($parcel_data['setPcode']);
        $form->setWeight($parcel_data['setWeight']);
        $form->setOrderNumber($parcel_data['setOrderNumber']);
        $form->setNumOfParcel($parcel_data['setNumOfParcel']);
        $form->setParcelType($parcel_data['setParcelType']);
        $form->setCodAmount($parcel_data['setCodAmount']);
        $form->setCodPurpose($parcel_data['setCodPurpose']);
        $form->setEmail($parcel_data['setEmail']);
        $form->setPhone($parcel_data['setPhone']);
        $form->setSMSNumber($parcel_data['setSMSNumber']);
        $form->setRemark($parcel_data['setRemark']);

        $form->setUsername($this->config['username']);
        $form->setPassword($this->config['password']);

        $this->validateForm($form);
        $uri = 'parcel_import.php';
        $json = $this->request($uri, $form, 'POST', array('content-type' => 'application/x-www-form-urlencoded'));
        $json = array_merge(array(
            'status'    => 'ok',
            'errlog'    => null,
            'pl_number' => array()
        ), $json);
        if ('ok' != $json['status']) {
            throw new Exception\ParcelGeneration($json['errlog'], json_encode($json));
        }

        return $json['pl_number'][0];
    }

    /**
     * @param string $parcel_number
     *
     * @throws Exception\Validation
     * @throws Exception\RequestErrorException
     * @throws Exception\JSONParseException
     *
     * @return string
     */
    public function getParcelStatus($parcel_number, $secret = 'FcJyN7vU7WKPtUh7m1bx')
    {
        $form = Form\ParcelStatus::newInstance();
        $form->setSecret($secret);
        $form->setParcelNumber($parcel_number);
        $this->validateForm($form);

        $url = 'parcel_status.php';
        $json = $this->request($url, $form);
        $json = array_merge(array(
            'parcel_status' => 'Unknown error'
        ), $json);

        return !empty($json['errmsg']) ? $json['errmsg'] : $json['parcel_status'];
    }

    /**
     * @param mixed $parcels
     *
     * @throws Exception\Validation
     * @throws Exception\RequestErrorException
     * @throws Exception\JSONParseException
     *
     * @return array
     * $json['pdf'] is streamed labels. Use echo and correct pdf header to display
     */
    public function getParcelLabels($parcels)
    {
        $form = Form\ParcelLabel::newInstance();

        $form->setUsername($this->config['username']);
        $form->setPassword($this->config['password']);
        $form->setParcels($parcels);

        $this->validateForm($form);

        $url = 'parcel_print.php';
        $json = $this->requestJsonLabel($url, $form);
        $json = array_merge(array(
            'status' => 'ok',
            'errlog' => null,
            'pdf'    => ''
        ), $json);

        return $json;
    }

    public function getTrackingUrl($parcel_number, $language = 'hu_HU')
    {
        return "https://tracking.dpd.de/parcelstatus?query=$parcel_number&locale=$language";
    }

    /**
     * @param mixed $parcels
     *
     * @throws Exception
     * @throws Exception/JSONParseException
     * @throws Exception/RequestErrorException
     *
     * @return array
     */
    public function deleteParcel($parcels)
    {
        $form = Form\ParcelLabel::newInstance();

        $form->setUsername($this->config['username']);
        $form->setPassword($this->config['password']);
        $form->setParcels($parcels);

        $this->validateForm($form);

        $uri = 'parcel_delete.php';
        $json = $this->request($uri, $form);

        $json = array_merge(array(
            'status' => 'ok',
            'errlog' => null
        ), $json);

        if ('ok' != $json['status']) {
            throw new Exception($json['errlog'], json_encode($json));
        }

        return $json;
    }

    /**
     * @param
     *
     * @throws Exception
     * @throws Exception/JSONParseException
     * @throws Exception/RequestErrorException
     *
     * @return integer
     */
    public function transferData()
    {
        $form = Form\ParcelTransferData::newInstance();
        $form->setUsername($this->config['username']);
        $form->setPassword($this->config['password']);

        $this->validateForm($form);

        $uri = 'parcel_datasend.php';
        $json = $this->request($uri, $form);
        if ('ok' != $json['status']) {
            throw new Exception($json['errlog'], json_encode($json));
        }
        return $json['parcels'];
    }

    /**
     * @param Form $form
     *
     * @throws Exception\Validation
     *
     * @return Form $form
     */
    protected function validateForm(Form $form)
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($form);
        if ($violations->count()) {
            throw new Exception\Validation($violations);
        }

        return $form;
    }

    /**
     * @param array
     *
     * @throws JSONParseException
     * @throws RequestErrorException
     *
     * @return array
     */
    protected function requestJsonLabel($uri, $data = array(), $method = 'POST', array $headers = array('content-type' => 'application/x-www-form-urlencoded'))
    {
        // transform to array
        if ($data instanceof Form) {
            $data = $data->toArray();
        }

        $response = $this->client->request($method, $uri, ['query' => $data, 'headers' =>$headers]);

        if (substr($response->getBody(), 0, 4) == '%PDF') {
            $json = array('status' => 'ok', 'errlog' => null, 'pdf' => $response->getBody());
        } else {
            $jsonData = json_decode($response->getBody(), true);
            if($jsonData == null) throw new JSONParseException('Cannot decode: ' . $response->getBody());
            if($response->getStatusCode() != 200 || $jsonData['status'] == 'err') {
                throw new RequestErrorException('Error: ' . $jsonData['errlog'], $response->getStatusCode());
            }
            $json = array('errlog' => $response->getBody(), 'status' => 'err');
        }

        return $json;
    }

    /**
     * @param array
     *
     * @throws JSONParseException
     * @throws RequestErrorException
     *
     * @return array
     */
    protected function request($uri, $data=[], $method = 'POST', array $headers = array('content-type' => 'application/x-www-form-urlencoded'))
    {
        // transform to array
        if ($data instanceof Form) {
            $data = $data->toArray();
        }

        $response = $this->client->request($method, $uri, ['query' => $data, 'headers' =>$headers]);

        $jsonData = json_decode($response->getBody(), true);
        if($jsonData == null) throw new JSONParseException('Cannot decode: ' . $response->getBody());
        if($response->getStatusCode() != 200 || $jsonData['status'] == 'err') {
            throw new RequestErrorException('Error: ' . $jsonData['errlog'], $response->getStatusCode());
        }

        return $jsonData;
    }

    /**
     *	Logger functionality: Creates a log file for each day with all requests and responses
     */
    private function getLogger()
    {
        if (empty($this->logger)) {
            $this->logger = with(new \Monolog\Logger('api-consumer'))->pushHandler(
                new \Monolog\Handler\RotatingFileHandler( $this->config['log_dir'] . 'api-dpd-consumer.log')
            );
        }

        return $this->logger;
    }

    private function createGuzzleLoggingMiddleware(string $messageFormat)
    {
        return \GuzzleHttp\Middleware::log(
            $this->getLogger(),
            new \GuzzleHttp\MessageFormatter($messageFormat)
        );
    }

    private function createLoggingHandlerStack(array $messageFormats)
    {
        $stack = \GuzzleHttp\HandlerStack::create();

        collect($messageFormats)->each(function ($messageFormat) use ($stack) {
            // We'll use unshift instead of push, to add the middleware to the bottom of the stack, not the top
            $stack->unshift(
                $this->createGuzzleLoggingMiddleware($messageFormat)
            );
        });

        return $stack;
    }
}