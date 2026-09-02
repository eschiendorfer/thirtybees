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
 * Class AdminCustomerThreadsControllerCore
 *
 * @property CustomerThread|null $object
 */
class AdminCustomerThreadsControllerCore extends AdminController
{
    /**
     * Settings controller
     */
    const SETTINGS_CONTROLLER = 'AdminCustomerServiceSettings';

    /**
     * Response templates controller
     */
    const RESPONSE_TEMPLATES_CONTROLLER = 'AdminOrderMessage';

    /** @var array<int, array<string, mixed>>|null */
    protected $assignableEmployeesCache;

    /**
     * AdminCustomerThreadsControllerCore constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->table = 'customer_thread';
        $this->className = 'CustomerThread';
        $this->lang = false;
        $this->list_id = 'customer_service_work_items';

        $statusArray = $this->getWorkItemStatuses();
        $employeeArray = [0 => $this->getUnassignedEmployeeLabel()];
        foreach ($this->getAssignableEmployees() as $employee) {
            $employeeArray[(int) $employee['id_employee']] = trim($employee['firstname'].' '.$employee['lastname']);
        }

        $this->fields_list = [
            'entity_type' => [
                'title'       => $this->l('Entity type'),
                'type'        => 'select',
                'list'        => $this->getWorkItemEntityTypes(),
                'filter_key'  => 'a!entity_type',
                'filter_type' => 'int',
                'callback'    => 'renderWorkItemEntity',
            ],
            'customer' => [
                'title'      => $this->l('Customer'),
                'filter_key' => 'a!customer',
                'callback'   => 'renderWorkItemCustomer',
            ],
            'normalized_status' => [
                'title'       => $this->l('Status'),
                'type'        => 'select',
                'list'        => $statusArray,
                'align'       => 'center',
                'filter_key'  => 'a!normalized_status',
                'callback'    => 'renderStatus',
            ],
            'id_employee_assigned' => [
                'title'       => $this->l('Assigned to'),
                'type'        => 'select',
                'list'        => $employeeArray,
                'filter_key'  => 'a!id_employee_assigned',
                'filter_type' => 'int',
                'callback'    => 'renderAssignedEmployee',
            ],
            'last_activity' => [
                'title'      => $this->l('Last activity'),
                'filter_key' => 'a!last_activity',
                'type'       => 'datetime',
            ],
        ];

        $this->bulk_actions = [];
        $this->shopLinkType = false;
        $this->list_no_link = true;
        $this->_orderBy = 'status_priority';
        $this->_orderWay = 'DESC';

        parent::__construct();

        $this->addRowAction('primary');
        $this->addRowAction('remove');
    }

    /**
     * @return void
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        parent::setMedia();

        if (Tools::getIntValue('id_customer_thread') > 0) {
            $this->addJS(_PS_MODULE_DIR_.'tb_framework/views/js/components/file_upload.js');
            $themeBaseUri = __PS_BASE_URI__.$this->admin_webpath.'/themes/'.$this->bo_theme;
            $this->addCSS($themeBaseUri.'/css/customer_thread_workspace.css');
            $this->addJS($themeBaseUri.'/js/customer_thread_workspace.js');
        } else {
            $this->addJS(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$this->bo_theme.'/js/customer_service_work_items.js');
        }
    }

    /**
     * Statuses available to employees and customers.
     *
     * pending1 remains the persisted value for "in progress" so existing threads
     * keep their meaning without a data migration.
     *
     * @return array<string, string>
     */
    protected function getCustomerServiceStatuses(): array
    {
        return [
            CustomerThread::STATUS_OPEN             => $this->l('Open'),
            CustomerThread::STATUS_IN_PROGRESS      => $this->l('In inquiry'),
            CustomerThread::STATUS_WAITING_CUSTOMER => $this->l('Waiting for customer'),
            CustomerThread::STATUS_CLOSED           => $this->l('Completed'),
        ];
    }

    /** @return array<string, string> */
    protected function getWorkItemStatuses(): array
    {
        return [
            CustomerServiceWorkItemProvider::STATUS_OPEN => $this->l('Open'),
            CustomerServiceWorkItemProvider::STATUS_INQUIRY => $this->l('In inquiry'),
            CustomerServiceWorkItemProvider::STATUS_WAITING_CUSTOMER => $this->l('Waiting for customer'),
            CustomerServiceWorkItemProvider::STATUS_CLOSED => $this->l('Completed'),
        ];
    }

