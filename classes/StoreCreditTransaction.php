<?php
/**
 * Copyright (C) 2025-2026 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2025-2026 thirty bees
 * @license   Open Software License (OSL 3.0)
 */

/**
 * Class StoreCreditTransactionCore
 */
class StoreCreditTransactionCore extends ObjectModel
{
    const SIGN_INCREASE = 1;
    const SIGN_DECREASE = 2;

    const TYPE_PAYMENT_INSTRUMENT = 1;
    const TYPE_REFUND_CREDIT = 2;
    const TYPE_MANUAL_ADJUSTMENT = 3;

    const ENTITY_ORDER = 1;
    const ENTITY_ORDER_SLIP = 2;
    const ENTITY_MANUAL = 3;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $id_store_credit;

    /**
     * @var int
     */
    public $transaction_sign;

    /**
     * @var int
     */
    public $transaction_type;

    /**
     * @var int
     */
    public $entity_type;

    /**
     * @var int|null
     */
    public $id_entity;

    /**
     * @var int
     */
    public $id_customer;

    /**
     * @var int
     */
    public $id_employee;

    /**
     * @var float
     */
    public $amount_tax_incl;

    /**
     * @var string
     */
    public $note;

    /**
     * @var string
     */
    public $date_add;

    /**
     * @var string
     */
    public $date_upd;

    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'   => 'store_credit_transaction',
        'primary' => 'id_store_credit_transaction',
        'fields'  => [
            'id_store_credit'  => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'transaction_sign' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
                'values' => [self::SIGN_INCREASE, self::SIGN_DECREASE],
            ],
            'transaction_type' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
                'values' => [self::TYPE_PAYMENT_INSTRUMENT, self::TYPE_REFUND_CREDIT, self::TYPE_MANUAL_ADJUSTMENT],
            ],
            'entity_type'      => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
                'values' => [self::ENTITY_ORDER, self::ENTITY_ORDER_SLIP, self::ENTITY_MANUAL],
            ],
            'id_entity'        => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbNullable' => true],
            'id_customer'      => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_employee'      => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'amount_tax_incl'  => ['type' => self::TYPE_PRICE, 'validate' => 'isPrice', 'required' => true],
            'note'             => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => ObjectModel::SIZE_TEXT],
            'date_add'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'store_credit_transaction' => [
                'id_store_credit' => ['type' => ObjectModel::KEY, 'columns' => ['id_store_credit']],
                'entity'          => ['type' => ObjectModel::KEY, 'columns' => ['entity_type', 'id_entity']],
                'id_customer'     => ['type' => ObjectModel::KEY, 'columns' => ['id_customer']],
                'id_employee'     => ['type' => ObjectModel::KEY, 'columns' => ['id_employee']],
            ],
        ],
    ];

    /**
     * @param int $entityType
     * @param int $idEntity
     * @param int $transactionSign
     * @param int $transactionType
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function existsTransaction(int $entityType, int $idEntity, int $transactionSign, int $transactionType): bool
    {
        if ($idEntity <= 0) {
            return false;
        }

        $sql = (new DbQuery())
            ->select('1')
            ->from('store_credit_transaction')
            ->where('entity_type = ' . (int)$entityType)
            ->where('id_entity = ' . (int)$idEntity)
            ->where('transaction_sign = ' . (int)$transactionSign)
            ->where('transaction_type = ' . (int)$transactionType);

        return (bool)Db::readOnly()->getValue($sql);
    }

    /**
     * @param int $idOrder
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function hasOrderConsumption(int $idOrder): bool
    {
        return static::existsTransaction(
            static::ENTITY_ORDER,
            $idOrder,
            static::SIGN_DECREASE,
            static::TYPE_PAYMENT_INSTRUMENT
        );
    }

    /**
     * @param int $idOrder
     *
     * @return float
     *
     * @throws PrestaShopException
     */
    public static function getOrderConsumptionAmount(int $idOrder): float
    {
        if ($idOrder <= 0) {
            return 0.0;
        }

        $sql = (new DbQuery())
            ->select('SUM(amount_tax_incl)')
            ->from('store_credit_transaction')
            ->where('entity_type = ' . (int)static::ENTITY_ORDER)
            ->where('id_entity = ' . (int)$idOrder)
            ->where('transaction_sign = ' . (int)static::SIGN_DECREASE)
            ->where('transaction_type = ' . (int)static::TYPE_PAYMENT_INSTRUMENT);

        return (float)Db::readOnly()->getValue($sql);
    }

    /**
     * @param int $idOrder
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getOrderConsumptionTransactionId(int $idOrder): int
    {
        if ($idOrder <= 0) {
            return 0;
        }

        $sql = (new DbQuery())
            ->select('id_store_credit_transaction')
            ->from('store_credit_transaction')
            ->where('entity_type = ' . (int)static::ENTITY_ORDER)
            ->where('id_entity = ' . (int)$idOrder)
            ->where('transaction_sign = ' . (int)static::SIGN_DECREASE)
            ->where('transaction_type = ' . (int)static::TYPE_PAYMENT_INSTRUMENT)
            ->orderBy('id_store_credit_transaction DESC');

        return max(0, (int)Db::readOnly()->getValue($sql));
    }

    /**
     * @param int $idStoreCredit
     * @param int $idCustomer
     * @param int $idOrder
     * @param float $amountTaxIncl
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function addOrderConsumption(int $idStoreCredit, int $idCustomer, int $idOrder, float $amountTaxIncl): bool
    {
        $amountTaxIncl = Tools::roundPrice($amountTaxIncl);
        if ($idStoreCredit <= 0 || $idCustomer <= 0 || $idOrder <= 0 || $amountTaxIncl <= 0.0) {
            return false;
        }

        return static::addStoreCreditTransaction(
            $idStoreCredit,
            $idCustomer,
            static::SIGN_DECREASE,
            static::TYPE_PAYMENT_INSTRUMENT,
            static::ENTITY_ORDER,
            $idOrder,
            $amountTaxIncl
        );
    }

    /**
     * @param int $idStoreCredit
     * @param int $idCustomer
     * @param int $transactionSign
     * @param int $transactionType
     * @param int $entityType
     * @param int|null $idEntity
     * @param float $amountTaxIncl
     * @param int $idEmployee
     * @param string $note
     *
     * @return bool
     */
    public static function addStoreCreditTransaction(
        int $idStoreCredit,
        int $idCustomer,
        int $transactionSign,
        int $transactionType,
        int $entityType,
        ?int $idEntity,
        float $amountTaxIncl,
        int $idEmployee = 0,
        string $note = ''
    ): bool {
        $amountTaxIncl = Tools::roundPrice($amountTaxIncl);
        $idEmployee = max(0, (int)$idEmployee);
        $idEntity = $idEntity !== null ? (int)$idEntity : 0;
        $requiresEntityId = $entityType !== static::ENTITY_MANUAL;
        $note = trim($note);

        if (
            $idStoreCredit <= 0 ||
            $idCustomer <= 0 ||
            ($requiresEntityId && $idEntity <= 0) ||
            $amountTaxIncl <= 0.0 ||
            !static::isValidTransactionSign($transactionSign) ||
            !static::isValidTransactionType($transactionType) ||
            !static::isValidEntityType($entityType) ||
            ($note !== '' && !Validate::isCleanHtml($note))
        ) {
            return false;
        }

        $transaction = new static();
        $transaction->id_store_credit = $idStoreCredit;
        $transaction->transaction_sign = $transactionSign;
        $transaction->transaction_type = $transactionType;
        $transaction->entity_type = $entityType;
        $transaction->id_entity = $requiresEntityId ? $idEntity : null;
        $transaction->id_customer = $idCustomer;
        $transaction->id_employee = $idEmployee;
        $transaction->amount_tax_incl = $amountTaxIncl;
        $transaction->note = $note;

        return (bool)$transaction->add();
    }

    /**
     * @param int $idStoreCredit
     * @param int $idCustomer
     * @param int $transactionType
     * @param float $amountTaxIncl
     * @param int $idEmployee
     * @param string $note
     *
     * @return bool
     */
    public static function addManualIncrease(
        int $idStoreCredit,
        int $idCustomer,
        int $transactionType,
        float $amountTaxIncl,
        int $idEmployee = 0,
        string $note = ''
    ): bool {
        if ($transactionType === static::TYPE_PAYMENT_INSTRUMENT) {
            return false;
        }

        return static::addStoreCreditTransaction(
            $idStoreCredit,
            $idCustomer,
            static::SIGN_INCREASE,
            $transactionType,
            static::ENTITY_MANUAL,
            null,
            $amountTaxIncl,
            $idEmployee,
            $note
        );
    }

    /**
     * @param int $idStoreCredit
     * @param int $idCustomer
     * @param int $transactionType
     * @param float $amountTaxIncl
     * @param int $idEmployee
     * @param string $note
     *
     * @return bool
     */
    public static function addManualDecrease(
        int $idStoreCredit,
        int $idCustomer,
        int $transactionType,
        float $amountTaxIncl,
        int $idEmployee = 0,
        string $note = ''
    ): bool {
        if ($transactionType === static::TYPE_PAYMENT_INSTRUMENT) {
            return false;
        }

        return static::addStoreCreditTransaction(
            $idStoreCredit,
            $idCustomer,
            static::SIGN_DECREASE,
            $transactionType,
            static::ENTITY_MANUAL,
            null,
            $amountTaxIncl,
            $idEmployee,
            $note
        );
    }

    /**
     * @param int $sign
     *
     * @return bool
     */
    public static function isValidTransactionSign(int $sign): bool
    {
        return in_array($sign, [
            static::SIGN_INCREASE,
            static::SIGN_DECREASE,
        ], true);
    }

    /**
     * @param int $type
     *
     * @return bool
     */
    public static function isValidTransactionType(int $type): bool
    {
        return in_array($type, [
            static::TYPE_PAYMENT_INSTRUMENT,
            static::TYPE_REFUND_CREDIT,
            static::TYPE_MANUAL_ADJUSTMENT,
        ], true);
    }

    /**
     * @param int $type
     *
     * @return bool
     */
    public static function isValidEntityType(int $type): bool
    {
        return in_array($type, [
            static::ENTITY_ORDER,
            static::ENTITY_ORDER_SLIP,
            static::ENTITY_MANUAL,
        ], true);
    }
}
