<?php

class CustomerServiceStatusCore
{
    public static function getEntity(int $entityType, int $idEntity): ?ObjectModel
    {
        $type = \CoreExtension\EntityTypeEnum::tryFrom($entityType);
        $class = $type ? $type->getObjectModelClassName() : null;
        if ($idEntity <= 0 || !$class || !is_a($class, CustomerServiceStatusSourceInterfaceCore::class, true)) {
            return null;
        }
        $entity = new $class($idEntity);
        return Validate::isLoadedObject($entity) ? $entity : null;
    }

    public static function getOwner(CustomerThread $thread): ObjectModel
    {
        return static::getEntity((int)$thread->entity_type, (int)$thread->id_entity) ?? $thread;
    }

    public static function getField(ObjectModel $owner): string
    {
        return $owner instanceof CustomerServiceStatusSourceInterfaceCore
            ? $owner->getCustomerServiceStatusField() : 'status';
    }

    public static function get(ObjectModel $owner): string
    {
        return (string)$owner->{static::getField($owner)};
    }

    public static function save(ObjectModel $owner, string $status): bool
    {
        if (!array_key_exists($status, static::getLabels($owner))) {
            throw new PrestaShopException(Tools::displayError('The selected status is invalid.'));
        }
        $owner->{static::getField($owner)} = $status;
        return $owner->update(true);
    }

    public static function getStandardLabels(): array
    {
        return ['open' => 'Open', 'pending1' => 'In inquiry', 'waiting_customer' => 'Waiting for customer', 'closed' => 'Completed'];
    }

    public static function getLabels(ObjectModel $owner): array
    {
        return $owner instanceof CustomerServiceStatusSourceInterfaceCore
            ? $owner->getCustomerServiceStatusLabels() : static::getStandardLabels();
    }

    public static function normalize(string $status): string
    {
        if ($status === 'open') {
            return 'open';
        }
        if (in_array($status, ['waiting_customer', 'waiting_package'], true)) {
            return 'waiting_customer';
        }
        return in_array($status, ['pending1', 'waiting'], true) ? 'inquiry' : 'closed';
    }

    /** Same mapping for the shared BO/FO queue, without combining two statuses. */
    public static function rankSql(string $field): string
    {
        return 'CASE WHEN '.$field.' = "open" THEN 4'
            .' WHEN '.$field.' IN ("waiting_customer", "waiting_package") THEN 3'
            .' WHEN '.$field.' IN ("pending1", "waiting") THEN 2 ELSE 1 END';
    }

    public static function getOptions(ObjectModel $owner): array
    {
        $colors = ['open' => 'danger', 'inquiry' => 'warning', 'waiting_customer' => 'info', 'closed' => 'success'];
        $options = [];
        foreach (static::getLabels($owner) as $value => $label) {
            $color = $colors[static::normalize($value)];
            $options[$value] = [
                'label' => Translate::getAdminTranslation($label, 'AdminCustomerThreads'),
                'button_class' => 'btn-'.$color,
                'badge_class' => 'badge-'.$color,
            ];
        }
        return $options;
    }
}
