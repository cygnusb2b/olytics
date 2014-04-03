<?php
namespace Cygnus\ApiSuiteBundle\ApiClient\Omeda;

use 
    Cygnus\ApiSuiteBundle\ApiClient\ApiClientInterface,
    Cygnus\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface,
    Symfony\Component\HttpFoundation\ParameterBag,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response
    \DateTime;

class ApiClientOmeda implements ApiClientInterface
{
    /**
     * The remote HttpKernel for sending Request objects and receiving Response objects
     *
     * @var Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $httpKernel;

    /**
     * An array of request methods that this API supports
     *
     * @var array
     */
    protected $supportedMethods = ['GET', 'POST', 'PUT', 'DELETE'];

    /**
     * An array of request methods that this API deems as 'modifying'
     *
     * @var array
     */
    protected $modifyingMethods = ['POST', 'PUT', 'DELETE'];

    /**
     * An array of required configuration options
     *
     * @var array
     */
    protected $requiredConfigOptions = ['host', 'client', 'brand', 'appid', 'inputid'];

    /**
     * Constructor. Sets the configuration for this Omeda API client instance
     *
     * @param  array $config The config options
     * @return void
     */
    public function __construct(array $config = array())
    {
        $this->setConfig($config);
    }

    /**
     * Performs a Customer Lookup by ID
     * https://wiki.omeda.com/wiki/en/Customer_Lookup_Service_By_CustomerId
     *
     * @param  int $customerId The Omeda CustomerId to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerLookupById($customerId) 
    {
        $endpoint = '/customer/' . $customerId . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Customer Lookup by Encrypted ID
     * https://wiki.omeda.com/wiki/en/Customer_Lookup_Service_By_EncryptedCustomerId
     *
     * @param  string $encryptedId The Omeda EncryptedCustomerId to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerLookupByEncryptedId($encryptedId) 
    {
        $endpoint = '/customer/' . $encryptedId . '/encrypted/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Customer Lookup by Email Address
     * https://wiki.omeda.com/wiki/en/Customer_Lookup_Service_By_Email
     *
     * @param  string $email The Email Address to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerLookupByEmail($email)
    {
        $endpoint = '/customer/email/' . $email . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Comprehensive Customer Lookup
     * https://wiki.omeda.com/wiki/en/Customer_Comprehensive_Lookup_Service
     *
     * @param  int $customerId The Omeda CustomerId to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerComprehensiveLookup($customerId)
    {
        $endpoint = '/customer/' . $customerId . '/comp/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Saves Customer Data, including Order and Behavior information
     * https://wiki.omeda.com/wiki/en/Save_Customer_and_Order_API
     *
     * @param  array|string $requestBody The request body to send to the API
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerSave($requestBody)
    {
        $endpoint = '/storecustomerandorder/*';
        return $this->handleRequest($endpoint, $requestBody, 'POST');
    }

    /**
     * Performs a Customer Transaction Lookup by ID
     * https://wiki.omeda.com/wiki/en/Transaction_Lookup_Service
     *
     * @param  int $transactionId The transaction ID to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerTransactionLookup($transactionId) 
    {
        $endpoint = '/transaction/' . $transactionId . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Customer Behavior Lookup
     * https://wiki.omeda.com/wiki/en/Customer_Behavior_API
     *
     * @param  int $customerId The Omeda CustomerId to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerBehaviorLookup($customerId) 
    {
        $endpoint = '/customer/' . $customerId . '/behavior/*';  
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Customer Behavior Lookup by a Specific Behavior ID
     * https://wiki.omeda.com/wiki/en/Customer_Behavior_API
     *
     * @param  int $customerId The Omeda CustomerId to lookup
     * @param  int $behaviorId The Omeda BehaviorId to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerBehaviorLookupByBehavior($customerId, $behaviorId) 
    {
        $endpoint = '/customer/' . $customerId . '/behavior/' . $behaviorId . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Customer Behavior Lookup by a Specific Product ID
     * https://wiki.omeda.com/wiki/en/Customer_Behavior_API
     *
     * @param  int $customerId The Omeda CustomerId to lookup
     * @param  int $productId  The Omeda ProductId to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerBehaviorLookupByProduct($customerId, $productId) 
    {
        $endpoint = '/customer/' . $customerId . '/behavior/product/' . $productId . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Assigns behavior to a cusomter
     * https://wiki.omeda.com/wiki/en/Behavior_Assign_API
     *
     * @param  array|string $requestBody The request body to send to the API
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerAssignBehavior($requestBody)
    {
        $endpoint = '/assignbehavior/*';
        return $this->handleRequest($endpoint, $requestBody, 'POST');
    }

    /**
     * Customer Change Lookup Service
     * https://wiki.omeda.com/wiki/en/Customer_Change_Lookup_Service
     * Start and end date can be a date/time string, a timestamp, or an instance of DateTime
     *
     * @param mixed $startDate The start date (inclusive)
     * @param mixed $endDate   The end date (inclusive)
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function customerChangeLookup($startDate, $endDate)
    {
        $startDate = $this->formatDate($startDate, 'mdY_Hi');
        $endDate   = $this->formatDate($endDate, 'mdY_Hi');
        $endpoint = '/customer/change/startdate/' . $startDate . '/enddate/' . $endDate . '/*';
        return $this->handleRequest($endpoint);
    }


    /**
     * Performs a Brand Comprehensive Lookup
     * https://wiki.omeda.com/wiki/en/Brand_Comprehensive_Lookup_Service
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function brandComprehensiveLookup() 
    {
        $endpoint = '/comp/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Brand Behavior Lookup
     * https://wiki.omeda.com/wiki/en/Behavior_API
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function brandBehaviorLookup()
    {
        $endpoint = '/behavior/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Brand Behavior Lookup by Behavior ID
     * https://wiki.omeda.com/wiki/en/Behavior_Lookup_By_Id
     *
     * @param  int $behaviorId The Omeda BehaviorId to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function brandBehaviorLookupById($behaviorId)
    {
        $endpoint = '/behavior/' . $behaviorId . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Brand Behavior Lookup by Product
     * https://wiki.omeda.com/wiki/en/Behavior_Lookup_By_Product
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function brandBehaviorLookupByProduct()
    {
        $endpoint = '/behavior/byproduct/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Create Brand Behavior
     * https://wiki.omeda.com/wiki/en/Store_Behavior_API
     *
     * @param  int    $actionId    Behavior Action Identifier - all behaviors must belong to a behavior action, which is predefined in the database
     * @param  string $description Description of the Behavior
     * @param  string $alternateId An id that can be used to uniquely identify this behavior
     * @param  int    $productId   Links the Behavior to a specific Product defined in the database
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function brandBehaviorCreate($actionId, $description, $alternateId = null, $productId = null)
    {
        $endpoint = '/behavior/*';

        $requestBody = array(
            'ActionId'      => (int) $actionId,
            'Description'   => $description,
        );
        if (is_scalar($alternateId)) $requestBody['AlternateId'] = (string) $alternateId;
        if (is_scalar($productId))   $requestBody['ProductId']   = (int) $product_id;

        return $this->handleRequest($endpoint, $requestBody, 'POST');
    }

    /**
    * Update Brand Behavior
    * https://wiki.omeda.com/wiki/en/Store_Behavior_API
    *
    * @param  int $behaviorId      Behavior Identifier
    * @param  string $alternateId  An id that can be used to uniquely identify this behavior
    * @param  int $statusCode      Only allowed when doing an update. "0" to deactivate, "1" to activate
    * @return Symfony\Component\HttpFoundation\Response
    */
    public function brandBehaviorUpdate($behaviorId, $alternateId = null, $statusCode = null)
    {
        $endpoint = '/behavior/*';
        
        $requestBody = array(
            'Id'    => (int) $behaviorId,
        );

        if (in_array((int) $statusCode, [0, 1])) {
            $requestBody['StatusCode'] = (int) $statusCode;
        }
        if (is_scalar($alternateId)) $requestBody['AlternateId'] = (string) $alternateId;

        return $this->handleRequest($endpoint, $requestBody, 'PUT');
    }

