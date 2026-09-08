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
 * Class CustomerMessageCore
 */
class CustomerMessageCore extends ObjectModel
{
    private const CONTENT_ALLOWED_TAGS = ['br', 'p', 'ul', 'li', 'em', 's', 'strong', 'b'];

    /**
     * @var int $id_customer_thread
     */
    public $id_customer_thread;

    /**
     * @var int $id_employee
     */
    public $id_employee;

    /**
     * @var string $message
     */
    public $message;

    /**
     * @var string $ip_address
     */
    public $ip_address;

    /**
     * @var string $user_agent
     */
    public $user_agent;

    /**
     * @var int $private
     */
    public $private;

    /**
     * @var string $date_add
     */
    public $date_add;

    /**
     * @var string $date_upd
     */
    public $date_upd;

    /**
     * @var bool $read
     */
    public $read;

    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'   => 'customer_message',
        'primary' => 'id_customer_message',
        'fields'  => [
            'id_customer_thread' => ['type' => self::TYPE_INT, 'dbType' => 'int(11)'],
            'id_employee'        => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            // An attachment is real message content on its own. The persistence service
            // therefore accepts an empty text only when at least one attachment exists.
            'message'            => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml', 'required' => false, 'dbNullable' => false, 'size' => ObjectModel::SIZE_MEDIUM_TEXT],
            'ip_address'         => ['type' => self::TYPE_STRING, 'validate' => 'isIp2Long', 'size' => 16],
            'user_agent'         => ['type' => self::TYPE_STRING, 'size' => 250],
            'date_add'           => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd'           => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'private'            => ['type' => self::TYPE_INT, 'dbType' => 'tinyint(4)', 'dbDefault' => '0'],
            'read'               => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbType' => 'tinyint(1)', 'dbDefault' => '0'],
        ],
        'keys' => [
            'customer_message' => [
                'id_customer_thread' => ['type' => ObjectModel::KEY, 'columns' => ['id_customer_thread']],
                'id_employee'        => ['type' => ObjectModel::KEY, 'columns' => ['id_employee']],
            ],
        ],
    ];

    /**
     * @var array Webservice parameters
     */
    protected $webserviceParameters = [
        'fields' => [
            'id_employee'        => [
                'xlink_resource' => 'employees',
            ],
            'id_customer_thread' => [
                'xlink_resource' => 'customer_threads',
            ],
        ],
    ];

    /**
     * Keep the small formatting subset supported by the customer-service editor.
     *
     * @param string $content
     *
     * @return string
     */
    public static function sanitizeContent($content)
    {
        $allowedTags = '<' . implode('><', self::CONTENT_ALLOWED_TAGS) . '>';
        $content = str_replace("\0", '', trim((string) $content));
        $content = strip_tags($content, $allowedTags);
        $content = preg_replace_callback(
            '/<\s*(\/?)\s*([a-z0-9]+)(?:\s[^>]*)?\s*\/?>/i',
            static function ($matches) {
                $tag = mb_strtolower((string) $matches[2], 'UTF-8');
                if (!in_array($tag, self::CONTENT_ALLOWED_TAGS, true)) {
                    return '';
                }
                if ($tag === 'br') {
                    return '<br>';
                }

                return !empty($matches[1]) ? '</' . $tag . '>' : '<' . $tag . '>';
            },
            $content
        );

        return trim((string) $content);
    }

    /**
     * Check whether formatted message content contains visible text.
     *
     * @param string $content
     *
     * @return bool
     */
    public static function hasVisibleContent($content)
    {
        $content = preg_replace('/<(?:br|\/p|\/li)>/i', "\n", static::sanitizeContent($content));
        $content = html_entity_decode(strip_tags((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace("\xc2\xa0", ' ', $content);

        return trim($content) !== '';
    }

    /**
     * Render both legacy plain-text messages and formatted customer-service messages.
     *
     * @param string $content
     *
     * @return string
     */
    public static function renderContent($content)
    {
        $content = static::sanitizeContent($content);
        $content = str_replace(["\\r\\n", "\r\n", "\\r", "\r", "\\n", "\n"], '<br>', $content);
        $content = preg_replace('/<p>\s*(?:&nbsp;|<br>)*\s*<\/p>/i', '', $content);
        $content = preg_replace('/(?:<br>\s*)+$/i', '', $content);

        return (string) $content;
    }

    /**
     * Render message content for an interactive web view.
     *
     * URLs remain plain text in storage and email output. Only the web-facing
     * representation turns explicit HTTP(S) URLs into safe links.
     *
     * @param string $content
     *
     * @return string
     */
    public static function renderWebContent($content)
    {
        $content = static::renderContent($content);
        $parts = preg_split('/(<[^>]+>)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $content;
        }

        foreach ($parts as $index => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }
            $linkedPart = preg_replace_callback(
                '~\bhttps?://[^\s<]+~iu',
                static function ($matches) {
                    $url = (string)$matches[0];
                    $suffix = '';

                    while ($url !== '' && preg_match('/[.,;:!?]$/u', $url)) {
                        $suffix = mb_substr($url, -1, 1, 'UTF-8').$suffix;
                        $url = mb_substr($url, 0, -1, 'UTF-8');
                    }
                    foreach ([')' => '(', ']' => '[', '}' => '{'] as $closing => $opening) {
                        while (
                            mb_substr($url, -1, 1, 'UTF-8') === $closing
                            && substr_count($url, $closing) > substr_count($url, $opening)
                        ) {
                            $suffix = $closing.$suffix;
                            $url = mb_substr($url, 0, -1, 'UTF-8');
                        }
                    }

                    $href = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (!preg_match('~^https?://~i', $href)) {
                        return (string)$matches[0];
                    }

                    return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer nofollow">'
                        .$url.'</a>'.$suffix;
                },
                $part
            );
            $parts[$index] = $linkedPart === null ? $part : $linkedPart;
        }

        return implode('', $parts);
    }

    /**
     * @param int $idOrder
     * @param bool $hidePrivate
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getMessagesByEntity($entityType, $idEntity, $hidePrivate = true)
    {
        if ((int)$entityType <= 0 || (int)$idEntity <= 0) {
            return [];
        }

        $messages = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('cm.*')
                ->select('c.`firstname` AS `cfirstname`')
                ->select('c.`lastname` AS `clastname`')
                ->select('e.`firstname` AS `efirstname`')
                ->select('e.`lastname` AS `elastname`')
                ->select('(COUNT(cm.id_customer_message) = 0 AND ct.id_customer != 0) AS is_new_for_me')
                ->from('customer_message', 'cm')
                ->leftJoin('customer_thread', 'ct', 'ct.`id_customer_thread` = cm.`id_customer_thread`')
                ->leftJoin('customer', 'c', 'ct.`id_customer` = c.`id_customer`')
                ->leftOuterJoin('employee', 'e', 'e.`id_employee` = cm.`id_employee`')
                ->where('ct.`entity_type` = '.(int)$entityType)
                ->where('ct.`id_entity` = '.(int)$idEntity)
                ->where($hidePrivate ? 'cm.`private` = 0' : '')
                ->groupBy('cm.`id_customer_message`')
                ->orderBy('cm.`date_add` DESC')
        );

        return CustomerMessageAttachment::appendToMessages($messages);
    }

    /**
     * @param string|null $where
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getTotalCustomerMessages($where = null)
    {
        $conn = Db::readOnly();
        if (is_null($where)) {
            return (int) $conn->getValue(
                (new DbQuery())
                    ->select('COUNT(*)')
                    ->from('customer_message')
                    ->leftJoin('customer_thread', 'ct', 'cm.`id_customer_thread` = ct.`id_customer_thread`')
                    ->where('1 '.Shop::addSqlRestriction())
            );
        } else {
            return (int) $conn->getValue(
                (new DbQuery())
                    ->select('COUNT(*)')
                    ->from('customer_message', 'cm')
                    ->leftJoin('customer_thread', 'ct', 'cm.`id_customer_thread` = ct.`id_customer_thread`')
                    ->where($where.Shop::addSqlRestriction())
            );
        }
    }

    /**
     * @return bool
     *
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
            if (!$this->deleteWithinTransaction($deletionPlan)) {
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

    /**
     * Delete this message inside a transaction owned by the caller.
     * Attachment files must be removed after that transaction commits.
     *
     * @param array{ids: int[], paths: string[]} $deletionPlan
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function deleteWithinTransaction(array &$deletionPlan)
    {
        $messagePlan = CustomerMessageAttachment::prepareDeletionByMessageIds([(int) $this->id]);
        if (!CustomerMessageAttachment::deletePreparedRecords($messagePlan) || !parent::delete()) {
            return false;
        }

        $deletionPlan['ids'] = array_merge($deletionPlan['ids'] ?? [], $messagePlan['ids']);
        $deletionPlan['paths'] = array_merge($deletionPlan['paths'] ?? [], $messagePlan['paths']);

        return true;
    }

}
