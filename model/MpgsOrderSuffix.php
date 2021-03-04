<?php
/**
 * 2007-2020 PrestaShop and Contributors
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
class MpgsOrderSuffix extends ObjectModel
{
    public $order_suffix_id;
    public $order_id;
    public $suffix;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'mpgs_payment_order_suffix',
        'primary' => 'order_suffix_id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            'order_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'suffix' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
        ),
    );

    /**
     * @param string|int $orderId
     * @param bool $refresh update hash value
     * @return self|null
     */
    public static function getOrderSuffixByOrderId($orderId, $refresh = false)
    {
        $isRefreshed = false;
        $sql = new DbQuery();
        $sql->from(self::$definition['table']);
        $sql->select('*');
        $sql->where('order_id = ' . pSQL($orderId));

        $res = Db::getInstance()->query($sql)->fetchAll();
        if ($res) {
            $res =  self::hydrateCollection(self::class, $res);
        } else if ($refresh) {
            $model = new self();
            $model->order_id = $orderId;
            $model->suffix = 1;
            $model->add();
            $res = [$model];
            $isRefreshed = true;
        } else {
            return null;
        }
        
        if ($refresh && !$isRefreshed) {
            $orderSuffixId = reset($res)->suffix + 1;
            foreach ($res as $item) {
                $item->suffix = (string)$orderSuffixId;
                $item->save();
            }
        }

        return reset($res);
    }
}