    /**
     * Performs a Brand Behavior Actions Lookup
     * https://wiki.omeda.com/wiki/en/Behavior_Actions_API
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function brandBehaviorActionsLookup()
    {
        $endpoint = '/behavior/action/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs a Brand Behavior Categories Lookup
     * https://wiki.omeda.com/wiki/en/Behavior_Categories_API
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function brandBehaviorCategoriesLookup()
    {
        $endpoint = '/behavior/category/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Performs an Opt-In / Opt-Out Lookup
     * https://wiki.omeda.com/wiki/en/Opt_In/Out_Lookup_Service
     *
     * @param  string $email The Email Address to lookup
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function omailOptinOptoutLookup($email)
    {
        $endpoint = '/filter/email/' . $email . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * Sends Optin Information to the Omail Filter
     * https://wiki.omeda.com/wiki/en/Optin_Queue_Service
     *
     * @param  array|string $requestBody The request body to send to the API
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function omailOptinSend($requestBody)
    {
        $endpoint = '/optinfilterqueue/*';
        return $this->handleRequest($endpoint, $requestBody, 'POST', true);
    }
   

    /**
     * Sends Optout Information to the Omail Filter
     * https://wiki.omeda.com/wiki/en/Optout_Queue_Service
     *
     * @param  array|string $requestBody The request body to send to the API
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function omailOptoutSend($requestBody)
    {
        $endpoint = '/optoutfilterqueue/*';
        return $this->handleRequest($endpoint, $requestBody, 'POST', true);
    }

    /**
     * The Deployment Lookup API provides the ability to retrieve deployment information such as link tracking, delivery statistics, deployment status, history, etc.
     * https://wiki.omeda.com/wiki/en/Deployment_Lookup_Resource
     *
     * @param  string $trackId The Omail TrackId for the desired deployment
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function omailDeploymentLookup($trackId)
    {
        $endpoint = '/omail/deployment/lookup/' . $trackId . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * The Deployment Lookup API provides the ability to retrieve deployment split content
     *
     * @param  string $trackId  The Omail TrackId for the desired deployment
     * @param  int    $sequence The split sequence number
     * @param  string $type     The content type of the split: text or html
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function omailDeploymentContentLookup($trackId, $sequence, $type = 'html')
    {
        $endpoint = '/omail/deployment/content/lookup/' . $type . '/' . $trackId . '/' . $sequence . '/*';
        return $this->handleRequest($endpoint);
    }

    /**
     * This service retrieves a list of most recent deployments for a given brand based on search parameters.
     * https://wiki.omeda.com/wiki/en/Deployment_Search_Resource
     *
     * @param  array|string $requestBody The request body to send to the API
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function omailDeploymentSearch($params) 
    {
        $endpoint = '/omail/deployment/search/*';
        return $this->handleRequest($endpoint, $requestBody, 'POST');
    }

    /**
     * Sets the configuration options for this API client
     * host: e.g. ows.omeda.com; brand: e.g. avvdb
     * appid: the assigned id for reading; inputid: the assigned id for writing
     *
     * @param  array $config The config options
     * @return self
     */
    public function setConfig(array $config) 
    {
        $this->config = new ParameterBag($config);
        return $this;
    }

