<?php
/**
 * Copyright (c) On Tap Networks Limited.
 */

require_once(dirname(__FILE__) . '/../../vendor/autoload.php');
require_once(dirname(__FILE__) . '/../../gateway.php');
require_once(dirname(__FILE__) . '/../../handlers.php');

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MastercardWebhookModuleFrontController extends ModuleFrontController
{
    const HEADER_WEBHOOK_SECRET = 'X-Notification-Secret';
    const HEADER_WEBHOOK_ID = 'X-Notification-Id';
    const HEADER_WEBHOOK_ATTEMPT = 'X-Notification-Attempt';

    /**
     * @var GatewayService
     */
    protected $client;

    /**
     * @var MasterCard
     */
    public $module;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function init()
    {
        $this->logger = new Logger('mastercard_webhook');
        $this->logger->pushHandler(new StreamHandler(_PS_ROOT_DIR_.'/var/logs/mastercard.log'));

        if (!$this->module->active) {
            $this->maintenance = true;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->emitServerError('Only POST is allowed');
        }

        $headers = getallheaders();

        // If store is NOT in HTTPS mode, then Webhook Secret is not sent,
        // we'll still proceed in case the payment is TEST mode.
        if (!Configuration::get('mpgs_mode') && !Configuration::get('PS_SSL_ENABLED')) {
            parent::init();
            return;
        }

        $webhoolSecret = $this->module->getConfigValue('mpgs_webhook_secret');
        if (!$webhoolSecret || $webhoolSecret !== $headers[self::HEADER_WEBHOOK_SECRET]) {
            $this->logger->critical('Invalid or missing webhook secret', array(
                'environment' => $_SERVER
            ));
            $this->emitServerError('Secret Mismatch');
        }

        parent::init();
    }

    /**
     * Emit server error and exit
     * @param string $reason
     */
    public function emitServerError($reason)
    {
        header('HTTP/1.1 500 ' . $reason);
        header('Retry-After: 3600');
        exit;
    }

    /**
     * @param $contentParsed
     * @return array
     */
    protected function getLoggerContext($contentParsed)
    {
        $headers = getallheaders();
        return array(
            'id' => $headers[self::HEADER_WEBHOOK_ID],
            'attempt' => $headers[self::HEADER_WEBHOOK_ATTEMPT],
            'order.id' => $contentParsed['order']['id'],
            'transaction.id' => $contentParsed['transaction']['id'],
            'transaction.type' => $contentParsed['transaction']['type'],
            'response.gatewayCode' => $contentParsed['response']['gatewayCode'],
        );
    }

    /**
     * @inheritdoc
     * @throws \Http\Client\Exception
     */
    public function postProcess()
    {
        try {
            $this->client = new GatewayService(
                $this->module->getApiEndpoint(),
                $this->module->getApiVersion(),
                $this->module->getConfigValue('mpgs_merchant_id'),
                $this->module->getConfigValue('mpgs_api_password'),
                $this->module->getWebhookUrl()
            );
        } catch (Exception $e) {
            $this->logger->critical('Could not instantiate the HTTP client', array($e));
            $this->emitServerError('HTTP Client Error');
        }

        $content = file_get_contents('php://input');
        $content = trim($content);

        $contentParsed = @json_decode($content, true);

        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            $this->logger->critical('Could not parse response JSON, error: '.$jsonError, array(
                'rawContent' => $content
            ));
            $this->emitServerError('JSON Error');
        }

        if ($this->module->getConfigValue('mpgs_merchant_id') !== $contentParsed['merchant']) {
            $this->logger->critical('Webhook merchant ID does not match the merchant ID configured', array($contentParsed));
            $this->emitServerError('Invalid Parameter');
        }

        if (!isset($contentParsed['order']) || !isset($contentParsed['order']['id'])) {
            $this->logger->critical('Invalid parameter order.id', array($contentParsed));
            $this->emitServerError('Invalid Parameter');
        }

        if (!isset($contentParsed['transaction']) || !isset($contentParsed['transaction']['id'])) {
            $this->logger->critical('Invalid parameter transaction.id', array($contentParsed));
            $this->emitServerError('Invalid Parameter');
        }

        $this->logger->info('Webhook received', $this->getLoggerContext($contentParsed));

        $response = array();

        try {
            $response = $this->client->retrieveTransaction(
                $contentParsed['order']['id'],
                $contentParsed['transaction']['id']
            );
        } catch (Exception $e) {
            $this->logger->critical('Gateway Error', array($this->getLoggerContext($contentParsed), $e));
            $this->emitServerError('Gateway Error');
        }

        if (!$this->client->isApproved($response)) {
            $this->logger->warning(sprintf('Unexpected gateway code "%s"', $response['response']['gatewayCode']), $this->getLoggerContext($response));
            exit;
        }

        /** @var Order $order */
        $order = Order::getByCartId($response['order']['id']);

        switch ($response['transaction']['type']) {
            case 'AUTHORIZATION':
            case 'AUTHORIZATION_UPDATE':
                $this->authorize($order, $response);
                break;

            case 'PAYMENT':
            case 'CAPTURE':
                $this->capture($order, $response);
                break;

            case 'REFUND':
                $this->refund($order, $response);
                break;

            case 'VOID_AUTHORIZATION':
            case 'CANCELLED':
                $this->void($order, $response);
                break;

            default:
                $this->logger->warning(
                    sprintf("Received unknown transaction.type '%s'", $response['transaction']['type']),
                    $this->getLoggerContext($response)
                );
                break;
        }

        parent::postProcess();

        $this->logger->info('Webhook completed (200 OK)');
        exit;
    }

    /**
     * @param Order $order
     * @param array $response
     */
    protected function capture($order, $response)
    {
        $state = $order->getCurrentOrderState();
        if (
            $state->id != Configuration::get('MPGS_OS_AUTHORIZED') &&
            $state->id != Configuration::get('MPGS_OS_PAYMENT_WAITING')
        ) {
            $this->logger->warning(sprintf("Order state '%s' does not allow capture", $state->id), $this->getLoggerContext($response));
            return;
        }

        try {
            $authTxnId = $this->module->findTxnId('auth', $order);
            $authTxn = $this->module->findTxn('auth', $order);

            $order->addOrderPayment(
                $response['transaction']['amount'],
                null,
                'capture-' . $response['transaction']['id']
            );

            if ($authTxnId) {
                // @todo: Deletes auth transaction, otherwise the payment would appear to be doubled
                $authTxn->delete();
            } else {
                $this->logger->warning('Authorization transaction not found on this order');
            }

            $newStatus = Configuration::get('PS_OS_PAYMENT');
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());

        } catch (Exception $e) {
            $this->logger->critical('Capture Error', array($this->getLoggerContext($response), $e));
            $this->emitServerError('Capture Error');
        }
    }

    /**
     * @param Order $order
     * @param array $response
     */
    protected function refund($order, $response)
    {
        $state = $order->getCurrentOrderState();
        if ($state->id != Configuration::get('PS_OS_PAYMENT')) {
            $this->logger->warning(sprintf("Order state '%s' does not allow refund", $state->id), $this->getLoggerContext($response));
            return;
        }

        try {
            $txnId = $this->module->findTxnId('capture', $order);
            if (!$txnId) {
                $this->logger->warning('Capture/pay transaction not found on this order');
                return;
            }


            $amount = number_format(floatval($response['transaction']['amount']) * -1, 2, '.', '');
            $order->addOrderPayment(
                $amount,
                null,
                'refund-' . $response['transaction']['id']
            );

            $newStatus = Configuration::get('PS_OS_REFUND');
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());

        } catch (Exception $e) {
            $this->logger->critical('Refund Error', array($this->getLoggerContext($response), $e));
            $this->emitServerError('Refund Error');
        }
    }

    /**
     * @param Order $order
     * @param array $response
     */
    protected function void($order, $response)
    {
        $state = $order->getCurrentOrderState();
        if ($state->id != Configuration::get('MPGS_OS_AUTHORIZED')) {
            $this->logger->warning(sprintf("Order state '%s' does not allow void", $state->id), $this->getLoggerContext($response));
            return;
        }

        try {
            $txnId = $this->module->findTxnId('auth', $order);
            if (!$txnId) {
                $this->logger->warning('Authorization transaction not found on this order');
                return;
            }

            $amount = number_format(floatval($response['transaction']['amount']) * -1, 2, '.', '');
            $order->addOrderPayment(
                $amount,
                null,
                'void-' . $response['transaction']['id']
            );

            $newStatus = Configuration::get('PS_OS_CANCELED');
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($newStatus, $order, true);
            $history->addWithemail(true, array());

        } catch (Exception $e) {
            $this->logger->critical('Void Error', array($this->getLoggerContext($response), $e));
            $this->emitServerError('Void Error');
        }
    }

    /**
     * @param Order $order
     * @param array $response
     */
    protected function authorize($order, $response)
    {
        $state = $order->getCurrentOrderState();
        if ($state->id != Configuration::get('MPGS_OS_PAYMENT_WAITING')) {
            $this->logger->warning(sprintf("Order state '%s' does not allow authorization", $state->id), $this->getLoggerContext($response));
            return;
        }

        try {
            $order->addOrderPayment(
                $response['transaction']['amount'],
                null,
                'auth-' . $response['transaction']['id']
            );

            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState(Configuration::get('MPGS_OS_AUTHORIZED'), $order, true);
            $history->addWithemail(true, array());
        } catch (Exception $e) {
            $this->logger->critical('Authorize Error', array($this->getLoggerContext($response), $e));
            $this->emitServerError('Authorize Error');
        }
    }
}
