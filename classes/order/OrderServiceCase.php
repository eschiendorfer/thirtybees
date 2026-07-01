<?php

/**
 * Class OrderServiceCaseCore
 */
class OrderServiceCaseCore extends ObjectModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    public const TYPE_DELIVERY_DAMAGE = 1;
    public const TYPE_ITEMS_MISSING = 2;
    public const TYPE_ITEMS_BROKEN = 3;
    public const TYPE_OTHER = 4;

    public const SOLUTION_REPLACEMENT = 1;
    public const SOLUTION_ALTERNATE_PRODUCT = 2;
    public const SOLUTION_VOUCHER = 3;

    public static $definition = [
        'table' => 'order_service_case',
        'primary' => 'id_order_service_case',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbNullable' => true],
            'case_type' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'requested_solution' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbNullable' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16, 'required' => true, 'dbDefault' => self::STATUS_OPEN],
            'id_replacement_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbNullable' => true],
            'migrated' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbType' => 'tinyint(1) unsigned', 'dbDefault' => '0'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'order_service_case' => [
                'id_order' => ['type' => ObjectModel::KEY, 'columns' => ['id_order']],
                'status' => ['type' => ObjectModel::KEY, 'columns' => ['status']],
                'id_replacement_order' => ['type' => ObjectModel::KEY, 'columns' => ['id_replacement_order']],
            ],
        ],
    ];

    /** @var int */
    public $id;
    /** @var int */
    public $id_order;
    /** @var int|null */
    public $id_employee;
    /** @var int */
    public $case_type;
    /** @var int|null */
    public $requested_solution;
    /** @var string */
    public $status = self::STATUS_OPEN;
    /** @var int|null */
    public $id_replacement_order;
    /** @var bool */
    public $migrated = false;
    /** @var string */
    public $date_add;
    /** @var string */
    public $date_upd;

    public static function getValidCaseTypes(): array
    {
        return [
            self::TYPE_DELIVERY_DAMAGE,
            self::TYPE_ITEMS_MISSING,
            self::TYPE_ITEMS_BROKEN,
            self::TYPE_OTHER,
        ];
    }

    public static function getValidRequestedSolutions(): array
    {
        return [
            self::SOLUTION_REPLACEMENT,
            self::SOLUTION_ALTERNATE_PRODUCT,
            self::SOLUTION_VOUCHER,
        ];
    }

    public static function createOpenForOrderDetail(
        Order $order,
        int $idOrderDetail,
        int $quantity,
        int $caseType,
        ?int $requestedSolution = null
    ): ?self {
        if (
            !Validate::isLoadedObject($order)
            || $idOrderDetail <= 0
            || $quantity <= 0
            || !in_array($caseType, self::getValidCaseTypes(), true)
            || ($requestedSolution !== null && !in_array($requestedSolution, self::getValidRequestedSolutions(), true))
        ) {
            return null;
        }

        $orderDetail = new OrderDetail($idOrderDetail);
        if (!Validate::isLoadedObject($orderDetail) || (int)$orderDetail->id_order !== (int)$order->id) {
            return null;
        }

        if ($quantity > self::getServiceableQuantity($orderDetail)) {
            return null;
        }

        $db = Db::getInstance();
        $db->execute('START TRANSACTION');

        try {
            $serviceCase = new self();
            $serviceCase->id_order = (int)$order->id;
            $serviceCase->case_type = $caseType;
            $serviceCase->requested_solution = $requestedSolution;
            $serviceCase->status = self::STATUS_OPEN;

            if (!$serviceCase->add(true, true)) {
                throw new PrestaShopException('Order service case could not be saved.');
            }

            $detail = new OrderServiceCaseDetail();
            $detail->id_order_service_case = (int)$serviceCase->id;
            $detail->id_order_detail = $idOrderDetail;
            $detail->product_quantity = $quantity;

            if (!$detail->add()) {
                throw new PrestaShopException('Order service case detail could not be saved.');
            }

            $db->execute('COMMIT');
            return $serviceCase;
        } catch (Exception $exception) {
            $db->execute('ROLLBACK');
            return null;
        }
    }

    private static function getServiceableQuantity(OrderDetail $orderDetail): int
    {
        $shippedQuantity = (int)$orderDetail->product_quantity;
        if (class_exists('\\CrmModule\\OrderDetailExtension')) {
            $orderDetailExtension = new \CrmModule\OrderDetailExtension((int)$orderDetail->id);
            if (Validate::isLoadedObject($orderDetailExtension)) {
                $shippedQuantity = (int)$orderDetailExtension->shipping_quantity;
            }
        }

        return max(
            0,
            min((int)$orderDetail->product_quantity, $shippedQuantity)
            - (int)$orderDetail->product_quantity_return
            - OrderReturn::getOpenReturnQuantityByOrderDetail((int)$orderDetail->id)
        );
    }
}
