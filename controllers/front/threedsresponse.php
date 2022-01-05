<?php
/**
 * Copyright (c) 2019-2021 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

require_once(dirname(__FILE__).'/../../vendor/autoload.php');
require_once(dirname(__FILE__).'/../../gateway.php');

class MastercardThreeDSResponseModuleFrontController extends ModuleFrontController
{
    public $auth = false;

    public $guest = true;

    /**
     * @var MasterCard
     */
    public $module;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function init()
    {
        if (!$this->module->active) {
            $this->maintenance = true;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->emitServerError('Only POST is allowed');
        }

        // If store is NOT in HTTPS mode, then Webhook Secret is not sent,
        // we'll still proceed in case the payment is TEST mode.
        if (!Configuration::get('mpgs_mode') && !Configuration::get('PS_SSL_ENABLED')) {
            parent::init();

            return;
        }

        parent::init();
    }

    /**
     * Emit server error and exit
     *
     * @param string $reason
     */
    public function emitServerError($reason)
    {
        header('HTTP/1.1 500 '.$reason);
        exit;
    }

    /**
     * @inheritdoc
     */
    public function postProcess()
    {
        $transactionId = Tools::getValue('transaction_id');
        $result = Tools::getValue('result');
        if ($result === 'SUCCESS') {
            echo "<script>window.parent.treeDS2Completed('{$transactionId}');</script>";
        } else {
            $error = $this->module->l('Your payment was declined.', 'threedsresponse');
            echo "<script>window.parent.treeDS2Failure('{$error}');</script>";
        }
        exit;
    }
}
