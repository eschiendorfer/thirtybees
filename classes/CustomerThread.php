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
 * Class CustomerThreadCore
 */
class CustomerThreadCore extends ObjectModel
{
    public const CUSTOMER_SERVICE_EMPLOYEE_IDS = [1, 2, 5, 7, 9, 14];

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'pending1';
    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';
    public const STATUS_CLOSED = 'closed';

    /** @var int $id_contact */
    public $id_contact;
    /** @var int $id_customer */
    public $id_customer;
    /** @var int $id_employee_assigned */
    public $id_employee_assigned;
    /**
     * Legacy order context for order-related threads.
     *
     * TODO: Prefer entity_type/id_entity for new workflows. Long-term target is to
     * model returns, cancellations, service cases, and other thread subjects with
     * explicit entity references so automation can reliably understand the topic.
     * Remove this column if the order context can be derived safely from the target
     * entity.
     *
     * @deprecated Use entity_type/id_entity. Kept temporarily for schema compatibility only.
     * @var int $id_order
     */
    public $id_order;
    /**
     * Legacy product context inside an order.
     *
     * TODO: Do not use this for new domain workflows. Use a concrete
     * entity_type/id_entity target such as an order return or service case.
     * Long-term target is to remove this column.
     *
     * @deprecated Use entity_type/id_entity. Kept temporarily for schema compatibility only.
     * @var int $id_product
     */
    public $id_product;
    /** @var int $entity_type */
    public $entity_type;
    /** @var int $id_entity */
    public $id_entity;
    /**
     * Structured transition data for request types that do not yet have a
     * dedicated business entity. Stable workflows should use
     * entity_type/id_entity instead.
     *
     * @var string|null $data
     */
    public $data;
    /** @var string $status */
    public $status;
    /** @var string $email */
    public $email;
    /** @var string $token */
    public $token;
    /** @var string $date_add */
    public $date_add;
    /** @var string $date_upd */
    public $date_upd;

    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'   => 'customer_thread',
        'primary' => 'id_customer_thread',
        'fields'  => [
            'id_shop'     => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '1'],
            'id_lang'     => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_contact'  => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_employee_assigned' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'id_order'    => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_product'  => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'entity_type' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'id_entity'   => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'data'        => ['type' => self::TYPE_STRING, 'dbType' => 'longtext', 'charset' => ['utf8mb4', 'utf8mb4_bin'], 'allow_null' => true, 'dbNullable' => true],
            'status'      => ['type' => self::TYPE_STRING, 'values' => ['open', 'closed', 'pending1', 'waiting_customer'], 'dbDefault' => 'open'],
            'email'       => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 128, 'dbNullable' => false],
            'token'       => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 12, 'dbNullable' => true],
            'date_add'    => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd'    => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'customer_thread' => [
                'id_contact'  => ['type' => ObjectModel::KEY, 'columns' => ['id_contact']],
                'id_customer' => ['type' => ObjectModel::KEY, 'columns' => ['id_customer']],
                'id_employee_assigned' => ['type' => ObjectModel::KEY, 'columns' => ['id_employee_assigned']],
                'id_lang'     => ['type' => ObjectModel::KEY, 'columns' => ['id_lang']],
                'id_order'    => ['type' => ObjectModel::KEY, 'columns' => ['id_order']],
                'id_product'  => ['type' => ObjectModel::KEY, 'columns' => ['id_product']],
                'entity'      => ['type' => ObjectModel::KEY, 'columns' => ['entity_type', 'id_entity']],
                'id_shop'     => ['type' => ObjectModel::KEY, 'columns' => ['id_shop']],
            ],
        ],
    ];

    /**
     * Find the single conversation belonging to a concrete entity.
     */
    public static function getIdByEntity($idCustomer, $entityType, $idEntity)
    {
        if ((int) $idCustomer <= 0 || (int) $entityType <= 0 || (int) $idEntity <= 0) {
            return 0;
        }

        // This lookup decides whether a write creates a new thread. Use the
        // primary connection so replica lag cannot create avoidable duplicates.
        return (int) Db::getInstance()->getValue(
            (new DbQuery())
                ->select('`id_customer_thread`')
                ->from(static::$definition['table'])
                ->where('`id_customer` = '.(int) $idCustomer)
                ->where('`entity_type` = '.(int) $entityType)
                ->where('`id_entity` = '.(int) $idEntity)
                ->orderBy('`id_customer_thread` ASC')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getDataArray()
    {
        if (!$this->data) {
            return [];
        }

        $data = json_decode((string) $this->data, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setDataArray(array $data)
    {
        $this->data = $data
            ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
    }

    /**
     * @var array Webservice parameters
     */
    protected $webserviceParameters = [
        'fields'       => [
            'id_lang'     => [
                'xlink_resource' => 'languages',
            ],
            'id_shop'     => [
                'xlink_resource' => 'shops',
            ],
            'id_customer' => [
                'xlink_resource' => 'customers',
            ],
            'id_order'    => [
                'xlink_resource' => 'orders',
            ],
            'id_product'  => [
                'xlink_resource' => 'products',
            ],
            'entity_type' => [],
            'id_entity'   => [],
        ],
        'associations' => [
            'customer_messages' => [
                'resource' => 'customer_message',
                'id'       => ['required' => true],
            ],
        ],
    ];

    /**
     * @param int $idCustomer
     * @param int|null $read
     * @param int|null $entityType
     * @param int|null $idEntity
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getCustomerMessages($idCustomer, $read = null, $entityType = null, $idEntity = null)
    {
        $sql = (new DbQuery())
            ->select('*')
            ->from('customer_thread', 'ct')
            ->leftJoin('customer_message', 'cm', 'ct.`id_customer_thread` = cm.`id_customer_thread`')
            ->where('`id_customer` = '.(int) $idCustomer)
            ->orderBy('cm.`date_add` ASC, cm.`id_customer_message` ASC');

        if ($read !== null) {
            $sql->where('cm.`read` = '.(int) $read);
        }
        if ($entityType !== null || $idEntity !== null) {
            if ((int)$entityType <= 0 || (int)$idEntity <= 0) {
                return [];
            }
            $sql->where('ct.`entity_type` = '.(int)$entityType);
            $sql->where('ct.`id_entity` = '.(int)$idEntity);
        }

        return CustomerMessageAttachment::appendToMessages(Db::readOnly()->getArray($sql));
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getContacts()
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('cl.*, COUNT(*) as `total`')
                ->select('(SELECT `id_customer_thread` FROM `'._DB_PREFIX_.'customer_thread` ct2 WHERE status = "open" AND ct.`id_contact` = ct2.`id_contact` '.Shop::addSqlRestriction().' ORDER BY `date_upd` ASC LIMIT 1) AS `id_customer_thread`')
                ->from('customer_thread', 'ct')
                ->leftJoin('contact_lang', 'cl', 'cl.`id_contact` = ct.`id_contact` AND cl.`id_lang` = '.(int) Context::getContext()->language->id)
                ->where('ct.`status` = "open"')
                ->where('ct.`id_contact` IS NOT NULL')
                ->where('cl.`id_contact` IS NOT NULL '.Shop::addSqlRestriction())
                ->groupBy('ct.`id_contact`')
                ->having('COUNT(*) > 0')
        );
    }

    /**
     * @param string|null $where
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getTotalCustomerThreads($where = null)
    {
        return (int) Db::readOnly()->getValue(
            (new DbQuery())
                ->select('COUNT(*)')
                ->from('customer_thread')
                ->where(($where ?: '1').' '.Shop::addSqlRestriction())
        );
    }

    /**
     * @param int $idCustomerThread
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getMessageCustomerThreads($idCustomerThread)
    {
        $messages = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('ct.*, cm.*, cl.name subject, CONCAT(e.firstname, \' \', e.lastname) employee_name')
                ->select('CONCAT(c.firstname, \' \', c.lastname) customer_name, c.firstname')
                ->from('customer_thread', 'ct')
                ->leftJoin('customer_message', 'cm', 'ct.`id_customer_thread` = cm.`id_customer_thread`')
                ->leftJoin('contact_lang', 'cl', 'cl.`id_contact` = ct.`id_contact` AND cl.`id_lang` = '.(int) Context::getContext()->language->id)
                ->leftJoin('employee', 'e', 'e.`id_employee` = cm.`id_employee`')
                ->leftJoin('customer', 'c', '(IFNULL(ct.`id_customer`, ct.`email`) = IFNULL(c.`id_customer`, c.`email`))')
                ->where('ct.`id_customer_thread` = '.(int) $idCustomerThread)
                ->orderBy('cm.`date_add` ASC')
        );

        return CustomerMessageAttachment::appendToMessages($messages);
    }

    /**
     * @param int $idCustomerThread
     *
     * @return false|null|string
     *
     * @throws PrestaShopException
     */
    public static function getNextThread($idCustomerThread)
    {
        $context = Context::getContext();

        return Db::readOnly()->getValue(
            (new DbQuery())
                ->select('`id_customer_thread`')
                ->from('customer_thread', 'ct')
                ->where('ct.status = "open"')
                ->where('ct.`date_upd` = (SELECT date_add FROM '._DB_PREFIX_.'customer_message WHERE (id_employee IS NULL OR id_employee = 0) AND id_customer_thread = '.(int) $idCustomerThread.' ORDER BY date_add DESC LIMIT 1)')
                ->where($context->cookie->{'customer_threadFilter_cl!id_contact'} ? 'ct.`id_contact` = '.(int) $context->cookie->{'customer_threadFilter_cl!id_contact'} : '')
                ->where($context->cookie->{'customer_threadFilter_l!id_lang'} ? 'ct.`id_lang` = '.(int) $context->cookie->{'customer_threadFilter_l!id_lang'} : '')
                ->orderBy('ct.`date_upd` ASC')
        );
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getWsCustomerMessages()
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_customer_message` AS `id`')
                ->from('customer_message')
                ->where('`id_customer_thread` = '.(int) $this->id)
        );
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function delete()
    {
        if (!Validate::isUnsignedId($this->id)) {
            return false;
        }

        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) {
            return false;
        }

        $deletionPlan = ['ids' => [], 'paths' => []];
        try {
            $result = $db->getArray(
            (new DbQuery())
                ->select('`id_customer_message`')
                ->from('customer_message')
                ->where('`id_customer_thread` = '.(int) $this->id)
            );

            foreach ($result as $res) {
                $message = new CustomerMessage((int) $res['id_customer_message']);
                if (
                    !Validate::isLoadedObject($message)
                    || !$message->deleteWithinTransaction($deletionPlan)
                ) {
                    $db->execute('ROLLBACK');

                    return false;
                }
            }

            if (!parent::delete()) {
                $db->execute('ROLLBACK');

                return false;
            }
            if (!$db->execute('COMMIT')) {
                $db->execute('ROLLBACK');

                return false;
            }
        } catch (Exception $exception) {
            $db->execute('ROLLBACK');
            throw $exception;
        }

        CustomerMessageAttachment::deletePreparedFiles($deletionPlan);

        return true;
    }
}
