<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class OrderReturnCore
 */
class OrderReturnCore extends ObjectModel
{
    public const STATE_WAITING_FOR_CONFIRMATION = 1;
    public const STATE_WAITING_FOR_PACKAGE = 2;
    public const STATE_PACKAGE_RECEIVED = 3;
    public const STATE_RETURN_DENIED = 4;
    public const STATE_RETURN_COMPLETED = 5;

    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'   => 'order_return',
        'primary' => 'id_order_return',
        'fields'  => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order'    => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'state'       => ['type' => self::TYPE_INT, 'dbType' => 'tinyint(1) unsigned', 'dbDefault' => '1'],
            'question'    => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml', 'size' => ObjectModel::SIZE_TEXT, 'dbNullable' => false],
            'migrated'    => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbType' => 'tinyint(1) unsigned', 'dbDefault' => '0'],
            'date_add'    => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd'    => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'order_return' => [
                'id_order'              => ['type' => ObjectModel::KEY, 'columns' => ['id_order']],
                'order_return_customer' => ['type' => ObjectModel::KEY, 'columns' => ['id_customer']],
            ],
        ],
    ];
    /** @var int */
    public $id;
    /** @var int */
    public $id_customer;
    /** @var int */
    public $id_order;
    /** @var int */
    public $state;
    /** @var string message content */
    public $question;
    /** @var bool */
    public $migrated = false;
    /** @var string Object creation date */
    public $date_add;
    /** @var string Object last modification date */
    public $date_upd;

    /**
     * @param int $idOrder
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getReturnedCustomizedProducts($idOrder)
    {
        $idOrder = (int)$idOrder;
        $returns = Customization::getReturnedCustomizations($idOrder);
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            throw new PrestaShopException(sprintf(Tools::displayError("Order %s not found"), $idOrder));
        }
        $products = $order->getProducts();

        foreach ($returns as &$return) {
            $return['product_id'] = (int) $products[(int) $return['id_order_detail']]['product_id'];
            $return['product_attribute_id'] = (int) $products[(int) $return['id_order_detail']]['product_attribute_id'];
            $return['name'] = $products[(int) $return['id_order_detail']]['product_name'];
            $return['reference'] = $products[(int) $return['id_order_detail']]['product_reference'];
            $return['id_address_delivery'] = $products[(int) $return['id_order_detail']]['id_address_delivery'];
        }

        return $returns;
    }

    public function update($nullValues = false)
    {
        $idOrderDetails = $this->getOrderDetailIds();
        $result = parent::update($nullValues);
        if ($result) {
            $this->syncOrderDetailIds($idOrderDetails);
        }

        return $result;
    }

    public function delete()
    {
        $idOrderDetails = $this->getOrderDetailIds();
        $result = parent::delete();
        if ($result) {
            Db::getInstance()->delete('order_return_detail', '`id_order_return` = '.(int)$this->id);
            $this->syncOrderDetailIds($idOrderDetails);
        }

        return $result;
    }

    /**
     * @param int $idOrderReturn
     * @param int $idOrderDetail
     * @param int $idCustomization
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function deleteOrderReturnDetail($idOrderReturn, $idOrderDetail, $idCustomization = 0)
    {
        $result = Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_return_detail` WHERE `id_order_detail` = '.(int) $idOrderDetail.' AND `id_order_return` = '.(int) $idOrderReturn.' AND `id_customization` = '.(int) $idCustomization);
        if ($result) {
            self::syncReturnedQuantity((int)$idOrderDetail);
        }

        return $result;
    }

    public static function isWaitingState(int $state): bool
    {
        return in_array($state, [
            self::STATE_WAITING_FOR_CONFIRMATION,
            self::STATE_WAITING_FOR_PACKAGE,
        ], true);
    }

    public static function getWaitingReturnForOrder(int $idOrder): ?self
    {
        $idOrderReturn = (int)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('`id_order_return`')
                ->from('order_return')
                ->where('`id_order` = ' . (int)$idOrder)
                ->where('`state` IN (' . implode(',', [
                    self::STATE_WAITING_FOR_CONFIRMATION,
                    self::STATE_WAITING_FOR_PACKAGE,
                ]) . ')')
                ->orderBy('`date_add` DESC')
        );

        if ($idOrderReturn <= 0) {
            return null;
        }

        $orderReturn = new self($idOrderReturn);
        return Validate::isLoadedObject($orderReturn) ? $orderReturn : null;
    }

    public static function getOrCreateBackOfficeReturn(Order $order, int $state): ?self
    {
        $orderReturn = self::getWaitingReturnForOrder((int)$order->id);

        if ($orderReturn) {
            $orderReturn->state = $state;
            return $orderReturn->save() ? $orderReturn : null;
        }

        $orderReturn = new self();
        $orderReturn->id_customer = (int)$order->id_customer;
        $orderReturn->id_order = (int)$order->id;
        $orderReturn->state = $state;
        $orderReturn->question = '';

        if (!$orderReturn->add()) {
            return null;
        }

        Hook::triggerEvent('actionOrderReturn', ['orderReturn' => $orderReturn]);

        return $orderReturn;
    }

    public static function upsertReturnDetail(
        int $idOrderReturn,
        int $idOrderDetail,
        int $quantity,
        int $idCustomization = 0
    ): bool {
        $quantity = max(0, $quantity);
        $where = '`id_order_return` = ' . (int)$idOrderReturn
            . ' AND `id_order_detail` = ' . (int)$idOrderDetail
            . ' AND `id_customization` = ' . (int)$idCustomization;

        $exists = (bool)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('1')
                ->from('order_return_detail')
                ->where($where)
        );

        if ($exists) {
            if ($quantity <= 0) {
                $result = (bool)Db::getInstance()->delete('order_return_detail', $where);
                if ($result) {
                    self::syncReturnedQuantity($idOrderDetail);
                }

                return $result;
            }

            $result = (bool)Db::getInstance()->update(
                'order_return_detail',
                ['product_quantity' => (int)$quantity],
                $where
            );
            if ($result) {
                self::syncReturnedQuantity($idOrderDetail);
            }

            return $result;
        }

        if ($quantity <= 0) {
            return true;
        }

        $result = (bool)Db::getInstance()->insert(
            'order_return_detail',
            [
                'id_order_return' => (int)$idOrderReturn,
                'id_order_detail' => (int)$idOrderDetail,
                'id_customization' => (int)$idCustomization,
                'product_quantity' => (int)$quantity,
            ]
        );
        if ($result) {
            self::syncReturnedQuantity($idOrderDetail);
        }

        return $result;
    }

    public static function syncReturnedQuantity(int $idOrderDetail): bool
    {
        if ($idOrderDetail <= 0) {
            return false;
        }

        $quantity = (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('COALESCE(SUM(ord.`product_quantity`), 0)')
                ->from('order_return_detail', 'ord')
                ->innerJoin('order_return', 'orx', 'orx.`id_order_return` = ord.`id_order_return`')
                ->where('ord.`id_order_detail` = '.(int)$idOrderDetail)
                ->where('orx.`state` IN ('.implode(',', [
                    self::STATE_PACKAGE_RECEIVED,
                    self::STATE_RETURN_COMPLETED,
                ]).')')
        );

        return (bool)Db::getInstance()->update(
            'order_detail',
            ['product_quantity_return' => max(0, $quantity)],
            '`id_order_detail` = '.(int)$idOrderDetail
        );
    }

    public static function getOpenReturnQuantityByOrderDetail(int $idOrderDetail): int
    {
        return (int)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('COALESCE(SUM(ord.`product_quantity`), 0)')
                ->from('order_return_detail', 'ord')
                ->innerJoin('order_return', 'orx', 'orx.`id_order_return` = ord.`id_order_return`')
                ->where('ord.`id_order_detail` = ' . (int)$idOrderDetail)
                ->where('orx.`state` IN (' . implode(',', [
                    self::STATE_WAITING_FOR_CONFIRMATION,
                    self::STATE_WAITING_FOR_PACKAGE,
                ]) . ')')
        );
    }

    public function getDetailRows(): array
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('*')
                ->from('order_return_detail')
                ->where('`id_order_return` = ' . (int)$this->id)
        );
    }

    private function getOrderDetailIds(): array
    {
        if ((int)$this->id <= 0) {
            return [];
        }

        $rows = Db::getInstance()->getArray(
            (new DbQuery())
                ->select('DISTINCT `id_order_detail`')
                ->from('order_return_detail')
                ->where('`id_order_return` = '.(int)$this->id)
        );

        return array_map('intval', array_column($rows, 'id_order_detail'));
    }

    private function syncOrderDetailIds(array $idOrderDetails): void
    {
        foreach (array_unique(array_map('intval', $idOrderDetails)) as $idOrderDetail) {
            self::syncReturnedQuantity($idOrderDetail);
        }
    }

    /**
     * Get return details for one product line
     *
     * @param int $idOrderDetail
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getProductReturnDetail($idOrderDetail)
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`product_quantity`, `date_add`, orsl.`name` AS `state`')
                ->from('order_return_detail', 'ord')
                ->leftJoin('order_return', 'o', 'o.`id_order_return` = ord.`id_order_return`')
                ->leftJoin('order_return_state_lang', 'orsl', 'orsl.`id_order_return_state` = o.`state` AND orsl.`id_lang` = '.(int) Context::getContext()->language->id)
                ->where('ord.`id_order_detail` = '.(int) $idOrderDetail)
        );
    }

    /**
     * Add returned quantity to products list
     *
     * @param array $products
     * @param int $idOrder
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function addReturnedQuantity(&$products, $idOrder)
    {
        $details = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('od.`id_order_detail`, GREATEST(od.`product_quantity_return`, IFNULL(SUM(ord.`product_quantity`),0)) AS `qty_returned`')
                ->from('order_detail', 'od')
                ->leftJoin('order_return_detail', 'ord', 'ord.`id_order_detail` = od.`id_order_detail`')
                ->where('od.`id_order` = '.(int) $idOrder)
                ->groupBy('od.`id_order_detail`')
        );
        if (!$details) {
            return;
        }

        $detailList = [];
        foreach ($details as $detail) {
            $detailList[$detail['id_order_detail']] = $detail;
        }

        foreach ($products as &$product) {
            if (isset($detailList[$product['id_order_detail']]['qty_returned'])) {
                $product['qty_returned'] = $detailList[$product['id_order_detail']]['qty_returned'];
            }
        }
    }

    /**
     * @param array $orderDetailList
     * @param array $productQtyList
     * @param array $customizationIds
     * @param array $customizationQtyInput
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function addReturnDetail($orderDetailList, $productQtyList, $customizationIds, $customizationQtyInput)
    {
        /* Classic product return */
        $conn = Db::getInstance();
        if ($orderDetailList) {
            foreach ($orderDetailList as $key => $orderDetail) {
                if ($qty = (int) $productQtyList[$key]) {
                    $conn->insert('order_return_detail', ['id_order_return' => (int) $this->id, 'id_order_detail' => (int) $orderDetail, 'product_quantity' => $qty, 'id_customization' => 0]);
                }
            }
        }
        /* Customized product return */
        if ($customizationIds) {
            foreach ($customizationIds as $orderDetailId => $customizations) {
                foreach ($customizations as $customizationId) {
                    if ($quantity = (int) $customizationQtyInput[(int) $customizationId]) {
                        $conn->insert('order_return_detail', ['id_order_return' => (int) $this->id, 'id_order_detail' => (int) $orderDetailId, 'product_quantity' => $quantity, 'id_customization' => (int) $customizationId]);
                    }
                }
            }
        }
    }

    /**
     * @param array $orderDetailList
     * @param array $productQtyList
     * @param array $customizationIds
     * @param array $customizationQtyInput
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function checkEnoughProduct($orderDetailList, $productQtyList, $customizationIds, $customizationQtyInput)
    {
        $idOrder = (int) $this->id_order;
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            throw new PrestaShopException(sprintf(Tools::displayError("Order %s not found"), $idOrder));
        }
        $products = $order->getProducts();
        /* Products already returned */
        $orderReturn = OrderReturn::getOrdersReturn($order->id_customer, $order->id, true);
        foreach ($orderReturn as $or) {
            $orderReturnProducts = OrderReturn::getOrdersReturnProducts($or['id_order_return'], $order);
            foreach ($orderReturnProducts as $key => $orp) {
                $products[$key]['product_quantity'] -= (int) $orp['product_quantity'];
            }
        }
        /* Quantity check */
        if ($orderDetailList) {
            foreach (array_keys($orderDetailList) as $key) {
                if ($qty = (int) $productQtyList[$key]) {
                    if ($products[$key]['product_quantity'] - $qty < 0) {
                        return false;
                    }
                }
            }
        }
        /* Customization quantity check */
        if ($customizationIds) {
            $orderedCustomizations = Customization::getOrderedCustomizations((int) $order->id_cart);
            foreach ($customizationIds as $customizations) {
                foreach ($customizations as $customizationId) {
                    $customizationId = (int) $customizationId;
                    if (!isset($orderedCustomizations[$customizationId])) {
                        return false;
                    }
                    $quantity = (isset($customizationQtyInput[$customizationId]) ? (int) $customizationQtyInput[$customizationId] : 0);
                    if ((int) $orderedCustomizations[$customizationId]['quantity'] - $quantity < 0) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param int $customerId
     * @param int|bool $orderId
     * @param bool $noDenied
     * @param Context|null $context
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrdersReturn($customerId, $orderId = false, $noDenied = false, ?Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }
        $data = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('*')
                ->from('order_return')
                ->where('`id_customer` = '.(int) $customerId)
                ->where($orderId ? '`id_order` = '.(int) $orderId : '')
                ->where($noDenied ? '`state` != 4' : '')
                ->orderBy('`date_add` DESC')
        );
        foreach ($data as $k => $or) {
            $state = new OrderReturnState($or['state']);
            $data[$k]['state_name'] = $state->name[$context->language->id];
            $data[$k]['type'] = 'Return';
            $data[$k]['tracking_number'] = $or['id_order_return'];
            $data[$k]['can_edit'] = false;
            $data[$k]['reference'] = Order::getUniqReferenceOf($or['id_order']);
        }

        return $data;
    }

    /**
     * @param int $orderReturnId
     * @param Order $order
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrdersReturnProducts($orderReturnId, $order)
    {
        $productsRet = OrderReturn::getOrdersReturnDetail($orderReturnId);
        $products = $order->getProducts();
        $tmp = [];
        foreach ($productsRet as $returnDetail) {
            $tmp[$returnDetail['id_order_detail']]['quantity'] = isset($tmp[$returnDetail['id_order_detail']]['quantity']) ? $tmp[$returnDetail['id_order_detail']]['quantity'] + (int) $returnDetail['product_quantity'] : (int) $returnDetail['product_quantity'];
            $tmp[$returnDetail['id_order_detail']]['customizations'] = (int) $returnDetail['id_customization'];
        }
        $resTab = [];
        foreach ($products as $key => $product) {
            if (isset($tmp[$product['id_order_detail']])) {
                $resTab[$key] = $product;
                $resTab[$key]['product_quantity'] = $tmp[$product['id_order_detail']]['quantity'];
                $resTab[$key]['customizations'] = $tmp[$product['id_order_detail']]['customizations'];
            }
        }

        return $resTab;
    }

    /**
     * @param int $idOrderReturn
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getOrdersReturnDetail($idOrderReturn)
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('*')
                ->from('order_return_detail')
                ->where('`id_order_return` = '.(int) $idOrderReturn)
        );
    }

    /**
     * @return bool|int
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function countProduct()
    {
        if (!$data = Db::readOnly()->getRow(
            (new DbQuery())
                ->select('COUNT(`id_order_return`) AS `total`')
                ->from('order_return_detail')
                ->where('`id_order_return` = '.(int) $this->id)
        )) {
            return false;
        }

        return (int) ($data['total']);
    }
}
