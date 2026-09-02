<?php

/**
 * Single persistence boundary for customer-service threads, messages and attachments.
 */
class CustomerServiceMessageServiceCore
{
    /**
     * @param array<string, mixed> $request
     *
     * @return array{thread: CustomerThread, message: CustomerMessage}
     *
     * @throws PrestaShopException
     */
    public function save(array $request): array
    {
        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) {
            throw new PrestaShopException('The customer-service transaction could not be started.');
        }

        try {
            $result = $this->saveWithinTransaction($request);
            if (!$db->execute('COMMIT')) {
                throw new PrestaShopException('The customer-service transaction could not be committed.');
            }

            return $result;
        } catch (Throwable $exception) {
            $db->execute('ROLLBACK');
            if ($exception instanceof PrestaShopException) {
                throw $exception;
            }

            throw new PrestaShopException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Persist inside a transaction owned by a higher-level domain workflow.
     *
     * @param array<string, mixed> $request
     *
     * @return array{thread: CustomerThread, message: CustomerMessage}
     *
     * @throws PrestaShopException
     */
    public function saveWithinTransaction(array $request): array
    {
        $request = $this->normalizeRequest($request);
        $this->validateRequest($request);
        $thread = $this->resolveThread($request);
        $threadCreated = !Validate::isLoadedObject($thread);

        if ($threadCreated) {
            $thread->id_customer = $request['idCustomer'];
            $thread->id_shop = $request['idShop'];
            $thread->id_lang = $request['idLang'];
            // @deprecated id_contact is retained only for historical threads. New
            // customer-service routing is based on the domain entity and assignment.
            $thread->id_contact = 0;
            $thread->email = $request['email'];
            $thread->token = Tools::passwdGen(12);
            $thread->entity_type = $request['entityType'];
            $thread->id_entity = $request['idEntity'];
            $thread->setDataArray($request['threadData']);
        } else {
            $this->assertThreadAccess($thread, $request);
            if ($request['threadData']) {
                $thread->setDataArray(array_replace($thread->getDataArray(), $request['threadData']));
            }
        }

        if ($request['status'] !== null) {
            $thread->status = $request['status'];
        } elseif ($threadCreated) {
            $thread->status = CustomerThread::STATUS_OPEN;
        }
        if (!$thread->save()) {
            throw new PrestaShopException('The customer-service thread could not be saved.');
        }

        $message = new CustomerMessage();
        $message->id_customer_thread = (int)$thread->id;
        $message->id_employee = $request['idEmployee'];
        $message->message = CustomerMessage::sanitizeContent($request['message']);
        $message->private = $request['private'];
        $message->ip_address = (int)ip2long(Tools::getRemoteAddr());
        $userAgentSize = (int)ObjectModel::getDefinition('CustomerMessage', 'user_agent')['size'];
        $message->user_agent = Tools::substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, $userAgentSize);

        $hasVisibleMessage = CustomerMessage::hasVisibleContent($message->message);
        if (!$hasVisibleMessage && !$request['attachments']) {
            throw new PrestaShopException('The customer-service message cannot be blank.');
        }
        if (!$hasVisibleMessage) {
            $message->message = '';
        }
        $validation = $message->validateField('message', $message->message, null, [], true);
        if ($validation !== true) {
            throw new PrestaShopException((string)$validation);
        }
        if (!$message->add()) {
            throw new PrestaShopException('The customer-service message could not be saved.');
        }
        if (!CustomerMessageAttachment::assignToMessage($request['attachments'], (int)$message->id)) {
            throw new PrestaShopException('The customer-service attachments could not be saved.');
        }

        return [
            'thread' => $thread,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function normalizeRequest(array $request): array
    {
        return array_replace([
            'idCustomer' => 0,
            'idVisitor' => 0,
            'idEmployee' => 0,
            'idShop' => 0,
            'idLang' => 0,
            'idCustomerThread' => 0,
            'entityType' => 0,
            'idEntity' => 0,
            'email' => '',
            'token' => '',
            'message' => '',
            'private' => false,
            'status' => CustomerThread::STATUS_OPEN,
            'threadData' => [],
            'attachments' => [],
        ], $request);
    }

    /**
     * @throws PrestaShopException
     */
    private function validateRequest(array $request): void
    {
        if ($request['idShop'] <= 0 || $request['idLang'] <= 0 || !Validate::isEmail($request['email'])) {
            throw new PrestaShopException('The customer-service message context is invalid.');
        }
        if (($request['entityType'] > 0) !== ($request['idEntity'] > 0)) {
            throw new PrestaShopException('The customer-service entity reference is incomplete.');
        }
        if (
            $request['entityType'] > 0
            && !\CoreExtension\EntityTypeEnum::tryFrom($request['entityType'])
        ) {
            throw new PrestaShopException('The customer-service entity type is invalid.');
        }
        if ($request['entityType'] === \CoreExtension\EntityTypeEnum::PRODUCT_VALUE) {
            throw new PrestaShopException('A product cannot be used as a customer-service entity.');
        }
        if ($request['entityType'] > 0 && $request['idCustomer'] <= 0 && $request['idEmployee'] <= 0) {
            throw new PrestaShopException('A customer is required for this customer-service entity.');
        }
        if (
            $request['entityType'] > 0
            && $request['idCustomer'] > 0
            && !$this->canCustomerReference(
                $request['entityType'],
                $request['idEntity'],
                $request['idCustomer']
            )
        ) {
            throw new PrestaShopException('The customer-service entity does not belong to this customer.');
        }
        if (
            $request['status'] !== null
            && !in_array($request['status'], CustomerThread::$definition['fields']['status']['values'], true)
        ) {
            throw new PrestaShopException('The customer-service thread status is invalid.');
        }
        if ($request['threadData'] && json_encode($request['threadData']) === false) {
            throw new PrestaShopException('The customer-service thread data is invalid.');
        }

        foreach ($request['attachments'] as $attachment) {
            if (
                !$attachment instanceof CustomerMessageAttachment
                || !Validate::isLoadedObject($attachment)
                || (int)$attachment->id_customer_message !== 0
            ) {
                throw new PrestaShopException('A customer-service attachment is invalid.');
            }

            $ownedByActor = $request['idEmployee'] > 0
                ? (int)$attachment->id_employee === $request['idEmployee']
                : ($request['idCustomer'] > 0
                    ? (int)$attachment->id_customer === $request['idCustomer']
                    : $request['idVisitor'] > 0
                        && (int)$attachment->id_visitor === $request['idVisitor']);
            if (!$ownedByActor) {
                throw new PrestaShopException('A customer-service attachment does not belong to this actor.');
            }
        }
    }

    private function resolveThread(array $request): CustomerThread
    {
        if ($request['idCustomerThread'] > 0) {
            return new CustomerThread($request['idCustomerThread']);
        }
        if ($request['idCustomer'] > 0 && $request['entityType'] > 0) {
            $idThread = CustomerThread::getIdByEntity(
                $request['idCustomer'],
                $request['entityType'],
                $request['idEntity']
            );
            if ($idThread > 0) {
                return new CustomerThread($idThread);
            }
        }

        return new CustomerThread();
    }

    /**
     * @throws PrestaShopException
     */
    private function assertThreadAccess(CustomerThread $thread, array $request): void
    {
        $customerOwnsThread = $request['idCustomer'] > 0
            && (int)$thread->id_customer === $request['idCustomer'];
        $guestOwnsThread = $request['idCustomer'] === 0
            && $request['token'] !== ''
            && hash_equals((string)$thread->token, $request['token']);
        $employeeAccess = $request['idEmployee'] > 0;
        if (!$customerOwnsThread && !$guestOwnsThread && !$employeeAccess) {
            throw new PrestaShopException('The customer-service thread is not accessible to this actor.');
        }

        if ($request['entityType'] > 0 && (
            (int)$thread->entity_type !== $request['entityType']
            || (int)$thread->id_entity !== $request['idEntity']
        )) {
            throw new PrestaShopException('The customer-service thread belongs to a different entity.');
        }

        if (
            (int)$thread->entity_type > 0
            && (int)$thread->id_entity > 0
            && $request['idCustomer'] > 0
            && !$this->canCustomerReference(
                (int)$thread->entity_type,
                (int)$thread->id_entity,
                $request['idCustomer']
            )
        ) {
            throw new PrestaShopException('The customer-service entity does not belong to this customer.');
        }
    }

    public function canCustomerReference(int $entityTypeId, int $idEntity, int $idCustomer): bool
    {
        if ($entityTypeId <= 0 || $idEntity <= 0 || $idCustomer <= 0) {
            return false;
        }

        $entityType = \CoreExtension\EntityTypeEnum::tryFrom($entityTypeId);
        if (!$entityType) {
            return false;
        }
        $className = $entityType->getObjectModelClassName();
        if (!$className || !class_exists($className)) {
            return false;
        }

        $entity = new $className($idEntity);
        if (!Validate::isLoadedObject($entity)) {
            return false;
        }
        if (property_exists($entity, 'id_customer') && (int)$entity->id_customer > 0) {
            return (int)$entity->id_customer === $idCustomer;
        }
        if (property_exists($entity, 'id_order') && (int)$entity->id_order > 0) {
            $order = new Order((int)$entity->id_order);

            return Validate::isLoadedObject($order) && (int)$order->id_customer === $idCustomer;
        }

        return false;
    }
}