    /**
     * Sets the remote RemoteKernelInterface for sending Request objects and returning Response objects
     *
     * @param  Cygnus\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface $httpKernel
     * @return self
     */
    public function setRemoteHttpKernel(RemoteKernelInterface $httpKernel)
    {
        $this->httpKernel = $httpKernel;
        return $this;
    }

    /**
     * Handles a request by creating a Request object and sending it to the Kernel
     *
     * @param  string $endpoint   The API endpoint
     * @param  mixed  $content    The request body content to use
     * @param  string $method     The request method
     * @param  bool   $clientCall Whether this is an API that applies to the entire customer/client
     * @return Symfony\Component\HttpFoundation\Response
     */
    protected function handleRequest($endpoint, $content = null, $method = 'GET', $clientCall = false)
    {
        $request = $this->createRequest($endpoint, $content, $method, $clientCall);
        return $this->doRequest($request);
    }

    /**
     * Takes a Request object and performs the request via the HttpKernelInterface
     * This should return a Response object
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function doRequest(Request $request)
    {
        return $this->httpKernel->handle($request);
    }

    /**
     * Creates a new Request object based on API method parameters
     * This should return a Response object
     *
     * @param  string $endpoint   The API endpoint
     * @param  mixed  $content    The request body content to use
     * @param  string $method     The request method
     * @param  bool   $clientCall Whether this is an API that applies to the entire customer/client 
     * @return Symfony\Component\HttpFoundation\Request
     * @throws \Exception If the API configuration is invalid, or a non-allowed request method is passed
     */
    protected function createRequest($endpoint, $content = null, $method = 'GET', $clientCall = false)
    {
        if ($this->hasValidConfig()) {

            $method = strtoupper($method);
            if (!in_array($method, $this->supportedMethods))  {
                // Request method not allowed by the API
                throw new \Exception(sprintf('The request method %s is not allowed. Only %s methods are supported.'), $method, implode(', ', $this->supportedMethods));
            }

            // Handle the request body content
            if (is_scalar($content)) {
                $content = (string) $content;
            } elseif (is_array($content)) {
                $content = @json_encode($content);
            }

            // Create initial request object
            $request = $this->httpKernel->createSimpleRequest($this->getUri($endpoint, $clientCall), $method, array(), $content);

            // Set default headers
            $headers = array('x-omeda-appid' => $this->getAppId());

            // Add additional headers based on request method
            if (in_array($method, $this->modifyingMethods)) {
                $headers['x-omeda-inputid'] = $this->getInputId();
                $headers['Content-Type'] = 'application/json';
            }

            // Add the headers to the request
            $request->headers->add($headers);

            return $request;
        } else {
            throw new \Exception(sprintf('The Omeda API configuration is not valid. The following options must be set: %s', implode(', ', $this->requiredConfigOptions)));
        }
    }

