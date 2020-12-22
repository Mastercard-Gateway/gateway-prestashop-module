<?php
/**
 * Copyright (c) 2019-2020 Mastercard
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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Mastercard $module
 * @return bool
 * @throws PrestaShopException
 */
function upgrade_module_1_3_3($module)
{
    $dbPrefix = _DB_PREFIX_;
    $mysqlEngine = _MYSQL_ENGINE_;
    $query = <<<EOT
CREATE TABLE IF NOT EXISTS `{$dbPrefix}mpgs_payment_refunds` (
    `refund_id` int(10) unsigned NOT NULL auto_increment,
    `order_id` int(10) unsigned NOT NULL,
    `order_slip_id` int(10) unsigned,
    `total` float NOT NULL,
    `transaction_id` varchar(255) NOT NULL,
     PRIMARY KEY  (`refund_id`)
) ENGINE={$mysqlEngine} DEFAULT CHARSET=utf8;
EOT;
    return DB::getInstance()->execute($query);
}
