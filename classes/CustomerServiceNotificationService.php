<?php

/**
 * Sends one-way customer notifications through the installed notification provider.
 * Notifications deliberately create neither a customer-service thread nor an email.
 */
class CustomerServiceNotificationServiceCore
{
    public function notify(
        int $idCustomer,
        string $eventKey,
        string $sourceRef,
        array $values,
        int $targetEntityType,
        int $targetIdEntity,
        string $url
    ): bool {
        if (
            $idCustomer <= 0
            || trim($eventKey) === ''
            || trim($sourceRef) === ''
            || $targetEntityType <= 0
            || $targetIdEntity <= 0
        ) {
            return false;
        }

        $responses = Hook::exec(
            'actionCreateGenzoNotification',
            [
                'operation' => 'create',
                'id_customer_recipient' => $idCustomer,
                'id_actor_type' => 'company',
                'id_actor' => 1,
                'event_key' => $eventKey,
                'values' => $values,
                'source_ref' => $sourceRef,
                'target_entity_type' => $targetEntityType,
                'target_id_entity' => $targetIdEntity,
                'url' => $url,
            ],
            null,
            true,
            false
        );

        if (!is_array($responses)) {
            return (bool)$responses;
        }

        foreach ($responses as $response) {
            if ($response) {
                return true;
            }
        }

        return false;
    }

    public function remove(string $eventKey, string $sourceRef, int $idCustomer): bool
    {
        if (trim($eventKey) === '' || trim($sourceRef) === '' || $idCustomer <= 0) {
            return false;
        }

        $responses = Hook::exec(
            'actionCreateGenzoNotification',
            [
                'operation' => 'delete',
                'event_key' => $eventKey,
                'source_ref' => $sourceRef,
                'id_customer_recipient' => $idCustomer,
            ],
            null,
            true,
            false
        );

        if (!is_array($responses)) {
            return (bool)$responses;
        }

        foreach ($responses as $response) {
            if ($response) {
                return true;
            }
        }

        return false;
    }
}
