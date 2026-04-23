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
 * Class StoreCreditCore
 */
class StoreCreditCore extends ObjectModel
{

    /**
     * @var int $id
     */
    public $id;

    /**
     * @var int $id_customer
     */
    public $id_customer;

    /**
     * @var string $date_from
     */
    public $date_from;

    /**
     * @var string $date_to
     */
    public $date_to;

    /**
     * @var int $quantity
     */
    public $amount;

    /**
     * @var string $date_add
     */
    public $date_add;

    /**
     * @var string $date_upd
     */
    public $date_upd;

    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'     => 'store_credit',
        'primary'   => 'id_store_credit',
        'fields'    => [
            'id_customer'  => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true, 'unique' => true],
            'date_from'    => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'dbDefault' => '0000-00-00 00:00:00'],
            'date_to'      => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'dbDefault' => '0000-00-00 00:00:00'],
            'amount'       => ['type' => self::TYPE_PRICE,  'validate' => 'isPrice'],
            'date_add'     => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'dbNullable' => false],
            'date_upd'     => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [],
    ];

    /**
     * @param $id
     * @param $idLang
     * @param $idShop
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        parent::__construct($id, $idLang, $idShop);
        if ($this->date_from === '0000-00-00 00:00:00') {
            $this->date_from = null;
        }
        if ($this->date_to === '0000-00-00 00:00:00') {
            $this->date_to = null;
        }
    }

    /**
     * Returns currently available store credit amount for customer in given shop.
     *
     * @param int $idShop
     * @param int $idCustomer
     *
     * @return float
     *
     * @throws PrestaShopException
     */
    public static function getCustomerAvailableAmount(int $idShop, int $idCustomer): float
    {
        $conn = Db::readOnly();
        $sql = (new DbQuery())
            ->select('SUM(c.amount)')
            ->from('store_credit', 'c')
            ->innerJoin('store_credit_shop', 'cs', 'c.id_store_credit = cs.id_store_credit AND cs.id_shop = ' . (int)$idShop)
            ->where('c.id_customer = ' . (int)$idCustomer)
            ->where('c.date_from <= NOW()')
            ->where('(c.date_to < "1900-00-00" OR c.date_to >= NOW())');

        return (float)$conn->getValue($sql);
    }

    /**
     * Consumes store credit for order and records transaction.
     *
     * @param int $idShop
     * @param int $idCustomer
     * @param int $idOrder
     * @param float $requestedAmountTaxIncl
     *
     * @return float
     *
     * @throws PrestaShopException
     */
    public static function consumeForOrder(int $idShop, int $idCustomer, int $idOrder, float $requestedAmountTaxIncl): float
    {
        $requestedAmountTaxIncl = Tools::roundPrice(max(0.0, $requestedAmountTaxIncl));
        if ($idShop <= 0 || $idCustomer <= 0 || $idOrder <= 0 || $requestedAmountTaxIncl <= 0.0) {
            return 0.0;
        }

        $conn = Db::getInstance();
        $sql = (new DbQuery())
            ->select('c.id_store_credit, c.amount')
            ->from('store_credit', 'c')
            ->innerJoin('store_credit_shop', 'cs', 'c.id_store_credit = cs.id_store_credit AND cs.id_shop = ' . (int)$idShop)
            ->where('c.id_customer = ' . (int)$idCustomer)
            ->where('c.date_from <= NOW()')
            ->where('(c.date_to < "1900-00-00" OR c.date_to >= NOW())');

        $row = $conn->getRow($sql);
        if (!is_array($row) || empty($row['id_store_credit'])) {
            return 0.0;
        }

        $idStoreCredit = (int)$row['id_store_credit'];
        $availableAmount = Tools::roundPrice(max(0.0, (float)$row['amount']));
        $consumedAmount = Tools::roundPrice(min($requestedAmountTaxIncl, $availableAmount));
        if ($consumedAmount <= 0.0) {
            return 0.0;
        }

        $consumedSql = pSQL((string)$consumedAmount);
        $updated = $conn->update(
            'store_credit',
            [
                'amount'   => ['type' => 'sql', 'value' => 'GREATEST(0, `amount` - ' . $consumedSql . ')'],
                'date_upd' => ['type' => 'sql', 'value' => 'NOW()'],
            ],
            'id_store_credit = ' . $idStoreCredit
        );
        if (!$updated) {
            return 0.0;
        }

        try {
            if (!StoreCreditTransaction::addOrderConsumption($idStoreCredit, $idCustomer, $idOrder, $consumedAmount)) {
                static::restoreConsumedAmount($conn, $idStoreCredit, $consumedSql);
                return 0.0;
            }
        } catch (Exception $exception) {
            static::restoreConsumedAmount($conn, $idStoreCredit, $consumedSql);
            Hook::triggerEvent('actionLogCaughtException', [
                'exception' => $exception,
                'extra_content' => [
                    'source' => __METHOD__,
                    'id_shop' => (int)$idShop,
                    'id_customer' => (int)$idCustomer,
                    'id_order' => (int)$idOrder,
                    'id_store_credit' => (int)$idStoreCredit,
                    'requested_amount_tax_incl' => (float)$requestedAmountTaxIncl,
                    'consumed_amount_tax_incl' => (float)$consumedAmount,
                ],
            ]);
            return 0.0;
        }

        return $consumedAmount;
    }

    /**
     * @param Db $conn
     * @param int $idStoreCredit
     * @param string $amountSql
     *
     * @return void
     */
    protected static function restoreConsumedAmount(Db $conn, int $idStoreCredit, string $amountSql): void
    {
        if ($idStoreCredit <= 0 || $amountSql === '') {
            return;
        }

        $conn->update(
            'store_credit',
            [
                'amount'   => ['type' => 'sql', 'value' => '`amount` + ' . $amountSql],
                'date_upd' => ['type' => 'sql', 'value' => 'NOW()'],
            ],
            'id_store_credit = ' . (int)$idStoreCredit
        );
    }

    /**
     * Adds refund credit for customer and records transaction.
     *
     * @param int $idShop
     * @param int $idCustomer
     * @param int $idOrder
     * @param int $idOrderSlip
     * @param float $amountTaxIncl
     * @param int $idEmployee
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function addRefundCreditForOrderSlip(int $idShop, int $idCustomer, int $idOrder, int $idOrderSlip, float $amountTaxIncl, int $idEmployee = 0): bool
    {
        $amountTaxIncl = Tools::roundPrice(max(0.0, $amountTaxIncl));
        $idEmployee = max(0, (int)$idEmployee);
        if ($idShop <= 0 || $idCustomer <= 0 || $idOrder <= 0 || $idOrderSlip <= 0 || $amountTaxIncl <= 0.0) {
            return false;
        }

        try {
            if (StoreCreditTransaction::existsTransaction(
                StoreCreditTransaction::ENTITY_ORDER_SLIP,
                $idOrderSlip,
                StoreCreditTransaction::SIGN_INCREASE,
                StoreCreditTransaction::TYPE_REFUND_CREDIT
            )) {
                return true;
            }

            $idStoreCredit = static::getStoreCreditIdByCustomer($idCustomer);
            if ($idStoreCredit <= 0) {
                $idStoreCredit = static::createStoreCreditForCustomer($idCustomer);
            }
            if ($idStoreCredit <= 0) {
                return false;
            }
            if (!static::associateStoreCreditToShop($idStoreCredit, $idShop)) {
                return false;
            }

            return static::increaseBalanceWithTransaction(
                $idStoreCredit,
                $amountTaxIncl,
                static function() use ($idStoreCredit, $idCustomer, $idOrderSlip, $amountTaxIncl, $idEmployee): bool {
                    return StoreCreditTransaction::addStoreCreditTransaction(
                        $idStoreCredit,
                        $idCustomer,
                        StoreCreditTransaction::SIGN_INCREASE,
                        StoreCreditTransaction::TYPE_REFUND_CREDIT,
                        StoreCreditTransaction::ENTITY_ORDER_SLIP,
                        $idOrderSlip,
                        $amountTaxIncl,
                        $idEmployee
                    );
                }
            );
        } catch (Exception $exception) {
            Hook::triggerEvent('actionLogCaughtException', [
                'exception' => $exception,
                'extra_content' => [
                    'source' => __METHOD__,
                    'id_shop' => (int)$idShop,
                    'id_customer' => (int)$idCustomer,
                    'id_order' => (int)$idOrder,
                    'id_order_slip' => (int)$idOrderSlip,
                    'amount_tax_incl' => (float)$amountTaxIncl,
                    'id_employee' => (int)$idEmployee,
                ],
            ]);
            return false;
        }
    }

    /**
     * @param int $idCustomer
     * @param float $amountTaxIncl
     * @param int $transactionType
     * @param int $idEmployee
     * @param string $note
     * @param array $idShops
     *
     * @return bool
     */
    public static function addManualCredit(
        int $idCustomer,
        float $amountTaxIncl,
        int $transactionType,
        int $idEmployee = 0,
        string $note = '',
        array $idShops = []
    ): bool {
        $amountTaxIncl = Tools::roundPrice((float)$amountTaxIncl);
        $idEmployee = max(0, (int)$idEmployee);
        $note = trim($note);
        $isDecrease = $amountTaxIncl < 0.0;
        $absoluteAmountTaxIncl = Tools::roundPrice(abs($amountTaxIncl));

        if (
            $idCustomer <= 0 ||
            $absoluteAmountTaxIncl <= 0.0 ||
            empty($idShops) ||
            !StoreCreditTransaction::isValidTransactionType($transactionType) ||
            $transactionType === StoreCreditTransaction::TYPE_PAYMENT_INSTRUMENT ||
            ($isDecrease && $transactionType !== StoreCreditTransaction::TYPE_MANUAL_ADJUSTMENT) ||
            ($note !== '' && !Validate::isCleanHtml($note))
        ) {
            return false;
        }

        try {
            $idStoreCredit = static::getStoreCreditIdByCustomer($idCustomer);
            if ($idStoreCredit <= 0) {
                $idStoreCredit = static::createStoreCreditForCustomer($idCustomer);
            }
            if ($idStoreCredit <= 0) {
                return false;
            }
            if (!static::associateStoreCreditToShops($idStoreCredit, $idShops)) {
                return false;
            }

            if ($isDecrease) {
                return static::decreaseBalanceWithTransaction(
                    $idStoreCredit,
                    $absoluteAmountTaxIncl,
                    static function() use ($idStoreCredit, $idCustomer, $transactionType, $absoluteAmountTaxIncl, $idEmployee, $note): bool {
                        return StoreCreditTransaction::addManualDecrease(
                            $idStoreCredit,
                            $idCustomer,
                            $transactionType,
                            $absoluteAmountTaxIncl,
                            $idEmployee,
                            $note
                        );
                    }
                );
            }

            return static::increaseBalanceWithTransaction(
                $idStoreCredit,
                $absoluteAmountTaxIncl,
                static function() use ($idStoreCredit, $idCustomer, $transactionType, $absoluteAmountTaxIncl, $idEmployee, $note): bool {
                    return StoreCreditTransaction::addManualIncrease(
                        $idStoreCredit,
                        $idCustomer,
                        $transactionType,
                        $absoluteAmountTaxIncl,
                        $idEmployee,
                        $note
                    );
                }
            );
        } catch (Exception $exception) {
            Hook::triggerEvent('actionLogCaughtException', [
                'exception' => $exception,
                'extra_content' => [
                    'source' => __METHOD__,
                    'id_customer' => (int)$idCustomer,
                    'transaction_type' => (int)$transactionType,
                    'id_employee' => (int)$idEmployee,
                    'amount_tax_incl' => (float)$absoluteAmountTaxIncl,
                    'is_decrease' => (bool)$isDecrease,
                    'id_shops' => array_map('intval', $idShops),
                    'note_length' => (int)Tools::strlen($note),
                ],
            ]);
            return false;
        }
    }

    /**
     * @param int $idCustomer
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getStoreCreditIdByCustomer(int $idCustomer): int
    {
        if ($idCustomer <= 0) {
            return 0;
        }

        $query = (new DbQuery())
            ->select('id_store_credit')
            ->from('store_credit')
            ->where('id_customer = ' . (int)$idCustomer)
            ->orderBy('id_store_credit ASC');

        return max(0, (int)Db::getInstance()->getValue($query));
    }

    /**
     * @param int $idCustomer
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function createStoreCreditForCustomer(int $idCustomer): int
    {
        if ($idCustomer <= 0) {
            return 0;
        }

        $storeCredit = new static();
        $storeCredit->id_customer = $idCustomer;
        $storeCredit->date_from = date('Y-m-d H:i:s');
        $storeCredit->amount = 0;

        if ($storeCredit->add()) {
            return (int)$storeCredit->id;
        }

        return static::getStoreCreditIdByCustomer($idCustomer);
    }

    /**
     * @param int $idStoreCredit
     * @param int $idShop
     *
     * @return bool
     */
    public static function associateStoreCreditToShop(int $idStoreCredit, int $idShop): bool
    {
        if ($idStoreCredit <= 0 || $idShop <= 0) {
            return false;
        }

        $sql = 'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'store_credit_shop` (`id_store_credit`, `id_shop`) VALUES (' . (int)$idStoreCredit . ', ' . (int)$idShop . ')';
        return (bool)Db::getInstance()->execute($sql, false);
    }

    /**
     * @param int $idStoreCredit
     * @param array $idShops
     *
     * @return bool
     */
    public static function associateStoreCreditToShops(int $idStoreCredit, array $idShops): bool
    {
        if ($idStoreCredit <= 0 || empty($idShops)) {
            return false;
        }

        $hasValidShop = false;
        foreach ($idShops as $idShop) {
            $idShop = (int)$idShop;
            if ($idShop <= 0) {
                continue;
            }
            $hasValidShop = true;
            if (!static::associateStoreCreditToShop($idStoreCredit, $idShop)) {
                return false;
            }
        }

        return $hasValidShop;
    }

    /**
     * @param int $idStoreCredit
     * @param float $amountTaxIncl
     * @param callable $writeTransaction
     *
     * @return bool
     */
    protected static function increaseBalanceWithTransaction(int $idStoreCredit, float $amountTaxIncl, callable $writeTransaction): bool
    {
        $amountTaxIncl = Tools::roundPrice(max(0.0, $amountTaxIncl));
        if ($idStoreCredit <= 0 || $amountTaxIncl <= 0.0) {
            return false;
        }

        $conn = Db::getInstance();
        $amountSql = pSQL((string)$amountTaxIncl);

        try {
            $conn->execute('START TRANSACTION');
            $updated = $conn->update(
                'store_credit',
                [
                    'amount'   => ['type' => 'sql', 'value' => '`amount` + ' . $amountSql],
                    'date_upd' => ['type' => 'sql', 'value' => 'NOW()'],
                ],
                'id_store_credit = ' . (int)$idStoreCredit
            );
            if (!$updated) {
                throw new RuntimeException('Failed to update store credit amount');
            }

            if (!$writeTransaction()) {
                throw new RuntimeException('Failed to create store credit transaction');
            }

            $conn->execute('COMMIT');
            return true;
        } catch (Exception $exception) {
            $conn->execute('ROLLBACK');
            Hook::triggerEvent('actionLogCaughtException', [
                'exception' => $exception,
                'extra_content' => [
                    'source' => __METHOD__,
                    'id_store_credit' => (int)$idStoreCredit,
                    'amount_tax_incl' => (float)$amountTaxIncl,
                ],
            ]);
            return false;
        }
    }

    /**
     * @param int $idStoreCredit
     * @param float $amountTaxIncl
     * @param callable $writeTransaction
     *
     * @return bool
     */
    protected static function decreaseBalanceWithTransaction(int $idStoreCredit, float $amountTaxIncl, callable $writeTransaction): bool
    {
        $amountTaxIncl = Tools::roundPrice(max(0.0, $amountTaxIncl));
        if ($idStoreCredit <= 0 || $amountTaxIncl <= 0.0) {
            return false;
        }

        $conn = Db::getInstance();
        $amountSql = pSQL((string)$amountTaxIncl);

        try {
            $conn->execute('START TRANSACTION');
            $updated = $conn->update(
                'store_credit',
                [
                    'amount'   => ['type' => 'sql', 'value' => '`amount` - ' . $amountSql],
                    'date_upd' => ['type' => 'sql', 'value' => 'NOW()'],
                ],
                'id_store_credit = ' . (int)$idStoreCredit . ' AND `amount` >= ' . $amountSql
            );
            if (!$updated || $conn->Affected_Rows() <= 0) {
                throw new RuntimeException('Failed to update store credit amount');
            }

            if (!$writeTransaction()) {
                throw new RuntimeException('Failed to create store credit transaction');
            }

            $conn->execute('COMMIT');
            return true;
        } catch (Exception $exception) {
            $conn->execute('ROLLBACK');
            Hook::triggerEvent('actionLogCaughtException', [
                'exception' => $exception,
                'extra_content' => [
                    'source' => __METHOD__,
                    'id_store_credit' => (int)$idStoreCredit,
                    'amount_tax_incl' => (float)$amountTaxIncl,
                ],
            ]);
            return false;
        }
    }


    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function isFeatureActive(): bool
    {
        return static::isCurrentlyUsed('store_credit');
    }

}
