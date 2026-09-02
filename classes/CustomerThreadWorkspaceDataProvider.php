<?php

/**
 * Builds the shared Back Office view data for customer-service conversations.
 */
class CustomerThreadWorkspaceDataProviderCore
{
    /** @var Context */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @return array{first_message: array<string, mixed>|null, messages: array<int, array<string, mixed>>}
     *
     * @throws PrestaShopException
     */
    public function getConversation(CustomerThread $thread): array
    {
        $messages = CustomerThread::getMessageCustomerThreads((int)$thread->id);
        foreach ($messages as &$message) {
            $message['customer_name'] = trim((string)$message['customer_name']);
            $message['message_html'] = CustomerMessage::renderWebContent((string)$message['message']);

            foreach ($message['attachments'] as &$attachment) {
                $attachment['inline_url'] = $this->getAttachmentUrl(
                    (int)$attachment['id_customer_message_attachment'],
                    ['inline' => 1]
                );
                $attachment['preview_url'] = !empty($attachment['is_image'])
                    ? $this->getAttachmentUrl(
                        (int)$attachment['id_customer_message_attachment'],
                        [
                            'thumbnail' => !empty($attachment['has_thumbnail']) ? 1 : 0,
                            'inline' => 1,
                        ]
                    )
                    : '';
            }
            unset($attachment);

            if (!empty($message['id_employee'])) {
                $employee = new Employee((int)$message['id_employee']);
                $message['employee_image'] = Validate::isLoadedObject($employee) ? $employee->getImage() : '';
            }
        }
        unset($message);

        $firstMessage = $messages ? reset($messages) : null;
        if ($firstMessage && empty($firstMessage['id_employee'])) {
            array_shift($messages);
        }

        return [
            'first_message' => $firstMessage,
            'messages' => $messages,
        ];
    }

    /**
     * @param array<string, int|string> $hiddenFields
     * @param array<string, mixed> $uploadData
     *
     * @return array<string, mixed>
     */
    public function getMessageFormData(
        int $idLang,
        ?Order $order,
        ?Customer $customer,
        string $formAction,
        string $submitName,
        array $hiddenFields,
        array $uploadData,
        ?string $currentStatus = null
    ): array {
        $defaultMessage = str_replace(
            '\\r\\n',
            "\n",
            Configuration::get('PS_CUSTOMER_SERVICE_SIGNATURE', $idLang)
        );

        $statusOptions = $this->getCommunicationStatusOptions();
        $message = (string)Tools::getValue('reply_message', $defaultMessage);
        if ($currentStatus === null || !isset($statusOptions[$currentStatus])) {
            $currentStatus = null;
        }
        $uploadPayload = json_decode((string)($uploadData['value'] ?? ''), true);
        $hasAttachments = is_array($uploadPayload) && !empty($uploadPayload['attachments']);
        $hasReplyContent = CustomerMessage::hasVisibleContent(CustomerMessage::sanitizeContent($message))
            || $hasAttachments;
        $selectedStatus = (string)Tools::getValue(
            'thread_status',
            $hasReplyContent || $currentStatus === null ? CustomerThread::STATUS_CLOSED : $currentStatus
        );
        if (!isset($statusOptions[$selectedStatus])) {
            $selectedStatus = CustomerThread::STATUS_CLOSED;
        }

        return [
            'form_action' => $formAction,
            'submit_name' => $submitName,
            'hidden_fields' => $hiddenFields,
            'order_messages' => OrderMessage::getOrderMessages($idLang, $order, $customer),
            'message' => $message,
            'upload' => $uploadData,
            'status_options' => $statusOptions,
            'selected_status' => $selectedStatus,
            'selected_status_option' => $statusOptions[$selectedStatus],
            'can_save_status' => $currentStatus !== null,
            'current_status' => $currentStatus,
            'has_reply_content' => $hasReplyContent,
        ];
    }

    /** @return array<string, array{label: string, button_class: string, badge_class: string}> */
    private function getCommunicationStatusOptions(): array
    {
        return [
            CustomerThread::STATUS_OPEN => [
                'label' => Translate::getAdminTranslation('Open', 'AdminCustomerThreads'),
                'button_class' => 'btn-danger',
                'badge_class' => 'badge-danger',
            ],
            CustomerThread::STATUS_IN_PROGRESS => [
                'label' => Translate::getAdminTranslation('In inquiry', 'AdminCustomerThreads'),
                'button_class' => 'btn-warning',
                'badge_class' => 'badge-warning',
            ],
            CustomerThread::STATUS_WAITING_CUSTOMER => [
                'label' => Translate::getAdminTranslation('Waiting for customer', 'AdminCustomerThreads'),
                'button_class' => 'btn-info',
                'badge_class' => 'badge-info',
            ],
            CustomerThread::STATUS_CLOSED => [
                'label' => Translate::getAdminTranslation('Completed', 'AdminCustomerThreads'),
                'button_class' => 'btn-success',
                'badge_class' => 'badge-success',
            ],
        ];
    }

