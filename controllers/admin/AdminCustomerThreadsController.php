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

    /**
     * Contacts controller
     */
    const CONTACTS_CONTROLLER = 'AdminContacts';

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

        $contactArray = [];
        $contacts = Contact::getContacts($this->context->language->id, true);

        foreach ($contacts as $contact) {
            $contactArray[$contact['id_contact']] = $contact['name'];
        }

        $statusArray = $this->getCustomerServiceStatuses();
        $employeeArray = [-1 => $this->getUnassignedEmployeeLabel()];
        foreach ($this->getAssignableEmployees() as $employee) {
            $employeeArray[(int) $employee['id_employee']] = trim($employee['firstname'].' '.$employee['lastname']);
        }

        $this->fields_list = [
            'id_customer_thread' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'customer'           => [
                'title'          => $this->l('Customer'),
                'filter_key'     => 'customer',
                'tmpTableFilter' => true,
            ],
            'email'              => [
                'title'      => $this->l('Email'),
                'filter_key' => 'a!email',
            ],
            'contact'            => [
                'title'       => $this->l('Type'),
                'type'        => 'select',
                'list'        => $contactArray,
                'filter_key'  => 'cl!id_contact',
                'filter_type' => 'int',
            ],
            'status'             => [
                'title'       => $this->l('Status'),
                'type'        => 'select',
                'list'        => $statusArray,
                'align'       => 'center',
                'filter_key'  => 'a!status',
                'filter_type' => 'string',
                'callback'    => 'renderStatus',
            ],
            'id_employee_assigned' => [
                'title'       => $this->l('Employee'),
                'type'        => 'select',
                'list'        => $employeeArray,
                'filter_key'  => 'assigned_employee_filter',
                'filter_type' => 'int',
                'tmpTableFilter' => true,
                'callback'    => 'renderAssignedEmployee',
            ],
            'messages'           => [
                'title'          => $this->l('Messages'),
                'filter_key'     => 'messages',
                'tmpTableFilter' => true,
                'maxlength'      => 40,
            ],
            'date_upd'           => [
                'title'        => $this->l('Last message'),
                'havingFilter' => true,
                'type'         => 'datetime',
            ],
        ];

        $this->bulk_actions = [
            'delete' => [
                'text'    => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
                'icon'    => 'icon-trash',
            ],
        ];

        $this->shopLinkType = 'shop';

        parent::__construct();
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
            CustomerThread::STATUS_IN_PROGRESS      => $this->l('In progress'),
            CustomerThread::STATUS_WAITING_CUSTOMER => $this->l('Waiting for customer reply'),
            CustomerThread::STATUS_CLOSED           => $this->l('Closed'),
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
            $this->assignableEmployeesCache = array_values(array_filter(
                Employee::getEmployees(true),
                static function (array $employee): bool {
                    return in_array((int) $employee['id_employee'], CustomerThread::CUSTOMER_SERVICE_EMPLOYEE_IDS, true);
                }
            ));
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

        $this->addRowAction('view');
        $this->addRowAction('delete');

        $this->_select = '
			CONCAT(c.`firstname`," ",c.`lastname`) as customer, cl.`name` as contact, group_concat(message) as messages,
			IF(a.`id_employee_assigned` = 0, -1, a.`id_employee_assigned`) as assigned_employee_filter';

        $this->_join = '
			LEFT JOIN `'._DB_PREFIX_.'customer` c
				ON c.`id_customer` = a.`id_customer`
			INNER JOIN `'._DB_PREFIX_.'customer_message` cm
				ON (cm.`id_customer_thread` = a.`id_customer_thread` AND cm.`private` = 0)
			LEFT JOIN `'._DB_PREFIX_.'contact_lang` cl
				ON (cl.`id_contact` = a.`id_contact` AND cl.`id_lang` = '.(int) $this->context->language->id.')';

        if ($idOrder = Tools::getIntValue('id_order')) {
            $this->_where .= ' AND a.`entity_type` = '.(int)\CoreExtension\EntityTypeEnum::ORDER_VALUE
                .' AND a.`id_entity` = '.(int)$idOrder;
        }

        $this->_group = 'GROUP BY cm.id_customer_thread';
        $this->_orderBy = 'date_upd';
        $this->_orderWay = 'DESC';

        return parent::renderList();
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

                        // we want to assign unrecognized mails to the right contact category
                        $contacts = Contact::getContacts($this->context->language->id, true);
                        if (!$contacts) {
                            continue;
                        }

                        foreach ($contacts as $contact) {
                            if (strpos($overview->to, $contact['email']) !== false) {
                                $idContact = $contact['id_contact'];
                            }
                        }

                        if (!isset($idContact)) { // if not use the default contact category
                            $idContact = $contacts[0]['id_contact'];
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
                                'idContact' => $newCt ? (int)$idContact : (int)$ct->id_contact,
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

        $contactsTabId = Tab::getIdFromClassName(static::CONTACTS_CONTROLLER);
        if ($this->context->employee->hasAccess($contactsTabId, Profile::PERMISSION_VIEW)) {
            $this->page_header_toolbar_btn['contacts'] = [
                'href' => $this->context->link->getAdminLink(static::CONTACTS_CONTROLLER),
                'icon' => 'process-icon-envelope',
                'desc' => $this->l('Departments'),
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
     * @param bool $value
     * @param Customer $customer
     *
     * @return string
     */
    public function printOptinIcon($value, $customer)
    {
        return ($value ? '<i class="icon-check"></i>' : '<i class="icon-remove"></i>');
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function postProcess()
    {
        if ($idCustomerThread = Tools::getIntValue('id_customer_thread')) {
            if (($idContact = Tools::getIntValue('id_contact'))) {
                Db::getInstance()->execute(
                    '
					UPDATE '._DB_PREFIX_.'customer_thread
					SET id_contact = '.(int) $idContact.'
					WHERE id_customer_thread = '.(int) $idCustomerThread
                );
            }
            if ($idStatus = Tools::getIntValue('setstatus')) {
                $statusArray = [1 => CustomerThread::STATUS_OPEN, 2 => CustomerThread::STATUS_CLOSED, 3 => CustomerThread::STATUS_IN_PROGRESS];
                if (isset($statusArray[$idStatus])) {
                    Db::getInstance()->execute(
                    '
					UPDATE '._DB_PREFIX_.'customer_thread
					SET status = "'.$statusArray[$idStatus].'"
                    WHERE id_customer_thread = '.(int) $idCustomerThread.'
				'
                    );
                }
            }
            if (isset($_POST['id_employee_forward'])) {
                $messages = Db::readOnly()->getRow(
                    '
					SELECT ct.*, cm.*, cl.name subject, CONCAT(e.firstname, \' \', e.lastname) employee_name,
						CONCAT(c.firstname, \' \', c.lastname) customer_name, c.firstname
					FROM '._DB_PREFIX_.'customer_thread ct
					LEFT JOIN '._DB_PREFIX_.'customer_message cm
						ON (ct.id_customer_thread = cm.id_customer_thread)
					LEFT JOIN '._DB_PREFIX_.'contact_lang cl
						ON (cl.id_contact = ct.id_contact AND cl.id_lang = '.(int) $this->context->language->id.')
					LEFT OUTER JOIN '._DB_PREFIX_.'employee e
						ON e.id_employee = cm.id_employee
					LEFT OUTER JOIN '._DB_PREFIX_.'customer c
						ON (c.email = ct.email)
					WHERE ct.id_customer_thread = '.Tools::getIntValue('id_customer_thread').'
					ORDER BY cm.date_add DESC
				'
                );
                $output = $this->displayMessage($messages, true, Tools::getIntValue('id_employee_forward'));
                $currentEmployee = $this->context->employee;
                $idEmployee = Tools::getIntValue('id_employee_forward');
                $employee = new Employee($idEmployee);
                $email = Tools::convertEmailToIdn(Tools::getValue('email'));
                $message = Tools::getValue('message_forward');
                if (!CustomerMessage::hasVisibleContent(CustomerMessage::sanitizeContent((string)$message))) {
                    $this->errors[] = Tools::displayError('The message cannot be blank.');
                } elseif (Validate::isLoadedObject($employee)) {
                    $params = [
                        '{messages}'  => stripslashes($output),
                        '{employee}'  => $currentEmployee->firstname.' '.$currentEmployee->lastname,
                        '{comment}'   => stripslashes(Tools::nl2br($_POST['message_forward'])),
                        '{firstname}' => $employee->firstname,
                        '{lastname}'  => $employee->lastname,
                    ];

                    if (Mail::Send(
                        $this->context->language->id,
                        'forward_msg',
                        Mail::l('Fwd: Customer message', $this->context->language->id),
                        $params,
                        $employee->email,
                        $employee->firstname.' '.$employee->lastname,
                        $currentEmployee->email,
                        $currentEmployee->firstname.' '.$currentEmployee->lastname,
                        null,
                        null,
                        _PS_MAIL_DIR_,
                        true
                    )) {
                        $this->savePrivateEmployeeMessage(
                            $idCustomerThread,
                            $this->l('Message forwarded to').' '.$employee->firstname.' '.$employee->lastname."\n".$this->l('Comment:').' '.$message
                        );
                    }
                } elseif ($email && Validate::isEmail($email)) {
                    $params = [
                        '{messages}'  => Tools::nl2br(stripslashes($output)),
                        '{employee}'  => $currentEmployee->firstname.' '.$currentEmployee->lastname,
                        '{comment}'   => stripslashes($_POST['message_forward']),
                        '{firstname}' => '',
                        '{lastname}'  => '',
                    ];

                    if (Mail::Send(
                        $this->context->language->id,
                        'forward_msg',
                        Mail::l('Fwd: Customer message', $this->context->language->id),
                        $params,
                        $email,
                        null,
                        $currentEmployee->email,
                        $currentEmployee->firstname.' '.$currentEmployee->lastname,
                        null,
                        null,
                        _PS_MAIL_DIR_,
                        true
                    )) {
                        $this->savePrivateEmployeeMessage(
                            $idCustomerThread,
                            $this->l('Message forwarded to').' '.Tools::convertEmailFromIdn($email)."\n".$this->l('Comment:').' '.$message
                        );
                    }
                } else {
                    $this->errors[] = '<div class="alert error">'.Tools::displayError('The email address is invalid.').'</div>';
                }
            }
            if (Tools::isSubmit('submitReply')) {
                $ct = new CustomerThread($idCustomerThread);
                $replyStatus = (string) Tools::getValue('thread_status', $ct->status);
                $idEmployee = (int) $this->context->employee->id;
                $pendingAttachments = CustomerMessageAttachment::getOwnedPending(
                    $this->getSubmittedAttachmentIds(),
                    0,
                    0,
                    $idEmployee
                );
                if ($pendingAttachments === false) {
                    $pendingAttachments = [];
                    $this->errors[] = Tools::displayError('The selected attachment is invalid.');
                }

                $uploadedFiles = CustomerMessageAttachment::getUploadedFiles('file_attachment');
                if (!array_key_exists($replyStatus, $this->getCustomerServiceStatuses())) {
                    $this->errors[] = Tools::displayError('The selected status is invalid.');
                }
                $replyMessage = (string)Tools::getValue('reply_message');
                if (!Validate::isEmail(Tools::getValue('msg_email'))) {
                    $this->errors[] = Tools::displayError('The email address is invalid.');
                }
                $this->errors = array_merge(
                    $this->errors,
                    CustomerMessageAttachment::validateUploadedFiles(
                        $uploadedFiles,
                        true,
                        count($pendingAttachments),
                        CustomerMessageAttachment::getTotalUploadSize($pendingAttachments)
                    )
                );

                if (!$this->errors && $uploadedFiles) {
                    try {
                        $pendingAttachments = array_merge(
                            $pendingAttachments,
                            CustomerMessageAttachment::storeUploadedFiles($uploadedFiles, 0, 0, 0, $idEmployee)
                        );
                    } catch (Exception $exception) {
                        $this->errors[] = Tools::displayError('An error occurred during the file upload process.');
                    }
                }

                if (!$this->errors && !CustomerMessageAttachment::canAttachToEmail($pendingAttachments)) {
                    $this->errors[] = sprintf(
                        Tools::displayError('The attachments exceed the maximum email size of %d MB.'),
                        CustomerMessageAttachment::getMaximumEmailTotalSizeMb()
                    );
                }

                $this->setSubmittedAttachmentIds(CustomerMessageAttachment::getIds($pendingAttachments));
                if (!$this->errors) {
                    $request = [
                        'idCustomer' => (int)$ct->id_customer,
                        'idEmployee' => $idEmployee,
                        'idShop' => (int)$ct->id_shop,
                        'idLang' => (int)$ct->id_lang,
                        'idContact' => (int)$ct->id_contact,
                        'idCustomerThread' => (int)$ct->id,
                        'email' => (string)$ct->email,
                        'message' => $replyMessage,
                        'status' => null,
                        'attachments' => $pendingAttachments,
                    ];
                    try {
                        $messageResult = (new CustomerServiceMessageService())->save($request);
                        $ct = $messageResult['thread'];
                        $cm = $messageResult['message'];
                    } catch (PrestaShopException $exception) {
                        PrestaShopLogger::addLog($exception->getMessage(), 3);
                        $this->errors[] = Tools::displayError('An error occurred while saving the message.');
                    }
                }

                if (!$this->errors) {
                    $_POST['reply_message'] = '';
                    $this->setSubmittedAttachmentIds([]);
                    $customer = new Customer($ct->id_customer);
                    $params = [
                        '{reply}'     => CustomerMessage::renderContent($cm->message),
                        '{link}'      => Tools::url(
                            $this->context->link->getPageLink('contact', true, null, null, false, $ct->id_shop),
                            'id_customer_thread='.(int) $ct->id.'&token='.$ct->token
                        ),
                        '{firstname}' => $customer->firstname,
                        '{lastname}'  => $customer->lastname,
                    ];
                    //#ct == id_customer_thread    #tc == token of thread   <== used in the synchronization imap
                    $contact = new Contact((int) $ct->id_contact, (int) $ct->id_lang);

                    if (Validate::isLoadedObject($contact)) {
                        $fromName = $contact->name;
                        $fromEmail = $contact->email;
                    } else {
                        $fromName = null;
                        $fromEmail = null;
                    }

                    if (!Mail::Send(
                        (int) $ct->id_lang,
                        'reply_msg',
                        sprintf(Mail::l('An answer to your message is available #ct%1$s #tc%2$s', $ct->id_lang), $ct->id, $ct->token),
                        $params,
                        Tools::getValue('msg_email'),
                        null,
                        Tools::convertEmailToIdn($fromEmail),
                        $fromName,
                        CustomerMessageAttachment::buildMailAttachments($pendingAttachments),
                        null,
                        _PS_MAIL_DIR_,
                        true,
                        $ct->id_shop
                    )) {
                        $this->errors[] = Tools::displayError('The message was saved, but the email could not be sent to the customer.');
                    }

                    if (!$this->errors) {
                        $ct->status = $replyStatus;
                        if (in_array($idEmployee, $this->getAssignableEmployeeIds(), true)) {
                            $ct->id_employee_assigned = $idEmployee;
                        }
                        if ($ct->update()) {
                            Tools::redirectAdmin(
                                static::$currentIndex.'&id_customer_thread='.(int) $idCustomerThread.'&viewcustomer_thread&token='.Tools::getValue('token')
                            );
                        }
                        $this->errors[] = Tools::displayError('The thread settings could not be updated.');
                    }
                }
            }
        }

        return parent::postProcess();
    }

    /**
     * @param array $message
     * @param bool $email
     * @param int|null $idEmployee
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function displayMessage($message, $email = false, $idEmployee = null)
    {
        $tpl = $this->createTemplate('message.tpl');

        $contacts = Contact::getContacts($this->context->language->id, true);
        $contactArray = [];
        foreach ($contacts as $contact) {
            $contactArray[$contact['id_contact']] = ['id_contact' => $contact['id_contact'], 'name' => $contact['name']];
        }
        $contacts = $contactArray;

        $message['date_add'] = Tools::displayDate($message['date_add'], null, true);
        $message['user_agent'] = strip_tags($message['user_agent']);
        $message['message'] = preg_replace(
            '/(https?:\/\/[a-z0-9#%&_=\(\)\.\? \+\-@\/]{6,1000})([\s\n<])/Uui',
            '<a href="\1">\1</a>\2',
            CustomerMessage::renderContent((string) $message['message'])
        );

        $tpl->assign(
            [
                'thread_url'        => Tools::getAdminUrl(basename(_PS_ADMIN_DIR_).'/'.$this->context->link->getAdminLink('AdminCustomerThreads').'&amp;id_customer_thread='.(int) $message['id_customer_thread'].'&amp;viewcustomer_thread=1'),
                'link'              => $this->context->link,
                'current'           => static::$currentIndex,
                'token'             => $this->token,
                'message'           => $message,
                'email'             => Tools::convertEmailFromIdn($email),
                'id_employee'       => $idEmployee,
                'PS_SHOP_NAME'      => Configuration::get('PS_SHOP_NAME'),
                'contacts'          => $contacts,
            ]
        );

        return $tpl->fetch();
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
        if ($field === 'status') {
            if (!array_key_exists($value, $this->getCustomerServiceStatuses())) {
                $this->respondThreadSettingJson(false, $this->l('The selected status is invalid.'));
            }
            $thread->status = $value;
        } elseif ($field === 'id_employee_assigned') {
            $idEmployee = (int) $value;
            if ($idEmployee !== 0 && !in_array($idEmployee, $this->getAssignableEmployeeIds(), true)) {
                $this->respondThreadSettingJson(false, $this->l('The selected employee cannot be assigned to customer service threads.'));
            }
            $thread->id_employee_assigned = $idEmployee;
        } else {
            $this->respondThreadSettingJson(false, $this->l('The selected setting is invalid.'));
        }

        $updated = $thread->update();
        $this->respondThreadSettingJson(
            $updated,
            $updated ? $this->l('The thread has been updated.') : $this->l('The thread could not be updated.')
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
        $time = time();
        $kpis = [];

        /* The data generation is located in AdminStatsControllerCore */

        $helper = new HelperKpi();
        $helper->id = 'box-pending-messages';
        $helper->icon = 'icon-envelope';
        $helper->color = 'color1';
        $helper->href = $this->context->link->getAdminLink('AdminCustomerThreads');
        $helper->title = $this->l('Pending Discussion Threads', null, null, false);
        if (ConfigurationKPI::get('PENDING_MESSAGES') !== false) {
            $helper->value = ConfigurationKPI::get('PENDING_MESSAGES');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=pending_messages';
        $helper->refresh = (bool) (ConfigurationKPI::get('PENDING_MESSAGES_EXPIRE') < $time);
        $kpis[] = $helper;

        $helper = new HelperKpi();
        $helper->id = 'box-age';
        $helper->icon = 'icon-time';
        $helper->color = 'color2';
        $helper->title = $this->l('Average Response Time', null, null, false);
        $helper->subtitle = $this->l('30 days', null, null, false);
        if (ConfigurationKPI::get('AVG_MSG_RESPONSE_TIME', $this->context->employee->id_lang) !== false) {
            $helper->value = ConfigurationKPI::get('AVG_MSG_RESPONSE_TIME', $this->context->employee->id_lang);
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=avg_msg_response_time';
        $helper->refresh = (bool) (ConfigurationKPI::get('AVG_MSG_RESPONSE_TIME_EXPIRE', $this->context->employee->id_lang) < $time);
        $kpis[] = $helper;

        $helper = new HelperKpi();
        $helper->id = 'box-messages-per-thread';
        $helper->icon = 'icon-copy';
        $helper->color = 'color3';
        $helper->title = $this->l('Messages per Thread', null, null, false);
        $helper->subtitle = $this->l('30 day', null, null, false);
        if (ConfigurationKPI::get('MESSAGES_PER_THREAD') !== false) {
            $helper->value = ConfigurationKPI::get('MESSAGES_PER_THREAD');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=messages_per_thread';
        $helper->refresh = (bool) (ConfigurationKPI::get('MESSAGES_PER_THREAD_EXPIRE') < $time);
        $kpis[] = $helper;

        return $kpis;
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
        if (!$idCustomerThread = Tools::getIntValue('id_customer_thread')) {
            return '';
        }

        /** @var CustomerThread $thread */
        $thread = $this->loadObject();
        if (! Validate::isLoadedObject($thread)) {
            return '';
        }
        $messages = CustomerThread::getMessageCustomerThreads($idCustomerThread);

        foreach ($messages as $key => $mess) {
            $messages[$key]['customer_name'] = trim((string) $mess['customer_name']);
            $messages[$key]['message_html'] = CustomerMessage::renderContent((string) $mess['message']);
            foreach ($mess['attachments'] as $attachmentKey => $attachment) {
                $attachmentUrl = $this->context->link->getAdminLink('AdminCustomerThreads', true, [
                    'showMessageAttachment' => (int) $attachment['id_customer_message_attachment'],
                ]);
                $messages[$key]['attachments'][$attachmentKey]['download_url'] = $attachmentUrl;
                $messages[$key]['attachments'][$attachmentKey]['inline_url'] = $this->context->link->getAdminLink('AdminCustomerThreads', true, [
                    'showMessageAttachment' => (int) $attachment['id_customer_message_attachment'],
                    'inline'                => 1,
                ]);
                $messages[$key]['attachments'][$attachmentKey]['preview_url'] = !empty($attachment['is_image'])
                    ? $this->context->link->getAdminLink('AdminCustomerThreads', true, [
                        'showMessageAttachment' => (int) $attachment['id_customer_message_attachment'],
                        'thumbnail'             => !empty($attachment['has_thumbnail']) ? 1 : 0,
                        'inline'                => 1,
                    ])
                    : '';
            }
            if ($mess['id_employee']) {
                $employee = new Employee($mess['id_employee']);
                $messages[$key]['employee_image'] = $employee->getImage();
            }

        }

        $contacts = Contact::getContacts($this->context->language->id, true);

        $customer = null;
        if ($thread->id_customer) {
            $customer = new Customer($thread->id_customer);
        }
        $firstMessage = $messages[0];

        if (!$messages[0]['id_employee']) {
            unset($messages[0]);
        }

        $contact = '';
        foreach ($contacts as $c) {
            if ($c['id_contact'] == $thread->id_contact) {
                $contact = $c['name'];
            }
        }

        $order = (int)$thread->entity_type === \CoreExtension\EntityTypeEnum::ORDER_VALUE
            ? new Order((int)$thread->id_entity)
            : null;
        if (! Validate::isLoadedObject($order)) {
            $order = null;
        }

        $threadContext = (new CustomerThreadContextProvider($this->context))->getForThread($thread);
        $defaultReplyMessage = str_replace('\r\n', "\n", Configuration::get('PS_CUSTOMER_SERVICE_SIGNATURE', (int) $thread->id_lang));
        $pendingAttachments = $this->getPendingEmployeeAttachments();
        $this->tpl_view_vars = [
            'id_customer_thread'            => $idCustomerThread,
            'thread'                        => $thread,
            'assignable_employees'          => $this->getAssignableEmployeeOptions(),
            'thread_statuses'               => $this->getCustomerServiceStatuses(),
            'current_employee'              => $this->context->employee,
            'orderMessages'                 => OrderMessage::getOrderMessages($thread->id_lang, $order, $customer),
            'messages'                      => $messages,
            'first_message'                 => $firstMessage,
            'contact'                       => $contact,
            'customer'                      => $customer ?? false,
            'reply_message'                 => (string) Tools::getValue('reply_message', $defaultReplyMessage),
            'thread_entity_context'         => $threadContext,
            'customer_overview'             => $this->getCustomerOverview($thread, $customer),
            'customer_message_upload'       => $this->getBackOfficeUploadData($pendingAttachments),
        ];

        return parent::renderView();
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
    protected function getCustomerOverview(CustomerThread $thread, ?Customer $customer): array
    {
        $overview = [
            'name'           => Validate::isLoadedObject($customer) ? $customer->firstname.' '.$customer->lastname : '',
            'email'          => Validate::isLoadedObject($customer) ? $customer->email : $thread->email,
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
                ->where('`id_customer_thread` != '.(int) $thread->id)
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
     * AdminController::getList() override
     *
     * @see AdminController::getList()
     *
     * @param int $idLang
     * @param string|null $orderBy
     * @param string|null $orderWay
     * @param int $start
     * @param int|null $limit
     * @param int|bool $idLangShop
     *
     * @throws PrestaShopException
     */
    public function getList($idLang, $orderBy = null, $orderWay = null, $start = 0, $limit = null, $idLangShop = false)
    {
        parent::getList($idLang, $orderBy, $orderWay, $start, $limit, $idLangShop);

        $nbItems = count($this->_list);
        for ($i = 0; $i < $nbItems; ++$i) {
            if (isset($this->_list[$i]['messages'])) {
                $this->_list[$i]['messages'] = Tools::htmlentitiesDecodeUTF8($this->_list[$i]['messages']);
            }
            if (isset($this->_list[$i]['email'])) {
                $this->_list[$i]['email'] = Tools::convertEmailFromIdn($this->_list[$i]['email']);
            }
        }
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

    /**
     * @return int[]
     */
    private function savePrivateEmployeeMessage(int $idCustomerThread, string $message): void
    {
        $thread = new CustomerThread($idCustomerThread);
        if (!Validate::isLoadedObject($thread)) {
            $this->errors[] = Tools::displayError('The customer-service thread is invalid.');
            return;
        }

        $request = [
            'idCustomer' => (int)$thread->id_customer,
            'idEmployee' => (int)$this->context->employee->id,
            'idShop' => (int)$thread->id_shop,
            'idLang' => (int)$thread->id_lang,
            'idContact' => (int)$thread->id_contact,
            'idCustomerThread' => (int)$thread->id,
            'email' => (string)$thread->email,
            'message' => $message,
            'private' => true,
            'status' => null,
        ];
        try {
            (new CustomerServiceMessageService())->save($request);
        } catch (PrestaShopException $exception) {
            PrestaShopLogger::addLog($exception->getMessage(), 3);
            $this->errors[] = Tools::displayError('The internal customer-service message could not be saved.');
        }
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
        $payload = [];
        foreach ($attachments as $attachment) {
            $payload[] = $this->getAttachmentPayload($attachment);
        }

        $maximumCount = CustomerMessageAttachment::getMaximumAttachmentCount();
        $maximumEmailSizeMb = CustomerMessageAttachment::getMaximumEmailTotalSizeMb();
        $endpoint = $this->context->link->getAdminLink('AdminCustomerThreads');

        return [
            'value'            => json_encode(['attachments' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'upload_url'       => $endpoint,
            'delete_url'       => $endpoint,
            'max_files'        => $maximumCount,
            'max_total_size'   => CustomerMessageAttachment::getMaximumTotalBytes(),
            'help'             => sprintf($this->l('Up to %1$d files and %2$d MB in total.'), $maximumCount, $maximumEmailSizeMb),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAttachmentPayload(CustomerMessageAttachment $attachment): array
    {
        return [
            'id_attachment' => (int) $attachment->id,
            'url'           => $this->context->link->getAdminLink('AdminCustomerThreads', true, [
                'showMessageAttachment' => (int) $attachment->id,
                'thumbnail'             => $attachment->thumbnailExists() ? 1 : 0,
                'inline'                => 1,
            ]),
            'open_url'      => $this->context->link->getAdminLink('AdminCustomerThreads', true, [
                'showMessageAttachment' => (int) $attachment->id,
                'inline'                => 1,
            ]),
            'name'          => $attachment->getFullFileName(),
            'mime'          => (string) $attachment->mime_type,
            'file_size'     => (int) $attachment->file_size,
            'upload_size'   => (int) $attachment->upload_size,
        ];
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
     * @param string $content
     *
     * @return string
     */
    protected function displayButton($content)
    {
        return '<div><p>'.$content.'</p></div>';
    }

    /**
     * @param string $value
     * @return string
     */
    public function renderStatus($value)
    {
        $statusLabels = $this->getCustomerServiceStatuses();
        $statuses = [
            'unknown'          => ['class' => 'badge', 'text' => $this->l('Unknown')],
            'open'             => ['class' => 'badge badge-danger', 'text' => $statusLabels[CustomerThread::STATUS_OPEN]],
            'closed'           => ['class' => 'badge badge-success', 'text' => $statusLabels[CustomerThread::STATUS_CLOSED]],
            'pending1'         => ['class' => 'badge badge-warning', 'text' => $statusLabels[CustomerThread::STATUS_IN_PROGRESS]],
            'waiting_customer' => ['class' => 'badge badge-info', 'text' => $statusLabels[CustomerThread::STATUS_WAITING_CUSTOMER]],
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
        $employeeName = $employees[$idEmployee] ?? $this->getUnassignedEmployeeLabel();

        if ($idEmployee <= 0 && ($row['status'] ?? null) !== CustomerThread::STATUS_CLOSED) {
            return '<span class="badge badge-warning">'.Tools::safeOutput($employeeName).'</span>';
        }

        if ($idEmployee === (int) $this->context->employee->id) {
            return '<span class="badge badge-info">'.Tools::safeOutput($employeeName).'</span>';
        }

        return $employeeName;
    }
}
