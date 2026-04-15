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
    const TYPE_INCREASE = 1;
    const TYPE_DECREASE = 2;

    const ECONOMIC_PAYMENT_INSTRUMENT = 1;
    const ECONOMIC_REFUND_CREDIT = 2;
    const ECONOMIC_MANUAL_ADJUSTMENT = 3;

    const ENTITY_ORDER = 1;
    const ENTITY_ORDER_SLIP = 2;

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
    public $transaction_type;

    /**
     * @var int
     */
    public $economic_type;

    /**
     * @var int
     */
    public $entity_type;

    /**
     * @var int
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
            'transaction_type' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
                'values' => [self::TYPE_INCREASE, self::TYPE_DECREASE],
            ],
            'economic_type'    => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
                'values' => [self::ECONOMIC_PAYMENT_INSTRUMENT, self::ECONOMIC_REFUND_CREDIT, self::ECONOMIC_MANUAL_ADJUSTMENT],
            ],
            'entity_type'      => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
                'values' => [self::ENTITY_ORDER, self::ENTITY_ORDER_SLIP],
            ],
            'id_entity'        => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
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
     * @param int $type
     *
     * @return bool
     */
    public static function isValidTransactionType(int $type): bool
    {
        return in_array($type, [
            static::TYPE_INCREASE,
            static::TYPE_DECREASE,
        ], true);
    }

    /**
     * @param int $type
     *
     * @return bool
     */
    public static function isValidEconomicType(int $type): bool
    {
        return in_array($type, [
            static::ECONOMIC_PAYMENT_INSTRUMENT,
            static::ECONOMIC_REFUND_CREDIT,
            static::ECONOMIC_MANUAL_ADJUSTMENT,
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
        ], true);
    }
}
