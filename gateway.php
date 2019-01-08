<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

use Http\Discovery\HttpClientDiscovery;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Message\Authentication\BasicAuth;
use Http\Client\Common\PluginClient;
use Http\Message\RequestMatcher\RequestMatcher;
use Http\Client\Common\HttpClientRouter;
use Http\Client\Common\Plugin\ErrorPlugin;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Http\Client\Common\Plugin\ContentLengthPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Http\Message\Formatter;
use Http\Message\Formatter\SimpleFormatter;
use Http\Client\Exception;


class ApiLoggerPlugin implements Plugin
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger, Formatter $formatter = null)
    {
        $this->logger = $logger;
        $this->formatter = $formatter ?: new SimpleFormatter();
    }

    /**
     * @inheritdoc
     */
    public function handleRequest(\Psr\Http\Message\RequestInterface $request, callable $next, callable $first)
    {
        $reqBody = @json_decode($request->getBody(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $reqBody = $request->getBody();
        }

        $this->logger->info(sprintf('Emit request: "%s"', $this->formatter->formatRequest($request)), ['request' => $reqBody]);

        return $next($request)->then(function (ResponseInterface $response) use ($request) {
            $body = @json_decode($response->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $body = $response->getBody();
            }
            $this->logger->info(
                sprintf('Receive response: "%s" for request: "%s"', $this->formatter->formatResponse($response), $this->formatter->formatRequest($request)),
                [
                    'response' => $body,
                ]
            );

            return $response;
        }, function (\Exception $exception) use ($request) {
            if ($exception instanceof Exception\HttpException) {
                $this->logger->error(
                    sprintf('Error: "%s" with response: "%s" when emitting request: "%s"', $exception->getMessage(), $this->formatter->formatResponse($exception->getResponse()), $this->formatter->formatRequest($request)),
                    [
                        'request' => $request,
                        'response' => $exception->getResponse(),
                        'exception' => $exception,
                    ]
                );
            } else {
                $this->logger->error(
                    sprintf('Error: "%s" when emitting request: "%s"', $exception->getMessage(), $this->formatter->formatRequest($request)),
                    [
                        'request' => $request,
                        'exception' => $exception,
                    ]
                );
            }

            throw $exception;
        });
    }
}


class GatewayResponseException extends \Exception {

}

class GatewayService
{
    /**
     * @var GuzzleMessageFactory
     */
    protected $messageFactory;

    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @var HttpClientRouter
     */
    protected $client;

    /**
     * @var string|null
     */
    protected $webhookUrl;

    /**
     * GatewayService constructor.
     * @param string $baseUrl
     * @param string $apiVersion
     * @param string $merchantId
     * @param string $password
     * @param string $webhookUrl
     * @throws \Exception
     */
    public function __construct($baseUrl, $apiVersion, $merchantId, $password, $webhookUrl)
    {
        $this->webhookUrl = $webhookUrl;

        $logger = new Logger('mastercard');
        $logger->pushHandler(new StreamHandler(_PS_ROOT_DIR_.'/var/logs/mastercard.log'));

        $this->messageFactory = new GuzzleMessageFactory();

        $this->apiUrl = 'https://' . $baseUrl . '/api/rest/version/' . $apiVersion . '/merchant/' . $merchantId . '/';

        $username = 'merchant.'.$merchantId;

        $client = new PluginClient(
            HttpClientDiscovery::find(),
            array(
                new ContentLengthPlugin(),
                new HeaderSetPlugin(['Content-Type' => 'application/json;charset=UTF-8']),
                new AuthenticationPlugin(new BasicAuth($username, $password)),
                new ErrorPlugin(),
                new ApiLoggerPlugin($logger),
            )
        );

        $requestMatcher = new RequestMatcher(null, $baseUrl);

        $this->client = new HttpClientRouter();
        $this->client->addClient(
            $client,
            $requestMatcher
        );
    }

    /**
     * Data format is: CART_X.X.X_DEV_X.X.X o e.g MAGENTO_2.0.2_CARTDEV_2.0.0
     * where MAGENTO_2.0.2 represents Magento version 2.0.2 and CARTDEV_2.0.0 represents extension developer CARTDEV and extension version 2.0.0
     *
     * @return string
     */
    protected function getSolutionId()
    {
        return 'PRESTASHOP_'._PS_VERSION_.'_ONTAP_'.MPGS_VERSION;
    }

