<?php
/**
 * 2007-2019 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

/**
 * Class CMSCore.
 */
class MpgsRefund extends ObjectModel
{
    public $refund_id;
    public $order_id;
    public $order_slip_id;
    public $total;
    public $transaction_id;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'mpgs_payment_refunds',
        'primary' => 'refund_id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            'order_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'order_slip_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'total' => array('type' => self::TYPE_FLOAT),
            'transaction_id' => array('type' => self::TYPE_STRING, 'size' => 255),
        ),
    );

    /**
     * @param string|int $orderId
     * @return bool
     */
    public static function hasExistingRefunds($orderId)
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
     * @return bool
     */
    public static function hasExistingFullRefund($orderId)
    {
        $sql = new DbQuery();
        $sql->from(self::$definition['table']);
        $sql->select('COUNT(*)');
        $sql->where('order_id = ' . pSQL($orderId) . ' AND ' . 'order_slip_id=0');

        $res = Db::getInstance()->getValue($sql);

        return !!$res;
    }

    /**
     * @param string|int $orderId
     * @return self[]
     */
    public static function getAllRefundsByOrderId($orderId)
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