    /**
     * Build the common conversation block embedded in a domain-entity view.
     *
     * return_entity_type and return_id_entity are request-only navigation
     * parameters. The persisted relation remains entity_type/id_entity on the
     * customer thread itself.
     *
     * @return array<string, mixed>
     *
     * @throws PrestaShopException
     */
    public function getEntityConversation(
        int $entityType,
        int $idEntity,
        ?Customer $customer,
        ?Order $order,
        int $idLang,
        string $uploadHelp
    ): array {
        $thread = $this->findEntityThread($entityType, $idEntity);
        $conversation = $thread
            ? $this->getConversation($thread)
            : ['first_message' => null, 'messages' => []];
        $hiddenFields = [
            'return_entity_type' => $entityType,
            'return_id_entity' => $idEntity,
        ];

        if ($thread) {
            $formAction = $this->context->link->getAdminLink('AdminCustomerThreads', true, [
                'id_customer_thread' => (int)$thread->id,
                'viewcustomer_thread' => 1,
            ]);
            $submitName = 'submitReply';
            $hiddenFields['id_customer_thread'] = (int)$thread->id;
            $idLang = (int)$thread->id_lang;
        } else {
            $formAction = $this->context->link->getAdminLink('AdminCustomerThreads');
            $submitName = 'submitEntityThreadReply';
            $hiddenFields['entity_type'] = $entityType;
            $hiddenFields['id_entity'] = $idEntity;
        }

        return [
            'thread' => $thread,
            'first_message' => $conversation['first_message'],
            'messages' => $conversation['messages'],
            'message_form' => $this->getMessageFormData(
                $idLang,
                $order,
                $customer,
                $formAction,
                $submitName,
                $hiddenFields,
                $this->getUploadData([], $uploadHelp),
                $thread ? (string)$thread->status : null
            ),
        ];
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     *
     * @return array<string, mixed>
     */
    public function getUploadData(array $attachments, string $help): array
    {
        $payload = [];
        foreach ($attachments as $attachment) {
            $payload[] = $this->getAttachmentPayload($attachment);
        }

        $endpoint = $this->context->link->getAdminLink('AdminCustomerThreads');

        return [
            'value' => json_encode(['attachments' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'upload_url' => $endpoint,
            'delete_url' => $endpoint,
            'max_files' => CustomerMessageAttachment::getMaximumAttachmentCount(),
            'max_total_size' => CustomerMessageAttachment::getMaximumTotalBytes(),
            'help' => $help,
        ];
    }

    /** @return array<string, mixed> */
    public function getAttachmentPayload(CustomerMessageAttachment $attachment): array
    {
        return [
            'id_attachment' => (int)$attachment->id,
            'url' => $this->getAttachmentUrl(
                (int)$attachment->id,
                [
                    'thumbnail' => $attachment->thumbnailExists() ? 1 : 0,
                    'inline' => 1,
                ]
            ),
            'open_url' => $this->getAttachmentUrl((int)$attachment->id, ['inline' => 1]),
            'name' => $attachment->getFullFileName(),
            'mime' => (string)$attachment->mime_type,
            'file_size' => (int)$attachment->file_size,
            'upload_size' => (int)$attachment->upload_size,
        ];
    }

    /** @param array<string, int> $parameters */
    private function getAttachmentUrl(int $idAttachment, array $parameters = []): string
    {
        return $this->context->link->getAdminLink(
            'AdminCustomerThreads',
            true,
            array_merge(['showMessageAttachment' => $idAttachment], $parameters)
        );
    }

    private function findEntityThread(int $entityType, int $idEntity): ?CustomerThread
    {
        if ($entityType <= 0 || $idEntity <= 0) {
            return null;
        }

        $idThread = (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('`id_customer_thread`')
                ->from(CustomerThread::$definition['table'])
                ->where('`entity_type` = '.(int)$entityType)
                ->where('`id_entity` = '.(int)$idEntity)
                ->orderBy('`id_customer_thread` ASC')
        );
        if ($idThread <= 0) {
            return null;
        }

        $thread = new CustomerThread($idThread);

        return Validate::isLoadedObject($thread) ? $thread : null;
    }
}
