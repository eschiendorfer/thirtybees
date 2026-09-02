<?php

/**
 * Saves an employee reply and notifies the customer by email and in the customer account.
 */
class CustomerServiceReplyServiceCore
{
    /**
     * @param array<string, mixed> $request
     *
     * @return array{thread: CustomerThread, message: CustomerMessage, email_sent: bool}
     *
     * @throws PrestaShopException
     */
    public function send(array $request): array
    {
        $idCustomer = (int)($request['idCustomer'] ?? 0);
        $customer = $idCustomer > 0 ? new Customer($idCustomer) : null;
        if ($idCustomer > 0 && !Validate::isLoadedObject($customer)) {
            throw new PrestaShopException('The customer-service customer could not be loaded.');
        }

        $result = (new CustomerServiceMessageService())->save($request);
        $thread = $result['thread'];
        $message = $result['message'];
        [$caseEntityType, $caseId] = $this->resolveCaseTarget($thread);

        $fromEmail = static::getSenderEmail((int)$thread->id_shop);
        $fromName = static::getSenderName((int)$thread->id_shop);
        $params = [
            '{reply}' => CustomerMessage::renderContent((string)$message->message),
            '{link}' => $this->getCustomerLink($thread, $caseEntityType, $caseId),
            '{firstname}' => $customer && Validate::isLoadedObject($customer) ? (string)$customer->firstname : '',
            '{lastname}' => $customer && Validate::isLoadedObject($customer) ? (string)$customer->lastname : '',
        ];

        $emailSent = Mail::Send(
            (int)$thread->id_lang,
            'reply_msg',
            sprintf(
                Mail::l('An answer to your message is available #ct%1$s #tc%2$s', (int)$thread->id_lang),
                (int)$thread->id,
                (string)$thread->token
            ),
            $params,
            (string)$thread->email,
            null,
            Tools::convertEmailToIdn($fromEmail),
            $fromName,
            CustomerMessageAttachment::buildMailAttachments((array)($request['attachments'] ?? [])),
            null,
            _PS_MAIL_DIR_,
            true,
            (int)$thread->id_shop
        );

        if (!(bool)$message->private && (int)$thread->id_customer > 0) {
            $this->notifyCustomer($thread, $message, $caseEntityType, $caseId);
        }

        return [
            'thread' => $thread,
            'message' => $message,
            'email_sent' => (bool)$emailSent,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveCaseTarget(CustomerThread $thread): array
    {
        $entityType = (int)$thread->entity_type;
        $idEntity = (int)$thread->id_entity;
        $structuredTypes = [
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE,
            (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE,
            (int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE,
        ];
        if (!in_array($entityType, $structuredTypes, true) || $idEntity <= 0) {
            $entityType = (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE;
            $idEntity = (int)$thread->id;
        }

        return [$entityType, $idEntity];
    }

    private function getCustomerLink(CustomerThread $thread, int $entityType, int $idEntity): string
    {
        $link = Context::getContext()->link;
        if ((int)$thread->id_customer <= 0) {
            // Email-only inquiries cannot access an account case. The email already
            // contains the reply, while this link opens a fresh contact request.
            return $link->getPageLink(
                'contact',
                true,
                (int)$thread->id_lang,
                null,
                false,
                (int)$thread->id_shop
            );
        }

        return $link->getModuleLink(
            'genzo_crm',
            'customer_service',
            [
                'view_case' => 1,
                'case_entity_type' => $entityType,
                'case_id' => $idEntity,
            ],
            true,
            (int)$thread->id_lang,
            (int)$thread->id_shop
        );
    }

    private function notifyCustomer(
        CustomerThread $thread,
        CustomerMessage $message,
        int $entityType,
        int $idEntity
    ): void
    {
        (new CustomerServiceNotificationService())->notify(
            (int)$thread->id_customer,
            'customer_service_reply',
            'customer_message:'.(int)$message->id.':employee_reply',
            Translate::getAdminTranslation(
                'You have received a new message from customer service.',
                'CustomerServiceReply'
            ),
            $entityType,
            $idEntity,
            $this->getCustomerLink($thread, $entityType, $idEntity)
        );
    }

    public static function getSenderEmail(int $idShop): string
    {
        $email = trim((string)Configuration::get(
            Configuration::CUSTOMER_SERVICE_EMAIL,
            null,
            null,
            $idShop
        ));
        if (!Validate::isEmail($email)) {
            $email = trim((string)Configuration::get(Configuration::SHOP_EMAIL, null, null, $idShop));
        }

        return $email;
    }

    public static function getSenderName(int $idShop): string
    {
        $name = trim((string)Configuration::get(
            Configuration::CUSTOMER_SERVICE_SENDER_NAME,
            null,
            null,
            $idShop
        ));

        return $name !== ''
            ? $name
            : trim((string)Configuration::get(Configuration::SHOP_NAME, null, null, $idShop));
    }
}
