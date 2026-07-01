<?php

/**
 * Class OrderServiceCaseDetailCore
 */
class OrderServiceCaseDetailCore extends ObjectModel
{
    public static $definition = [
        'table' => 'order_service_case_detail',
        'primary' => 'id_order_service_case_detail',
        'fields' => [
            'id_order_service_case' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order_detail' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'product_quantity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true, 'dbDefault' => '0'],
        ],
        'keys' => [
            'order_service_case_detail' => [
                'id_order_service_case' => ['type' => ObjectModel::KEY, 'columns' => ['id_order_service_case']],
                'id_order_detail' => ['type' => ObjectModel::KEY, 'columns' => ['id_order_detail']],
            ],
        ],
    ];

    /** @var int */
    public $id;
    /** @var int */
    public $id_order_service_case;
    /** @var int */
    public $id_order_detail;
    /** @var int */
    public $product_quantity;
}