    /**
     * Formats a date value based on a specified format string
     *
     * @param  mixed  The date value
     * @param  string The format string
     * @return string The formatted date
     */
    protected function formatDate($value, $format)
    {
        if ($value instanceof DateTime) {
            return $value->format($format);
        }

        $date = new DateTime();

        if (is_numeric($value)) {
            $date->setTimestamp($value);
        } elseif (is_string($value)) {
            $date->setTimestamp(strtotime($value));
        }
        return $date->format($format);
    }

    /**
     * Determines if the API instance has a valid configuration
     *
     * @return bool
     */
    public function hasValidConfig()
    {
        foreach ($this->requiredConfigOptions as $option) {
            if (!$this->config->has($option)) return false;
        }
        return true;
    }

    /**
     * Gets the full request URI based on an API endpoint
     *
     * @param  string $endpoint The API endpoint
     * @param  bool   $clientCall Whether this is an API that applies to the entire customer/client
     * @return string The request URI
     */
    public function getUri($endpoint = null, $clientCall = false)
    {
        if ($clientCall === true) {
            $uri = 'https://' . $this->getHost() . '/webservices/rest/client/' . $this->getClient();
        } else {
            $uri = 'https://' . $this->getHost() . '/webservices/rest/brand/' . $this->getBrand();
        }
        
        // Add the API endpoint, if sent
        if (!is_null($endpoint)) {
            $uri .= rtrim($endpoint, '/');
        }
        return $uri;
    }

    /**
     * Gets the API hostname
     *
     * @return string
     */
    public function getHost()
    {
        return trim($this->config->get('host'), '/');
    }

    /**
     * Gets the client/customer
     *
     * @return string
     */
    public function getClient()
    {
        return $this->config->get('client');
    }

    /**
     * Gets the brand
     *
     * @return string
     */
    public function getBrand()
    {
        return $this->config->get('brand');
    }

    /**
     * Gets the App ID for reading
     *
     * @return string
     */
    public function getAppId()
    {
        return $this->config->get('appid');
    }

    /**
     * Gets the Input ID for writing
     *
     * @return string
     */
    public function getInputId()
    {
        return $this->config->get('inputid');
    }
}