    /** @return array<int, string> */
    protected function getWorkItemEntityTypes(): array
    {
        return [
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE => $this->l('Cancellation'),
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE => $this->l('Merchandise return'),
            (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE => $this->l('Service case'),
            (int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE => $this->l('Product wish'),
            (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE => $this->l('Message'),
        ];
    }

    /**
     * Employees available for customer service assignment.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getAssignableEmployees(): array
    {
        if ($this->assignableEmployeesCache === null) {
            $this->assignableEmployeesCache = array_values(Employee::getEmployees(true));
        }

        return $this->assignableEmployeesCache;
    }

    /**
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getAssignableEmployeeIds(): array
    {
        return array_map('intval', array_column($this->getAssignableEmployees(), 'id_employee'));
    }

    protected function getUnassignedEmployeeLabel(): string
    {
        return $this->l('Unassigned');
    }

    /**
     * @return array<int, string>
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getAssignableEmployeeOptions(): array
    {
        $options = [0 => $this->getUnassignedEmployeeLabel()];
        foreach ($this->getAssignableEmployees() as $employee) {
            $options[(int) $employee['id_employee']] = trim($employee['firstname'].' '.$employee['lastname']);
        }

        return $options;
    }

    /**
     * Render list
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderList()
    {
        // Check the new IMAP messages before rendering the list
        $this->renderProcessSyncImap();

        return parent::renderList();
    }

    /**
     * Read the shared queue from a UNION of its domain entities. This keeps the
     * domain status authoritative and avoids a duplicate work-item table.
     */
    public function getList(
        $idLang,
        $orderBy = null,
        $orderWay = null,
        $start = 0,
        $limit = null,
        $idLangShop = false
    ) {
        $this->ensureListIdDefinition();
        $limit = $limit === false
            ? 0
            : HelperList::resolvePagination($this->list_id, $this->context->cookie, $this->_pagination, $this->_default_pagination);
        $useLimit = $limit > 0;
        if ($useLimit && $limit !== $this->_default_pagination) {
            $this->context->cookie->{$this->list_id.'_pagination'} = $limit;
        } elseif (isset($this->context->cookie->{$this->list_id.'_pagination'})) {
            unset($this->context->cookie->{$this->list_id.'_pagination'});
        }

        $orderBy = $this->resolveOrderBy($orderBy);
        $orderWay = $this->resolveOrderWay($orderWay);
        $allowedOrderFields = ['entity_type', 'customer', 'normalized_status', 'id_employee_assigned', 'last_activity', 'status_priority'];
        if (!in_array($orderBy, $allowedOrderFields, true) || !Validate::isOrderWay($orderWay)) {
            $orderBy = 'status_priority';
            $orderWay = 'DESC';
        }
        $this->_orderBy = $orderBy;
        $this->_orderWay = strtoupper($orderWay);

        $start = 0;
        if ($useLimit && Tools::getIntValue('submitFilter'.$this->list_id)) {
            $start = (Tools::getIntValue('submitFilter'.$this->list_id) - 1) * $limit;
        }

        $shopIds = Shop::getContextListShopID();
        if (!$shopIds) {
            $shopIds = [(int)$this->context->shop->id];
        }
        $baseSql = (new CustomerServiceWorkItemProvider())->getBaseSql($shopIds);
        $where = ' WHERE 1 '.($this->_filter ?? '');
        $orderSql = $orderBy === 'status_priority'
            ? 'a.`status_priority` DESC, a.`last_activity` DESC'
            : 'a.`'.bqSQL($orderBy).'` '.pSQL($orderWay).', a.`last_activity` DESC';
        $limitSql = $useLimit ? ' LIMIT '.(int)$start.', '.(int)$limit : '';
        $this->_listsql = 'SELECT a.* FROM ('.$baseSql.') a'.$where.' ORDER BY '.$orderSql.$limitSql;
        $countSql = 'SELECT COUNT(*) FROM ('.$baseSql.') a'.$where;

        try {
            $this->_list = Db::readOnly()->getArray($this->_listsql);
            $this->_listTotal = (int)Db::readOnly()->getValue($countSql);
            foreach ($this->_list as &$workItem) {
                $workItem['email'] = Tools::convertEmailFromIdn((string)($workItem['email'] ?? ''));
                $this->addWorkItemActions($workItem);
            }
            unset($workItem);
        } catch (PrestaShopDatabaseException $exception) {
            PrestaShopLogger::addLog($exception->getMessage(), 3);
            $this->_list = [];
            $this->_listTotal = 0;
            $this->_list_error = Tools::displayError('The customer-service work queue could not be loaded.');
        }
    }

    /**
     * Call the IMAP synchronization during the render process.
     * @throws PrestaShopException
     */
    public function renderProcessSyncImap()
    {
        // To avoid an error if the IMAP isn't configured, we check the configuration here, like during
        // the synchronization. All parameters will exists.
        if (!(Configuration::get('PS_SAV_IMAP_URL')
            || Configuration::get('PS_SAV_IMAP_PORT')
            || Configuration::get('PS_SAV_IMAP_USER')
            || Configuration::get('PS_SAV_IMAP_PWD'))
        ) {
            return;
        }

        // Executes the IMAP synchronization.
        $syncErrors = $this->syncImap();

        // Show the errors.
        if (isset($syncErrors['hasError']) && $syncErrors['hasError']) {
            if (isset($syncErrors['errors'])) {
                foreach ($syncErrors['errors'] as &$error) {
                    $this->displayWarning($error);
                }
            }
        }
    }

    /**
     * Imap synchronization method.
     *
     * @return array Errors list.
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function syncImap()
    {
        if (!($url = Configuration::get('PS_SAV_IMAP_URL'))
            || !($port = Configuration::get('PS_SAV_IMAP_PORT'))
            || !($user = Configuration::get('PS_SAV_IMAP_USER'))
            || !($password = Configuration::get('PS_SAV_IMAP_PWD'))
        ) {
            return ['hasError' => true, 'errors' => ['IMAP configuration is not correct']];
        }

        $conf = Configuration::getMultiple(
            [
                'PS_SAV_IMAP_OPT_NORSH',
                'PS_SAV_IMAP_OPT_SSL',
                'PS_SAV_IMAP_OPT_VALIDATE-CERT',
                'PS_SAV_IMAP_OPT_NOVALIDATE-CERT',
                'PS_SAV_IMAP_OPT_TLS',
                'PS_SAV_IMAP_OPT_NOTLS',
            ]
        );

        $confStr = '';
        if ($conf['PS_SAV_IMAP_OPT_NORSH']) {
            $confStr .= '/norsh';
        }
        if ($conf['PS_SAV_IMAP_OPT_SSL']) {
            $confStr .= '/ssl';
        }
        if ($conf['PS_SAV_IMAP_OPT_VALIDATE-CERT']) {
            $confStr .= '/validate-cert';
        }
        if ($conf['PS_SAV_IMAP_OPT_NOVALIDATE-CERT']) {
            $confStr .= '/novalidate-cert';
        }
        if ($conf['PS_SAV_IMAP_OPT_TLS']) {
            $confStr .= '/tls';
        }
        if ($conf['PS_SAV_IMAP_OPT_NOTLS']) {
            $confStr .= '/notls';
        }

        if (!function_exists('imap_open')) {
            return ['hasError' => true, 'errors' => ['imap is not installed on this server']];
        }

        $mbox = @imap_open('{'.$url.':'.$port.$confStr.'}', $user, $password);

        //checks if there is no error when connecting imap server
        $errors = imap_errors();
        $strErrors = $errors ? implode(',', array_unique($errors)) : '';
        $strErrorDelete = '';

        //checks if imap connexion is active
        if (!$mbox) {
            return ['hasError' => true, 'errors' => ['Cannot connect to the mailbox :<br />'.($strErrors)]];
        }

        //Returns information about the current mailbox. Returns FALSE on failure.
        $check = imap_check($mbox);
        if (!$check) {
            return ['hasError' => true, 'errors' => ['Fail to get information about the current mailbox']];
        }

        if ($check->Nmsgs == 0) {
            return ['hasError' => true, 'errors' => ['NO message to sync']];
        }

        $result = imap_fetch_overview($mbox, "1:{$check->Nmsgs}", 0);
        foreach ($result as $overview) {
            //check if message exist in database
            $subject = $overview->subject ?? '';
            //Creating an md5 to check if message has been allready processed
            $md5 = md5($overview->date.$overview->from.$subject.$overview->msgno);
            $exist = Db::readOnly()->getValue(
                (new DbQuery())
                    ->select('`md5_header`')
                    ->from('customer_message_sync_imap')
                    ->where('`md5_header` = \''.pSQL($md5).'\'')
            );
            if ($exist) {
                if (Configuration::get('PS_SAV_IMAP_DELETE_MSG')) {
                    if (!imap_delete($mbox, $overview->msgno)) {
                        $strErrorDelete = ', Fail to delete message';
                    }
                }
            } else {
                //check if subject has id_order
                preg_match('/\#ct([0-9]*)/', $subject, $matches1);
                preg_match('/\#tc([0-9-a-z-A-Z]*)/', $subject, $matches2);
                $matchFound = false;
                if (isset($matches1[1]) && isset($matches2[1])) {
                    $matchFound = true;
                }

                $newCt = (Configuration::get('PS_SAV_IMAP_CREATE_THREADS') && !$matchFound && (strpos($subject, '[no_sync]') == false));

                if ($matchFound || $newCt) {
                    if ($newCt) {
                        if (!preg_match('/<('.Tools::cleanNonUnicodeSupport('[a-z\p{L}0-9!#$%&\'*+\/=?^`{}|~_-]+[.a-z\p{L}0-9!#$%&\'*+\/=?^`{}|~_-]*@[a-z\p{L}0-9]+[._a-z\p{L}0-9-]*\.[a-z0-9]+').')>/', $overview->from, $result)
                            || !Validate::isEmail($from = Tools::convertEmailToIdn($result[1]))
                        ) {
                            continue;
                        }

                        $customer = new Customer();
                        $client = $customer->getByEmail($from); //check if we already have a customer with this email
                        $newThreadCustomerId = (int)($client->id ?? 0);
                        $ct = null;
                    } else {
                        $ct = new CustomerThread((int) $matches1[1]);
                    } //check if order exist in database

                    if ($newCt || (
                        Validate::isLoadedObject($ct)
                        && isset($matches2[1])
                        && hash_equals((string)$ct->token, (string)$matches2[1])
                    )) {
                        $message = imap_fetchbody($mbox, $overview->msgno, 1);
                        if (base64_encode(base64_decode($message)) === $message) {
                            $message = base64_decode($message);
                        }
                        $message = quoted_printable_decode($message);
                        $message = mb_convert_encoding($message, 'UTF-8', mb_list_encodings());
                        $message = quoted_printable_decode($message);
                        $message = nl2br($message);
                        $message = mb_substr($message, 0, (int) CustomerMessage::$definition['fields']['message']['size']);

                        if (empty($message) || !Validate::isCleanHtml($message)) {
                            $strErrors .= Tools::displayError(sprintf('Invalid Message Content for subject: %1s', $subject));
                        } else {
                            $request = [
                                'idCustomer' => $newCt ? $newThreadCustomerId : (int)$ct->id_customer,
                                'idShop' => $newCt ? (int)$this->context->shop->id : (int)$ct->id_shop,
                                'idLang' => $newCt ? (int)Configuration::get('PS_LANG_DEFAULT') : (int)$ct->id_lang,
                                'idCustomerThread' => $newCt ? 0 : (int)$ct->id,
                                'email' => $newCt ? $from : (string)$ct->email,
                                'token' => $newCt ? '' : (string)($matches2[1] ?? ''),
                                'message' => $message,
                            ];
                            try {
                                (new CustomerServiceMessageService())->save($request);
                            } catch (PrestaShopException $exception) {
                                PrestaShopLogger::addLog($exception->getMessage(), 3);
                                $strErrors .= Tools::displayError(sprintf('Message could not be imported for subject: %1s', $subject));
                            }
                        }
                    }
                }
                Db::getInstance()->insert(
                    'customer_message_sync_imap',
                    [
                        'md5_header' => pSQL($md5),
                    ]
                );
            }
        }
        imap_expunge($mbox);
        imap_close($mbox);
        if ($strErrors.$strErrorDelete) {
            return ['hasError' => true, 'errors' => [$strErrors.$strErrorDelete]];
        } else {
            return ['hasError' => false, 'errors' => ''];
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);

        $responseTemplatesTabId = Tab::getIdFromClassName(static::RESPONSE_TEMPLATES_CONTROLLER);
        if ($this->context->employee->hasAccess($responseTemplatesTabId, Profile::PERMISSION_VIEW)) {
            $this->page_header_toolbar_btn['response_templates'] = [
                'href' => $this->context->link->getAdminLink(static::RESPONSE_TEMPLATES_CONTROLLER),
                'icon' => 'process-icon-edit',
                'desc' => $this->l('Response Templates'),
            ];
        }

        $settingsTabId = Tab::getIdFromClassName(static::SETTINGS_CONTROLLER);
        if ($this->context->employee->hasAccess($settingsTabId, Profile::PERMISSION_EDIT)) {
            $this->page_header_toolbar_btn['settings'] = [
                'href' => $this->context->link->getAdminLink(static::SETTINGS_CONTROLLER),
                'icon' => 'process-icon-cogs',
                'desc' => $this->l('Settings'),
            ];
        }
    }

    /**
     * @return void
     */
    public function initPageHeaderToolbar()
    {
        if (Tools::getIntValue('id_customer_thread') > 0) {
            $thread = $this->loadObject();
            if (Validate::isLoadedObject($thread)) {
                $context = (new CustomerThreadContextProvider($this->context))->getForThread($thread);
                $this->page_header_toolbar_title = $this->getThreadPageTitle($thread, $context);
            }
        }

        parent::initPageHeaderToolbar();
        $this->context->smarty->clearAssign('help_link');
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitEntityThreadReply')) {
            $this->processEntityReply();
            return false;
        }

        if ($idCustomerThread = Tools::getIntValue('id_customer_thread')) {
            if (Tools::isSubmit('submitReply')) {
                $ct = new CustomerThread($idCustomerThread);
                $replyStatus = (string) Tools::getValue('thread_status', CustomerThread::STATUS_CLOSED);
                $idEmployee = (int) $this->context->employee->id;
                $pendingAttachments = $this->prepareReplyAttachments($idEmployee);
                if (!array_key_exists($replyStatus, $this->getCustomerServiceStatuses())) {
                    $this->errors[] = Tools::displayError('The selected status is invalid.');
                }
                $replyMessage = (string)Tools::getValue('reply_message');
                $hasReplyContent = CustomerMessage::hasVisibleContent(CustomerMessage::sanitizeContent($replyMessage))
                    || !empty($pendingAttachments);
                if (!$this->errors && !$hasReplyContent) {
                    $ct->status = $replyStatus;
                    if (!$ct->update()) {
                        $this->errors[] = Tools::displayError('The thread status could not be updated.');
                    } else {
                        Tools::redirectAdmin($this->getReplyReturnUrl($ct));
                    }
                }
                if (!$this->errors && $hasReplyContent) {
                    try {
                        $replyResult = (new CustomerServiceReplyService())->send([
                            'idCustomer' => (int)$ct->id_customer,
                            'idEmployee' => $idEmployee,
                            'idShop' => (int)$ct->id_shop,
                            'idLang' => (int)$ct->id_lang,
                            'idCustomerThread' => (int)$ct->id,
                            'email' => (string)$ct->email,
                            'message' => $replyMessage,
                            'status' => $replyStatus,
                            'attachments' => $pendingAttachments,
                        ]);
                        $ct = $replyResult['thread'];
                    } catch (PrestaShopException $exception) {
                        PrestaShopLogger::addLog($exception->getMessage(), 3);
                        $this->errors[] = Tools::displayError('The customer message could not be sent.');
                    }
                }

                if (!$this->errors && $hasReplyContent) {
                    $_POST['reply_message'] = '';
                    $this->setSubmittedAttachmentIds([]);
                    if (empty($replyResult['email_sent'])) {
                        $this->errors[] = Tools::displayError('The message was saved, but the email could not be sent to the customer.');
                    }

                    if (in_array($idEmployee, $this->getAssignableEmployeeIds(), true)) {
                        [$assignmentEntityType, $assignmentEntityId] = $this->getThreadAssignmentTarget($ct);
                        if (!EntityEmployeeAssignment::assign($assignmentEntityType, $assignmentEntityId, $idEmployee)) {
                            $this->errors[] = Tools::displayError('The reply was saved, but the assignment could not be updated.');
                        }
                    }
                    if (!$this->errors) {
                        Tools::redirectAdmin($this->getReplyReturnUrl($ct));
                    }
                }
            }
        }

        return parent::postProcess();
    }

    /**
     * Initialize content
     *
     * @return void
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function initContent()
    {
        if ($attachmentId = Tools::getIntValue('showMessageAttachment')) {
            $this->openUploadedFile(
                $attachmentId,
                Tools::getIntValue('thumbnail') === 1,
                Tools::getIntValue('inline') === 1
            );
        }

        parent::initContent();
    }

    /**
     * Update one customer thread setting from the detail view.
     *
     * @return void
     * @throws PrestaShopException
     */
    public function ajaxProcessUpdateThreadSetting()
    {
        if (!$this->hasEditPermission()) {
            $this->respondThreadSettingJson(false, $this->l('You do not have permission to edit this thread.'));
        }

        $thread = new CustomerThread(Tools::getIntValue('id_customer_thread'));
        if (!Validate::isLoadedObject($thread)) {
            $this->respondThreadSettingJson(false, $this->l('The thread could not be found.'));
        }

        $field = (string) Tools::getValue('field');
        $value = (string) Tools::getValue('value');
        if ($field === 'id_employee_assigned') {
            $idEmployee = (int) $value;
            if ($idEmployee !== 0 && !in_array($idEmployee, $this->getAssignableEmployeeIds(), true)) {
                $this->respondThreadSettingJson(false, $this->l('The selected employee cannot be assigned.'));
            }
            [$assignmentEntityType, $assignmentEntityId] = $this->getThreadAssignmentTarget($thread);
            $updated = EntityEmployeeAssignment::assign($assignmentEntityType, $assignmentEntityId, $idEmployee);
            $this->respondThreadSettingJson(
                $updated,
                $updated ? $this->l('The assignment has been updated.') : $this->l('The assignment could not be updated.')
            );
        } else {
            $this->respondThreadSettingJson(false, $this->l('The selected setting is invalid.'));
        }
    }

    /**
     * Update an assignment directly from the shared work queue.
     */
    public function ajaxProcessUpdateEntityAssignment()
    {
        if (!$this->hasEditPermission()) {
            $this->respondThreadSettingJson(false, $this->l('You do not have permission to change this assignment.'));
        }

        $entityType = Tools::getIntValue('entity_type');
        $idEntity = Tools::getIntValue('id_entity');
        $idEmployee = Tools::getIntValue('id_employee');
        $provider = new CustomerServiceWorkItemProvider();
        if (!$provider->entityExists($entityType, $idEntity)) {
            $this->respondThreadSettingJson(false, $this->l('The work item could not be found.'));
        }
        if ($idEmployee !== 0 && !in_array($idEmployee, $this->getAssignableEmployeeIds(), true)) {
            $this->respondThreadSettingJson(false, $this->l('The selected employee cannot be assigned.'));
        }

        $updated = EntityEmployeeAssignment::assign($entityType, $idEntity, $idEmployee);
        $this->respondThreadSettingJson(
            $updated,
            $updated ? $this->l('The assignment has been updated.') : $this->l('The assignment could not be updated.')
        );
    }

    /**
     * Upload a pending attachment for the current employee.
     *
     * @return void
     * @throws PrestaShopException
     */
    public function ajaxProcessUploadRteFile()
    {
        if (!$this->hasEditPermission()) {
            $this->respondAttachmentJson(false, [], $this->l('You do not have permission to upload files.'));
        }

        $files = CustomerMessageAttachment::getUploadedFiles('file');
        if (count($files) !== 1) {
            $this->respondAttachmentJson(false, [], $this->l('No file was uploaded.'));
        }

        $idEmployee = (int) $this->context->employee->id;
        $existingAttachments = CustomerMessageAttachment::getOwnedPending(
            Tools::getArrayValue('existing_attachment_ids', []),
            0,
            0,
            $idEmployee
        );
        if ($existingAttachments === false) {
            $this->respondAttachmentJson(false, [], $this->l('The selected attachment is invalid.'));
        }

        $errors = CustomerMessageAttachment::validateUploadedFiles(
            $files,
            true,
            count($existingAttachments),
            CustomerMessageAttachment::getTotalUploadSize($existingAttachments)
        );
        if ($errors) {
            $this->respondAttachmentJson(false, [], implode(' ', array_unique($errors)));
        }

        try {
            $attachments = CustomerMessageAttachment::storeUploadedFiles($files, 0, 0, 0, $idEmployee);
        } catch (Exception $exception) {
            $this->respondAttachmentJson(false, [], $this->l('The file could not be uploaded.'));
        }

        $attachment = reset($attachments);
        if (!$attachment instanceof CustomerMessageAttachment) {
            $this->respondAttachmentJson(false, [], $this->l('The file could not be uploaded.'));
        }
        if (!CustomerMessageAttachment::canAttachToEmail(array_merge($existingAttachments, [$attachment]))) {
            $attachment->delete();
            $this->respondAttachmentJson(false, [], sprintf(
                $this->l('The attachments exceed the maximum email size of %d MB.'),
                CustomerMessageAttachment::getMaximumEmailTotalSizeMb()
            ));
        }

        $this->respondAttachmentJson(true, $this->getAttachmentPayload($attachment));
    }

    /**
     * Delete a pending attachment owned by the current employee.
     *
     * @return void
     * @throws PrestaShopException
     */
    public function ajaxProcessDeleteRteFile()
    {
        if (!$this->hasEditPermission()) {
            $this->respondAttachmentJson(false, [], $this->l('You do not have permission to remove files.'));
        }

        $attachments = CustomerMessageAttachment::getOwnedPending(
            [Tools::getIntValue('id_attachment')],
            0,
            0,
            (int) $this->context->employee->id
        );
        $attachment = is_array($attachments) && count($attachments) === 1 ? reset($attachments) : false;
        if (!$attachment instanceof CustomerMessageAttachment || !$attachment->delete()) {
            $this->respondAttachmentJson(false, [], $this->l('The attachment could not be removed.'));
        }

        $this->respondAttachmentJson(true);
    }

    /**
     * @return HelperKpi[]
     *
     * @throws PrestaShopException
     */
    public function getKpis(): array
    {
        $counts = $this->getWorkItemCounts();
        $kpis = [];

        $definitions = [
            ['open', 'icon-inbox', 'color1', $this->l('Open'), [
                'normalized_status' => CustomerServiceWorkItemProvider::STATUS_OPEN,
            ]],
            ['inquiry', 'icon-search', 'color2', $this->l('In inquiry'), [
                'normalized_status' => CustomerServiceWorkItemProvider::STATUS_INQUIRY,
            ]],
            ['waiting-customer', 'icon-user', 'color3', $this->l('Waiting for customer'), [
                'normalized_status' => CustomerServiceWorkItemProvider::STATUS_WAITING_CUSTOMER,
            ]],
            ['unassigned', 'icon-user', 'color4', $this->l('Unassigned'), [
                'normalized_status' => CustomerServiceWorkItemProvider::STATUS_OPEN,
                'id_employee_assigned' => '0',
            ]],
        ];
        foreach ($definitions as [$id, $icon, $color, $title, $filters]) {
            $helper = new HelperKpi();
            $helper->id = 'box-'.$id;
            $helper->icon = $icon;
            $helper->color = $color;
            $helper->title = $title;
            $helper->value = (int)($counts[$id] ?? 0);
            $params = ['submitFilterForced' => 1];
            foreach ($filters as $filterField => $filterValue) {
                $params['list_idFilter_a!'.$filterField] = $filterValue;
            }
            $helper->href = $this->context->link->getAdminLink('AdminCustomerThreads', true, $params);
            $helper->refresh = false;
            $kpis[] = $helper;
        }

        return $kpis;
    }

    /** @return array<string, int> */
    private function getWorkItemCounts(): array
    {
        $shopIds = Shop::getContextListShopID();
        if (!$shopIds) {
            $shopIds = [(int)$this->context->shop->id];
        }
        $baseSql = (new CustomerServiceWorkItemProvider())->getBaseSql($shopIds);
        $row = Db::readOnly()->getRow(
            'SELECT'
            .' SUM(a.`normalized_status` = "'.pSQL(CustomerServiceWorkItemProvider::STATUS_OPEN).'") AS `open`,'
            .' SUM(a.`normalized_status` = "'.pSQL(CustomerServiceWorkItemProvider::STATUS_INQUIRY).'") AS `inquiry`,'
            .' SUM(a.`normalized_status` = "'.pSQL(CustomerServiceWorkItemProvider::STATUS_WAITING_CUSTOMER).'") AS `waiting-customer`,'
            .' SUM(a.`normalized_status` = "'.pSQL(CustomerServiceWorkItemProvider::STATUS_OPEN).'"'
            .' AND a.`id_employee_assigned` = 0) AS `unassigned`'
            .' FROM ('.$baseSql.') a'
        );

        return is_array($row) ? array_map('intval', $row) : [];
    }

    /**
     * Render view
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderView()
    {
        // Keep old bookmarks working, but never render a second workspace for a domain entity.
        if (Tools::getIntValue('start_entity_thread') === 1) {
            $this->redirectEntityView(
                Tools::getIntValue('entity_type'),
                Tools::getIntValue('id_entity')
            );

            $this->errors[] = Tools::displayError('The customer-service entity could not be loaded.');
            return '';
        }

        if (!$idCustomerThread = Tools::getIntValue('id_customer_thread')) {
            return '';
        }

        /** @var CustomerThread $thread */
        $thread = $this->loadObject();
        if (! Validate::isLoadedObject($thread)) {
            return '';
        }
        if (!Tools::isSubmit('submitReply')) {
            $this->redirectEntityView((int)$thread->entity_type, (int)$thread->id_entity);
        }
        $workspaceProvider = new CustomerThreadWorkspaceDataProvider($this->context);
        $conversation = $workspaceProvider->getConversation($thread);

        $customer = null;
        if ($thread->id_customer) {
            $customer = new Customer($thread->id_customer);
        }
        $threadContext = (new CustomerThreadContextProvider($this->context))->getForThread($thread);
        $idOrder = (int)($threadContext['related_order_id'] ?? 0);
        $order = $idOrder > 0 ? new Order($idOrder) : null;
        if (! Validate::isLoadedObject($order)) {
            $order = null;
        }

        [$assignmentEntityType, $assignmentEntityId] = $this->getThreadAssignmentTarget($thread);
        $this->tpl_view_vars = [
            'thread'                        => $thread,
            'assignable_employees'          => $this->getAssignableEmployeeOptions(),
            'assigned_employee_id'          => EntityEmployeeAssignment::getEmployeeId($assignmentEntityType, $assignmentEntityId),
            'messages'                      => $conversation['messages'],
            'first_message'                 => $conversation['first_message'],
            'thread_entity_context'         => $threadContext,
            'customer_overview'             => $this->getCustomerOverview(
                $customer,
                (string)$thread->email,
                (int)$thread->id
            ),
            'message_form'                  => $workspaceProvider->getMessageFormData(
                (int)$thread->id_lang,
                $order,
                $customer,
                $this->context->link->getAdminLink('AdminCustomerThreads', true, [
                    'id_customer_thread' => (int)$thread->id,
                    'viewcustomer_thread' => 1,
                ]),
                'submitReply',
                [
                    'id_customer_thread' => (int)$thread->id,
                ],
                $this->getBackOfficeUploadData($this->getPendingEmployeeAttachments()),
                (string)$thread->status
            ),
        ];

        return parent::renderView();
    }

    private function processEntityReply(): void
    {
        if (!$this->hasEditPermission()) {
            $this->errors[] = Tools::displayError('You do not have permission to contact this customer.');
            return;
        }

        $target = $this->resolveEntityReplyTarget();
        if (!$target) {
            $this->errors[] = Tools::displayError('The customer-service entity could not be loaded.');
            return;
        }

        $idEmployee = (int)$this->context->employee->id;
        $pendingAttachments = $this->prepareReplyAttachments($idEmployee);
        $message = (string)Tools::getValue('reply_message');
        $replyStatus = (string)Tools::getValue('thread_status', CustomerThread::STATUS_CLOSED);
        if (!array_key_exists($replyStatus, $this->getCustomerServiceStatuses())) {
            $this->errors[] = Tools::displayError('The selected status is invalid.');
        }
        if (!$this->errors && !CustomerMessage::hasVisibleContent(CustomerMessage::sanitizeContent($message)) && !$pendingAttachments) {
            $this->errors[] = Tools::displayError('The message cannot be blank.');
        }
        if ($this->errors) {
            return;
        }

        try {
            $result = (new CustomerServiceReplyService())->send([
                'idCustomer' => (int)$target['customer']->id,
                'idEmployee' => $idEmployee,
                'idShop' => (int)$target['id_shop'],
                'idLang' => (int)$target['id_lang'],
                'entityType' => (int)$target['entity_type'],
                'idEntity' => (int)$target['id_entity'],
                'email' => (string)$target['customer']->email,
                'message' => $message,
                'status' => $replyStatus,
                'attachments' => $pendingAttachments,
            ]);
        } catch (PrestaShopException $exception) {
            PrestaShopLogger::addLog($exception->getMessage(), 3);
            $this->errors[] = Tools::displayError('The customer message could not be sent.');
            return;
        }

        EntityEmployeeAssignment::assign((int)$target['entity_type'], (int)$target['id_entity'], $idEmployee);
        if (empty($result['email_sent'])) {
            $this->warnings[] = Tools::displayError('The message was saved, but the email could not be sent to the customer.');
        }
        $this->setSubmittedAttachmentIds([]);
        Tools::redirectAdmin($this->getReplyReturnUrl($result['thread']));
    }

    /** @return array<string, mixed>|null */
    private function resolveEntityReplyTarget(): ?array
    {
        $entityType = Tools::getIntValue('entity_type');
        $idEntity = Tools::getIntValue('id_entity');
        if (!in_array($entityType, [
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE,
            (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE,
            (int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE,
        ], true) || $idEntity <= 0) {
            return null;
        }

        $entityTypeEnum = \CoreExtension\EntityTypeEnum::tryFrom($entityType);
        $className = $entityTypeEnum ? $entityTypeEnum->getObjectModelClassName() : null;
        if (!$className || !class_exists($className)) {
            return null;
        }
        $entity = new $className($idEntity);
        if (!Validate::isLoadedObject($entity)) {
            return null;
        }

        $order = null;
        $idCustomer = property_exists($entity, 'id_customer') ? (int)$entity->id_customer : 0;
        if (property_exists($entity, 'id_order') && (int)$entity->id_order > 0) {
            $order = new Order((int)$entity->id_order);
            if (!Validate::isLoadedObject($order)) {
                return null;
            }
            $idCustomer = (int)$order->id_customer;
        }
        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            return null;
        }

        $idShop = $order && Validate::isLoadedObject($order) ? (int)$order->id_shop : (int)$customer->id_shop;
        $idLang = (int)$customer->id_lang > 0 ? (int)$customer->id_lang : (int)$this->context->language->id;

        return [
            'entity_type' => $entityType,
            'id_entity' => $idEntity,
            'entity' => $entity,
            'customer' => $customer,
            'order' => $order && Validate::isLoadedObject($order) ? $order : null,
            'id_shop' => $idShop > 0 ? $idShop : (int)$this->context->shop->id,
            'id_lang' => $idLang,
        ];
    }

    /**
     * @param CustomerThread $thread
     * @param array<string, mixed>|null $context
     */
    protected function getThreadPageTitle(CustomerThread $thread, ?array $context): string
    {
        $customer = (int) $thread->id_customer > 0 ? new Customer((int) $thread->id_customer) : null;
        $customerName = Validate::isLoadedObject($customer)
            ? trim($customer->firstname.' '.$customer->lastname)
            : (string) $thread->email;

        if ($context) {
            $title = trim((string) $context['title'].' '.(string) ($context['reference'] ?? ''));
            if (!empty($context['related_order_reference'])) {
                $title .= ' – '.(string) ($context['related_order_label'] ?? 'Order').' '.(string) $context['related_order_reference'];
            }

            return $customerName.' – '.$title;
        }

        return $customerName.' – '.$this->l('General request').' #'.(int) $thread->id;
    }

    /**
     * @return array<string, mixed>
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getCustomerOverview(
        ?Customer $customer,
        string $fallbackEmail = '',
        int $excludedThreadId = 0
    ): array
    {
        $overview = [
            'name'           => Validate::isLoadedObject($customer) ? $customer->firstname.' '.$customer->lastname : '',
            'email'          => Validate::isLoadedObject($customer) ? $customer->email : $fallbackEmail,
            'url'            => Validate::isLoadedObject($customer)
                ? $this->context->link->getAdminLink('AdminCustomers').'&id_customer='.(int) $customer->id.'&viewcustomer'
                : '',
            'note_html'      => Validate::isLoadedObject($customer) && trim((string) $customer->note) !== ''
                ? CustomerMessage::renderContent((string) $customer->note)
                : '',
            'recent_threads' => [],
            'recent_orders'  => [],
        ];

        if (!Validate::isLoadedObject($customer)) {
            return $overview;
        }

        $recentThreads = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_customer_thread`')
                ->from('customer_thread')
                ->where('`id_customer` = '.(int) $customer->id)
                ->where('`id_customer_thread` != '.(int)$excludedThreadId)
                ->orderBy('`date_upd` DESC')
                ->limit(3)
        );
        $statuses = $this->getCustomerServiceStatuses();
        $provider = new CustomerThreadContextProvider($this->context);

        foreach ($recentThreads as $row) {
            $recentThread = new CustomerThread((int) $row['id_customer_thread']);
            if (!Validate::isLoadedObject($recentThread)) {
                continue;
            }
            /*
             * Todo: Resolve only the lightweight title and reference for recent threads
             * instead of loading the complete customer-thread context.
             */
            $recentContext = $provider->getForThread($recentThread);
            $recentStatus = $recentThread->status;
            $overview['recent_threads'][] = [
                'title'  => $recentContext
                    ? trim((string) $recentContext['title'].' '.(string) ($recentContext['reference'] ?? ''))
                    : $this->l('Customer service').' #'.(int) $recentThread->id,
                'status' => $statuses[$recentStatus] ?? $recentStatus,
                'date'   => $recentThread->date_upd,
                'url'    => $this->context->link->getAdminLink('AdminCustomerThreads').'&id_customer_thread='.(int) $recentThread->id.'&viewcustomer_thread',
            ];
        }

        $recentOrders = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('o.`id_order`, o.`reference`, o.`date_add`, osl.`name` AS `status`')
                ->from('orders', 'o')
                ->leftJoin(
                    'order_state_lang',
                    'osl',
                    'osl.`id_order_state` = o.`current_state` AND osl.`id_lang` = '.(int) $this->context->language->id
                )
                ->where('o.`id_customer` = '.(int) $customer->id)
                ->orderBy('o.`date_add` DESC')
                ->limit(3)
        );

