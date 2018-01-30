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

    public function generateParcel(Form\ParcelGeneration $form)
    {
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

    public function getParcelStatus($parcel_number, $secret = 'FcJyN7vU7WKPtUh7m1bx')
    {
        $form = Form\ParcelStatus::newInstance();
        $form->setSecret($secret);
        $form->setParcelNumber($parcel_number);
        $this->validateForm($form);

        $url = 'parcel_status.php';
        $json = $this->request($url, $form, 'POST', array('content-type' => 'application/x-www-form-urlencoded'));
        $json = array_merge(array(
            'parcel_status' => 'Unknown error'
        ), $json);

        return !empty($json['errmsg']) ? $json['errmsg'] : $json['parcel_status'];
    }

    public function getParcelLabels($parcels)
    {
        $form = Form\ParcelLabel::newInstance();

        $form->setUsername($this->config['username']);
        $form->setPassword($this->config['password']);
        //Parcel number list, separated with ‘|’. Example: 16401234567890|16401234567891|16401122334455
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
        //return "https://tracking.dpd.de/cgi-bin/delistrack?pknr=$parcel_number&lang=$language";
        return "https://tracking.dpd.de/parcelstatus?query=$parcel_number&locale=$language";
    }

    /**
     * @param Form\ParcelDelete $form
     *
     * @throws Exception
     *
     * @return array
     */
    public function deleteParcel(Form\ParcelDelete $form)
    {
        $this->validateForm($form);

        $uri = 'parcel_delete.php';
        $json = $this->request($uri, $form, 'POST', array('content-type' => 'application/x-www-form-urlencoded'));

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
     * @return Client
     */
    public function transferData()
    {
        $form = Form\ParcelTransferData::newInstance();
        $form->setUsername($this->config['username']);
        $form->setPassword($this->config['password']);

        $this->validateForm($form);

        $uri = 'parcel_datasend.php';
        $json = $this->request($uri, $form, 'POST', array('content-type' => 'application/x-www-form-urlencoded'));
        if ('ok' != $json['status']) {
            throw new Exception($json['errlog'], json_encode($json));
        }
        return $json['parcels'];
    }

    protected function validateForm(Form $form)
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $violations = $validator->validate($form);
        if ($violations->count()) {
            throw new Exception\Validation($violations);
        }

        return $form;
    }

    protected function requestJsonLabel($url, $data = array(), $method = 'POST', array $headers = array('content-type' => 'application/x-www-form-urlencoded'))
    {
        $content = $this->request($url, $data, $method, $headers);
        $json = $this->request($url, $data, $method, $headers);
        if (!$json) {
            if (substr($content, 0, 4) == '%PDF') {
                $json = array('status' => 'ok', 'errlog' => null, 'pdf' => $content);
            } else {
                $json = array('errlog' => $content, 'status' => 'err');
            }
        }
        return $json;
    }

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
