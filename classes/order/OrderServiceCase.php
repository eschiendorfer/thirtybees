<?php

/**
 * Class OrderServiceCaseCore
 */
class OrderServiceCaseCore extends ObjectModel implements CustomerThreadContextSourceInterfaceCore
{
    public const CONFIG_PERIOD_DAYS = 'PS_CUSTOMER_SERVICE_CASE_PERIOD_DAYS';
    public const DEFAULT_PERIOD_DAYS = 730;

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

    /**
     * @return array<string, mixed>
     */
    public function getCustomerThreadContextData(): array
    {
        $order = new Order((int) $this->id_order);
        $orderReference = Validate::isLoadedObject($order) ? (string) $order->reference : '';
        $details = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('oscd.`id_order_detail`, oscd.`product_quantity` AS `quantity`, od.`product_id` AS `id_product`, od.`product_attribute_id` AS `id_product_attribute`, od.`product_reference`, od.`product_name`')
                ->from('order_service_case_detail', 'oscd')
                ->leftJoin('order_detail', 'od', 'od.`id_order_detail` = oscd.`id_order_detail`')
                ->where('oscd.`id_order_service_case` = '.(int) $this->id)
                ->orderBy('oscd.`id_order_service_case_detail` ASC')
        );

        $fields = [];
        if ($orderReference !== '') {
            $fields[] = [
                'label'  => 'Order',
                'value'  => $orderReference,
                'target' => [
                    'controller' => 'AdminOrders',
                    'params'     => [
                        'vieworder' => 1,
                        'id_order'  => (int) $this->id_order,
                    ],
                ],
            ];
        }
        $fields[] = [
            'label'           => 'Case type',
            'value'           => static::getCaseTypeLabelSource((int) $this->case_type),
            'translate_value' => true,
        ];
        $fields[] = [
            'label'           => 'Requested solution',
            'value'           => static::getRequestedSolutionLabelSource(
                (int) $this->requested_solution > 0 ? (int) $this->requested_solution : null
            ),
            'translate_value' => true,
        ];

        return [
            'context_type'            => 'order_service_case',
            'title'                   => 'Service case',
            'reference'               => '#'.(int) $this->id,
            'related_order_id'        => (int) $this->id_order,
            'related_order_reference' => $orderReference,
            'product_quantity_label'  => 'Affected quantity',
            'fields'                  => $fields,
            'products'                => $details,
            'action'                  => [
                'label'      => 'Open service case',
                'controller' => 'AdminOrderServiceCases',
                'params'     => [
                    'vieworder_service_case'     => 1,
                    'id_order_service_case'      => (int) $this->id,
                ],
            ],
        ];
    }

    public static function getCaseTypeLabelSource(int $caseType): string
    {
        $labels = [
            self::TYPE_DELIVERY_DAMAGE => 'Delivery damage',
            self::TYPE_ITEMS_MISSING   => 'Items missing',
            self::TYPE_ITEMS_BROKEN    => 'Items broken',
            self::TYPE_OTHER           => 'Other',
        ];

        return $labels[$caseType] ?? 'Unknown';
    }

    public static function getRequestedSolutionLabelSource(?int $requestedSolution): string
    {
        if ($requestedSolution === null) {
            return '-';
        }

        $labels = [
            self::SOLUTION_REPLACEMENT       => 'Replacement',
            self::SOLUTION_ALTERNATE_PRODUCT => 'Alternate product',
            self::SOLUTION_VOUCHER           => 'Voucher',
        ];

        return $labels[$requestedSolution] ?? 'Unknown';
    }

    public static function createOpenForOrderDetail(
        Order $order,
        int $idOrderDetail,
        int $quantity,
        int $caseType,
        ?int $requestedSolution = null,
        bool $withinTransaction = false
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

        if ($quantity > static::getServiceableQuantity($order, $orderDetail)) {
            return null;
        }

        $db = Db::getInstance();
        if (!$withinTransaction && !$db->execute('START TRANSACTION')) {
            return null;
        }

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

            if (!$withinTransaction) {
                if (!$db->execute('COMMIT')) {
                    throw new PrestaShopException('Order service case transaction could not be committed.');
                }
            }
            return $serviceCase;
        } catch (Throwable $exception) {
            if (!$withinTransaction) {
                $db->execute('ROLLBACK');
            }
            return null;
        }
    }

    public static function getServiceableQuantity(Order $order, OrderDetail $orderDetail): int
    {
        if (
            !Validate::isLoadedObject($order)
            || !Validate::isLoadedObject($orderDetail)
            || (int)$orderDetail->id_order !== (int)$order->id
            || static::isShippingTriggerProduct((int)$orderDetail->product_id)
            || !class_exists('\\CrmModule\\OrderDetailExtension')
            || !\CrmModule\OrderDetailExtension::isWithinShippingPeriod(
                $order,
                $orderDetail,
                static::getPeriodDays()
            )
        ) {
            return 0;
        }

        $capabilities = (new RefundEligibilityService(new RefundPolicy(), new CancelEligibilityService()))
            ->getOrderDetailActionCapabilities($order, $orderDetail);
        $shippedAndAvailable = min(
            max(0, (int)($capabilities['serviceable_quantity'] ?? 0)),
            max(0, (int)($capabilities['returnable_quantity'] ?? 0))
        );

        return max(0, $shippedAndAvailable - static::getOpenServiceCaseQuantity((int)$orderDetail->id));
    }

    public static function getPeriodDays(): int
    {
        $value = Configuration::get(static::CONFIG_PERIOD_DAYS);

        return $value === false ? static::DEFAULT_PERIOD_DAYS : max(0, (int)$value);
    }

    private static function getOpenServiceCaseQuantity(int $idOrderDetail): int
    {
        return (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('COALESCE(SUM(oscd.`product_quantity`), 0)')
                ->from('order_service_case_detail', 'oscd')
                ->innerJoin(
                    'order_service_case',
                    'osc',
                    'osc.`id_order_service_case` = oscd.`id_order_service_case`'
                )
                ->where('oscd.`id_order_detail` = '.(int)$idOrderDetail)
                ->where("osc.`status` IN ('".static::STATUS_OPEN."', '".static::STATUS_WAITING."')")
        );
    }

    private static function isShippingTriggerProduct(int $idProduct): bool
    {
        return class_exists('SpielezarHelper')
            && $idProduct === (int)SpielezarHelper::PRODUCT_SHIPPING_TRIGGER;
    }
}