        foreach ($recentOrders as $row) {
            $overview['recent_orders'][] = [
                'title'  => $this->l('Order').' '.(string) $row['reference'],
                'status' => (string) $row['status'],
                'date'   => (string) $row['date_add'],
                'url'    => $this->context->link->getAdminLink('AdminOrders').'&id_order='.(int) $row['id_order'].'&vieworder',
            ];
        }

        return $overview;
    }


    /**
     * @param int $attachmentId
     * @return void
     * @throws PrestaShopException
     */
    protected function openUploadedFile(int $attachmentId, bool $thumbnail = false, bool $inline = false)
    {
        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }

        $attachment = new CustomerMessageAttachment($attachmentId);
        if (!Validate::isLoadedObject($attachment)) {
            die('Attachment not found');
        }
        if (!$attachment->fileExists() || ($thumbnail && !$attachment->thumbnailExists())) {
            die('File not found');
        }

        $path = $thumbnail ? $attachment->getThumbnailFilePath() : $attachment->getFilePath();
        header('Content-Type: ' . ($thumbnail ? 'image/webp' : $attachment->mime_type));
        header('Content-Length: ' . (int) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $attachment->getFullFileName() . '"');
        readfile($path);
        die;
    }

    /**
     * @return CustomerMessageAttachment[]
     */
    protected function getPendingEmployeeAttachments()
    {
        $attachments = CustomerMessageAttachment::getOwnedPending(
            $this->getSubmittedAttachmentIds(),
            0,
            0,
            (int) $this->context->employee->id
        );

        return $attachments === false ? [] : $attachments;
    }

    /** @return CustomerMessageAttachment[] */
    private function prepareReplyAttachments(int $idEmployee): array
    {
        $attachments = CustomerMessageAttachment::getOwnedPending(
            $this->getSubmittedAttachmentIds(),
            0,
            0,
            $idEmployee
        );
        if ($attachments === false) {
            $attachments = [];
            $this->errors[] = Tools::displayError('The selected attachment is invalid.');
        }

        $uploadedFiles = CustomerMessageAttachment::getUploadedFiles('file_attachment');
        $this->errors = array_merge(
            $this->errors,
            CustomerMessageAttachment::validateUploadedFiles(
                $uploadedFiles,
                true,
                count($attachments),
                CustomerMessageAttachment::getTotalUploadSize($attachments)
            )
        );
        if (!$this->errors && $uploadedFiles) {
            try {
                $attachments = array_merge(
                    $attachments,
                    CustomerMessageAttachment::storeUploadedFiles($uploadedFiles, 0, 0, 0, $idEmployee)
                );
            } catch (Exception $exception) {
                $this->errors[] = Tools::displayError('An error occurred during the file upload process.');
            }
        }
        if (!$this->errors && !CustomerMessageAttachment::canAttachToEmail($attachments)) {
            $this->errors[] = sprintf(
                Tools::displayError('The attachments exceed the maximum email size of %d MB.'),
                CustomerMessageAttachment::getMaximumEmailTotalSizeMb()
            );
        }

        $this->setSubmittedAttachmentIds(CustomerMessageAttachment::getIds($attachments));

        return $attachments;
    }

    protected function getSubmittedAttachmentIds(): array
    {
        $payload = json_decode((string) Tools::getValue('customer_message_attachments'), true);
        if (is_array($payload) && !empty($payload['attachments']) && is_array($payload['attachments'])) {
            $ids = [];
            foreach ($payload['attachments'] as $attachment) {
                $idAttachment = (int) ($attachment['id_attachment'] ?? 0);
                if ($idAttachment > 0) {
                    $ids[$idAttachment] = $idAttachment;
                }
            }

            return array_values($ids);
        }

        return array_values(array_filter(array_map('intval', Tools::getArrayValue('customer_message_attachment_ids', []))));
    }

    /**
     * @param int[] $attachmentIds
     */
    protected function setSubmittedAttachmentIds(array $attachmentIds): void
    {
        $_POST['customer_message_attachment_ids'] = $attachmentIds;
        $_POST['customer_message_attachments'] = '';
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     *
     * @return array<string, mixed>
     */
    protected function getBackOfficeUploadData(array $attachments): array
    {
        $maximumCount = CustomerMessageAttachment::getMaximumAttachmentCount();
        $maximumEmailSizeMb = CustomerMessageAttachment::getMaximumEmailTotalSizeMb();

        return (new CustomerThreadWorkspaceDataProvider($this->context))->getUploadData(
            $attachments,
            sprintf($this->l('Up to %1$d files and %2$d MB in total.'), $maximumCount, $maximumEmailSizeMb)
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAttachmentPayload(CustomerMessageAttachment $attachment): array
    {
        return (new CustomerThreadWorkspaceDataProvider($this->context))->getAttachmentPayload($attachment);
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function respondAttachmentJson(bool $success, array $data = [], string $error = '')
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ajaxDie(json_encode(array_merge([
            'success' => $success,
            'error'   => $error,
        ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return void
     */
    protected function respondThreadSettingJson(bool $success, string $message)
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ajaxDie(json_encode([
            'success' => $success,
            'text'    => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param string $value
     * @return string
     */
    public function renderStatus($value)
    {
        $threadStatusLabels = $this->getCustomerServiceStatuses();
        $workItemStatusLabels = $this->getWorkItemStatuses();
        $statuses = [
            'unknown'          => ['class' => 'badge', 'text' => $this->l('Unknown')],
            CustomerServiceWorkItemProvider::STATUS_OPEN => ['class' => 'badge badge-danger', 'text' => $workItemStatusLabels[CustomerServiceWorkItemProvider::STATUS_OPEN]],
            CustomerServiceWorkItemProvider::STATUS_CLOSED => ['class' => 'badge badge-success', 'text' => $workItemStatusLabels[CustomerServiceWorkItemProvider::STATUS_CLOSED]],
            CustomerServiceWorkItemProvider::STATUS_INQUIRY => ['class' => 'badge badge-warning', 'text' => $workItemStatusLabels[CustomerServiceWorkItemProvider::STATUS_INQUIRY]],
            CustomerThread::STATUS_IN_PROGRESS => ['class' => 'badge badge-warning', 'text' => $threadStatusLabels[CustomerThread::STATUS_IN_PROGRESS]],
            CustomerServiceWorkItemProvider::STATUS_WAITING_CUSTOMER => ['class' => 'badge badge-info', 'text' => $workItemStatusLabels[CustomerServiceWorkItemProvider::STATUS_WAITING_CUSTOMER]],
        ];

        if (! array_key_exists($value, $statuses)) {
            $value = 'unknown';
        }

        return '<span class="'.$statuses[$value]['class'] . '">' . $statuses[$value]['text'] . '</span>';
    }

    /**
     * @param int|string $value
     * @param array<string, mixed> $row
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderAssignedEmployee($value, array $row)
    {
        $employees = $this->getAssignableEmployeeOptions();
        $idEmployee = (int) $value;
        $options = '';
        foreach ($employees as $employeeId => $employeeName) {
            $options .= '<option value="'.(int)$employeeId.'"'.((int)$employeeId === $idEmployee ? ' selected="selected"' : '').'>'
                .Tools::safeOutput($employeeName).'</option>';
        }

        return '<select class="form-control input-sm js-entity-employee-assignment"'
            .' data-entity-type="'.(int)($row['entity_type'] ?? 0).'"'
            .' data-id-entity="'.(int)($row['id_entity'] ?? 0).'">'.$options.'</select>';
    }

    public function renderWorkItemEntity($value, array $row): string
    {
        $labels = $this->getWorkItemEntityTypes();
        $label = $labels[(int)$value] ?? $this->l('Unknown');

        return Tools::safeOutput($label).' <span class="text-muted">#'.(int)($row['id_entity'] ?? 0).'</span>';
    }

    public function renderWorkItemCustomer($value, array $row): string
    {
        $label = trim((string)$value);
        if ($label === '') {
            $label = (string)($row['email'] ?? '');
        }
        if ($label === '') {
            $label = '-';
        }
        if ((int)($row['id_customer'] ?? 0) <= 0) {
            return Tools::safeOutput($label);
        }

        $url = $this->context->link->getAdminLink('AdminCustomers', true, [
            'viewcustomer' => 1,
            'id_customer' => (int)$row['id_customer'],
        ]);

        return '<a href="'.Tools::safeOutput($url).'">'.Tools::safeOutput($label).'</a>';
    }

    /** @param array<string, mixed> $workItem */
    private function addWorkItemActions(array &$workItem): void
    {
        $entityType = (int)($workItem['entity_type'] ?? 0);
        $idEntity = (int)($workItem['id_entity'] ?? 0);
        $idThread = (int)($workItem['id_customer_thread'] ?? 0);
        $entityUrl = $this->getWorkItemEntityUrl($entityType, $idEntity);
        $threadUrl = $idThread > 0 ? $this->getThreadUrl($idThread) : '';

        // A queue row represents one task. Structured tasks always open their
        // domain entity; a free customer request is itself the domain entity.
        $targetUrl = $entityUrl !== '' ? $entityUrl : $threadUrl;
        if ($targetUrl !== '') {
            $workItem['primary'] = $this->renderWorkItemActionLink($targetUrl, $this->l('Edit'), 'icon-pencil');
        }

        if (
            $entityType === (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE
            && $idThread > 0
            && $this->hasDeletePermission()
        ) {
            $workItem['remove'] = $this->renderWorkItemDeleteLink($idThread);
        }
    }

    private function renderWorkItemActionLink(string $url, string $label, string $icon): string
    {
        return '<a href="'.Tools::safeOutput($url).'" class="btn btn-default" title="'.Tools::safeOutput($label).'">'
            .'<i class="'.Tools::safeOutput($icon).'"></i> '.Tools::safeOutput($label).'</a>';
    }

    private function renderWorkItemDeleteLink(int $idCustomerThread): string
    {
        $label = $this->l('Delete');
        $confirm = json_encode($this->l('Delete selected item?', 'Helper'));
        $url = $this->context->link->getAdminLink('AdminCustomerThreads', true, [
            'deletecustomer_thread' => 1,
            'id_customer_thread' => $idCustomerThread,
        ]);

        return '<a href="'.Tools::safeOutput($url).'" class="delete" title="'.Tools::safeOutput($label).'"'
            .' onclick="return confirm('.htmlspecialchars((string)$confirm, ENT_QUOTES, 'UTF-8').');">'
            .'<i class="icon-trash"></i> '.Tools::safeOutput($label).'</a>';
    }

    private function getThreadUrl(int $idCustomerThread): string
    {
        return $this->context->link->getAdminLink('AdminCustomerThreads', true, [
            'viewcustomer_thread' => 1,
            'id_customer_thread' => $idCustomerThread,
        ]);
    }

    private function getReplyReturnUrl(CustomerThread $thread): string
    {
        $entityType = Tools::getIntValue('return_entity_type');
        $idEntity = Tools::getIntValue('return_id_entity');
        if (
            $entityType > 0
            && $idEntity > 0
            && (int)$thread->entity_type === $entityType
            && (int)$thread->id_entity === $idEntity
        ) {
            $entityUrl = $this->getWorkItemEntityUrl($entityType, $idEntity);
            if ($entityUrl !== '') {
                return $entityUrl.'&conf=4';
            }
        }

        return $this->getThreadUrl((int)$thread->id);
    }

    private function redirectEntityView(int $entityType, int $idEntity): void
    {
        if (
            $entityType <= 0
            || $idEntity <= 0
            || $entityType === (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE
            || !(new CustomerServiceWorkItemProvider())->entityExists($entityType, $idEntity)
        ) {
            return;
        }

        $entityUrl = $this->getWorkItemEntityUrl($entityType, $idEntity);
        if ($entityUrl !== '') {
            Tools::redirectAdmin($entityUrl);
        }
    }

    private function getWorkItemEntityUrl(int $entityType, int $idEntity): string
    {
        $targets = [
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE => ['AdminOrderCancellations', 'vieworder_cancellation', 'id_order_cancellation'],
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE => ['AdminReturn', 'updateorder_return', 'id_order_return'],
            (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE => ['AdminOrderServiceCases', 'vieworder_service_case', 'id_order_service_case'],
            (int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE => ['AdminProductWishes', 'viewgenzo_crm_product_wish', 'id_product_wish'],
        ];
        if (!isset($targets[$entityType])) {
            return '';
        }
        [$controller, $action, $identifier] = $targets[$entityType];

        return $this->context->link->getAdminLink($controller, true, [
            $action => 1,
            $identifier => $idEntity,
        ]);
    }

    /** @return int[] */
    private function getThreadAssignmentTarget(CustomerThread $thread): array
    {
        $entityType = (int)$thread->entity_type;
        $idEntity = (int)$thread->id_entity;
        $provider = new CustomerServiceWorkItemProvider();
        if (
            $entityType !== (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE
            && $provider->entityExists($entityType, $idEntity)
        ) {
            return [$entityType, $idEntity];
        }

        return [(int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE, (int)$thread->id];
    }
}