    /**
     * @param $value
     * @param int $limited
     * @return bool|string|null
     */
    public static function safe($value, $limited = 0)
    {
        if ($value === "") {
            return null;
        }

        if ($limited > 0 && strlen($value) > $limited) {
            return substr($value, 0, $limited);
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function numeric($value)
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param $data
     * @throws GatewayResponseException
     */
    public function validateCheckoutSessionResponse($data)
    {
        if (!isset($data['result']) || $data['result'] !== 'SUCCESS') {
            throw new GatewayResponseException('Missing or invalid session result.');
        }

        if (!isset($data['session']) || !isset($data['session']['id'])) {
            throw new GatewayResponseException('Missing session or ID.');
        }
    }

    /**
     * @param array $data
     */
    public function validateTxnResponse($data)
    {
        // @todo
    }

    /**
     * @param array $data
     */
    public function validateOrderResponse($data)
    {
        // @todo
    }

    /**
     * @param array $data
     */
    public function validateVoidResponse($data)
    {
        // @todo
    }

    /**
     * @param $response
     * @return bool
     */
    public function isApproved($response)
    {
        $gatewayCode = $response['response']['gatewayCode'];

        if (!in_array($gatewayCode, array('APPROVED', 'APPROVED_AUTO'))) {
            return false;
        }

        return true;
    }

    /**
     * Interprets the authentication response returned from the card Issuer's Access Control Server (ACS)
     * after the cardholder completes the authentication process. The response indicates the success
     * or otherwise of the authentication.
     * The 3DS AuthId is required so that merchants can submit payloads multiple times
     * without producing duplicates in the database.
     * POST https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/3DSecureId/{3DSecureId}
     *
     * @param string $threeDSecureId
     * @param string $paRes
     * @return mixed|ResponseInterface
     * @throws Exception
     */
    public function process3dsResult($threeDSecureId, $paRes)
    {
        $uri = $this->apiUrl . '3DSecureId/' . $threeDSecureId;

        $request = $this->messageFactory->createRequest('POST', $uri, array(), json_encode(array(
            'apiOperation' => 'PROCESS_ACS_RESULT',
            '3DSecure' => array(
                'paRes' => $paRes
            )
        )));

        $response = $this->client->sendRequest($request);
        $response = json_decode($response->getBody(), true);

        return $response;
    }

    /**
     * Request to check a cardholder's enrollment in the 3DSecure scheme.
     * PUT https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/3DSecureId/{3DSecureId}
     *
     * @param array $data
     * @param array $order
     * @param array $session
     * @return mixed|ResponseInterface
     * @throws Exception
     */
    public function check3dsEnrollment($data, $order, $session)
    {
        $threeDSecureId = uniqid(sprintf('3DS-'));
        $uri = $this->apiUrl . '3DSecureId/' . $threeDSecureId;

        $request = $this->messageFactory->createRequest('PUT', $uri, array(), json_encode(array(
            'apiOperation' => 'CHECK_3DS_ENROLLMENT',
            '3DSecure' => $data,
            'order' => $order,
            'session' => $session,
        )));

        $response = $this->client->sendRequest($request);
        $response = json_decode($response->getBody(), true);

        return $response;
    }

    /**
     * Create Checkout Session
     * Request to create a session identifier for the checkout interaction.
     * The session identifier, when included in the Checkout.configure() function,
     * allows you to return the payer to the merchant's website after completing the payment attempt.
     * https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/session
     *
     * @param string $orderId
     * @param string $amount
     * @param string $currency
     * @param array $interaction
     * @param array $customer
     * @param array $billing
     * @return array
     * @throws Exception
     * @throws GatewayResponseException
     */
    public function createCheckoutSession(
        $orderId,
        $amount,
        $currency,
        $interaction = array(),
        $customer = array(),
        $billing = array()
    ) {
        $uri = $this->apiUrl . 'session';

        $request = $this->messageFactory->createRequest('POST', $uri, array(), json_encode(array(
            'apiOperation' => 'CREATE_CHECKOUT_SESSION',
            'partnerSolutionId' => $this->getSolutionId(),
            'order' => array(
                'id' => $orderId,
                'currency' => $currency,
                'amount' => $amount,
                'notificationUrl' => $this->webhookUrl
            ),
            'billing' => $billing,
            'interaction' => $interaction,
            'customer' => $customer,
        )));

        $response = $this->client->sendRequest($request);
        $response = json_decode($response->getBody(), true);

        $this->validateCheckoutSessionResponse($response);

        return $response;
    }

    /**
     * Request to obtain an authorization for a proposed funds transfer.
     * An authorization is a response from a financial institution indicating that payment information
     * is valid and funds are available in the payers account.
     * https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/order/{orderid}/transaction/{transactionid}
     *
     * @param string $orderId
     * @param string $amount
     * @param string $currency
     * @param array $theeDSecure
     * @param array $session
     * @param array $customer
     * @param array $billing
     * @return mixed|ResponseInterface
     * @throws Exception
     */
    public function authorize(
        $orderId,
        $amount,
        $currency,
        $theeDSecure = null,
        $session = array(),
        $customer = array(),
        $billing = array()
    ) {
        $txnId = '1';
        $uri = $this->apiUrl . 'order/' . $orderId . '/transaction/' . $txnId;

        $request = $this->messageFactory->createRequest('PUT', $uri, array(), json_encode(array(
            'apiOperation' => 'AUTHORIZE',
            '3DSecure' => $theeDSecure,
            'partnerSolutionId' => $this->getSolutionId(),
            'order' => array(
                'currency' => $currency,
                'amount' => $amount,
                'notificationUrl' => $this->webhookUrl
            ),
            'billing' => $billing,
            'customer' => $customer,
            'sourceOfFunds' => array(
                'type' => 'CARD'
            ),
            'session' => $session,
        )));

        $response = $this->client->sendRequest($request);
        $response = json_decode($response->getBody(), true);

        $this->validateTxnResponse($response);

        return $response;
    }

    /**
     * A single transaction to authorise the payment and transfer funds from the payer's account to your account.
     *
     * For card payments, Pay is a mode where the Authorize and Capture operations are completed at the same time.
     * Pay is the most common type of payment model used by merchants to accept card payments.
     * The Pay model is used when the merchant is allowed to bill the cardholder's account immediately,
     * for example when providing services or goods on the spot.
     * PUT https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/order/{orderid}/transaction/{transactionid}
     *
     * @param string $orderId
     * @param string $amount
     * @param string $currency
     * @param array $theeDSecure
     * @param array $session
     * @param array $customer
     * @param array $billing
     * @return mixed|ResponseInterface
     * @throws Exception
     */
    public function pay(
        $orderId,
        $amount,
        $currency,
        $theeDSecure = null,
        $session = array(),
        $customer = array(),
        $billing = array()
    ) {
        $txnId = '1';
        $uri = $this->apiUrl . 'order/' . $orderId . '/transaction/' . $txnId;

        $request = $this->messageFactory->createRequest('PUT', $uri, array(), json_encode(array(
            'apiOperation' => 'PAY',
            '3DSecure' => $theeDSecure,
            'partnerSolutionId' => $this->getSolutionId(),
            'order' => array(
                'currency' => $currency,
                'amount' => $amount,
                'notificationUrl' => $this->webhookUrl
            ),
            'billing' => $billing,
            'customer' => $customer,
            'sourceOfFunds' => array(
                'type' => 'CARD'
            ),
            'session' => $session,
        )));

        $response = $this->client->sendRequest($request);
        $response = json_decode($response->getBody(), true);

        $this->validateTxnResponse($response);

        return $response;
    }

    /**
     * Retrieve order
     * Request to retrieve the details of an order and all transactions associated with this order.
     * https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/order/{orderid}
     *
     * @param string $orderId
     * @return array
     * @throws \Http\Client\Exception
     */
    public function retrieveOrder($orderId)
    {
        $uri = $this->apiUrl . 'order/' . $orderId;

        $request = $this->messageFactory->createRequest('GET', $uri);
        $response = $this->client->sendRequest($request);

        $response = json_decode($response->getBody(), true);

        $this->validateOrderResponse($response);

        return $response;
    }

    /**
     * Request to retrieve the details of a transaction. For example you can retrieve the details of an authorization that you previously executed.
     * https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/order/{orderid}/transaction/{transactionid}
     *
     * @param string $orderId
     * @param string $txnId
     * @return array
     * @throws Exception
     */
    public function retrieveTransaction($orderId, $txnId)
    {
        $uri = $this->apiUrl . 'order/' . $orderId . '/transaction/' . $txnId;

        $request = $this->messageFactory->createRequest('GET', $uri);
        $response = $this->client->sendRequest($request);

        $response = json_decode($response->getBody(), true);

        $this->validateTxnResponse($response);

        return $response;
    }

    /**
     * Request to void a previous transaction. A void will reverse a previous transaction.
     * Typically voids will only be successful when processed not long after the original transaction.
     * https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/order/{orderid}/transaction/{transactionid}
     *
     * @param string $orderId
     * @param string $newTxnId
     * @param string $txnId
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function voidTxn($orderId, $newTxnId, $txnId)
    {
        $uri = $this->apiUrl . 'order/' . $orderId . '/transaction/' . $newTxnId;

        $request = $this->messageFactory->createRequest('PUT', $uri, array(), json_encode(array(
            'apiOperation' => 'VOID',
            'partnerSolutionId' => $this->getSolutionId(),
            'transaction' => array(
                'targetTransactionId' => $txnId
            )
        )));
        $response = $this->client->sendRequest($request);

        $response = json_decode($response->getBody(), true);

        $this->validateVoidResponse($response);

        return $response;
    }

    /**
     * Request to capture funds previously reserved by an authorization.
     * A Capture transaction triggers the movement of funds from the payer's account to the merchant's account.
     * Typically, a Capture is linked to the authorization through the orderId - you provide the original orderId,
     * a new transactionId, and the amount you wish to capture.
     * You may provide other fields (such as shipping address) if you want to update their values; however,
     * you must NOT provide sourceOfFunds.
     * https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/order/{orderid}/transaction/{transactionid}
     *
     * @param string $orderId
     * @param string $newTxnId
     * @param $amount
     * @param $currency
     * @return mixed|ResponseInterface
     * @throws Exception
     */
    public function captureTxn($orderId, $newTxnId, $amount, $currency)
    {
        $uri = $this->apiUrl . 'order/' . $orderId . '/transaction/' . $newTxnId;

        $request = $this->messageFactory->createRequest('PUT', $uri, array(), json_encode(array(
            'apiOperation' => 'CAPTURE',
            'partnerSolutionId' => $this->getSolutionId(),
            'transaction' => array(
                'amount' => $amount,
                'currency' => $currency
            ),
            'order' => array(
                'notificationUrl' => $this->webhookUrl
            )
        )));

        $response = $this->client->sendRequest($request);
        $response = json_decode($response->getBody(), true);

        $this->validateTxnResponse($response);

        return $response;
    }

    /**
     * Request to refund previously captured funds to the payer.
     * Typically, a Refund is linked to the Capture or Pay through the orderId - you provide the original orderId,
     * a new transactionId, and the amount you wish to refund. You may provide other fields if you want to update their values;
     * however, you must NOT provide sourceOfFunds.
     * In rare situations, you may want to refund the payer without associating the credit to a previous transaction (see Standalone Refund).
     * In this case, you need to provide the sourceOfFunds and a new orderId.
     * https://mtf.gateway.mastercard.com/api/rest/version/50/merchant/{merchantId}/order/{orderid}/transaction/{transactionid}
     *
     * @param $orderId
     * @param $newTxnId
     * @param $amount
     * @param $currency
     * @return mixed|ResponseInterface
     * @throws Exception
     */
    public function refund($orderId, $newTxnId, $amount, $currency)
    {
        $uri = $this->apiUrl . 'order/' . $orderId . '/transaction/' . $newTxnId;

        $request = $this->messageFactory->createRequest('PUT', $uri, array(), json_encode(array(
            'apiOperation' => 'REFUND',
            'partnerSolutionId' => $this->getSolutionId(),
            'transaction' => array(
                'amount' => $amount,
                'currency' => $currency
            ),
            'order' => array(
                'notificationUrl' => $this->webhookUrl
            )
        )));

        $response = $this->client->sendRequest($request);
        $response = json_decode($response->getBody(), true);

        $this->validateTxnResponse($response);

        return $response;
    }
}
