<?php
/**
 * Copyright (c) 2019-2023 Mastercard
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
 */

class MpgsVoid extends ObjectModel
{
    public $void_id;
    public $order_id;
    public $total;
    public $transaction_id;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'mpgs_payment_voids',
        'primary' => 'void_id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            'order_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'total' => array('type' => self::TYPE_FLOAT),
            'transaction_id' => array('type' => self::TYPE_STRING, 'size' => 255),
        ),
    );

    /**
     * @param string|int $orderId
     * @return bool
     */
    public static function hasExistingVoids($orderId)
    {
        $sql = new DbQuery();
        $sql->from(self::$definition['table']);
        $sql->select('COUNT(*)');
        $sql->where('order_id = ' . pSQL($orderId));

        $res = Db::getInstance()->getValue($sql);

        return !!$res;
    }

    /**
     * @param string|int $orderId
     * @return self[]
     */
    public static function getAllVoidsByOrderId($orderId)
    {
        $sql = new DbQuery();
        $sql->from(self::$definition['table']);
        $sql->select('*');
        $sql->where('order_id = ' . pSQL($orderId));

        $res = Db::getInstance()->query($sql);

        if (!$res) {
            return [];
        }

        return self::hydrateCollection(self::class, $res->fetchAll());
    }
}
