<?php

/**
 * Resolves the entity linked to a customer service thread and prepares its context.
 */
class CustomerThreadContextProviderCore
{
    /** @var Context */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForThread(CustomerThread $thread, $backOffice = true): ?array
    {
        $entityTypeId = (int) $thread->entity_type;
        $idEntity = (int) $thread->id_entity;

        $transitionContext = $this->getTransitionContext($thread);
        if ($transitionContext !== null) {
            return $this->prepareContext($transitionContext, (bool)$backOffice);
        }

        if ($entityTypeId <= 0 || $idEntity <= 0) {
            return null;
        }

        $entityType = \CoreExtension\EntityTypeEnum::tryFrom($entityTypeId);
        if (!$entityType) {
            return null;
        }

        $className = $entityType->getObjectModelClassName();
        if (!$className || !class_exists($className)) {
            return null;
        }

        $entity = new $className($idEntity);
        if (!Validate::isLoadedObject($entity) || !$entity instanceof CustomerThreadContextSourceInterfaceCore) {
            return null;
        }

        return $this->prepareContext($entity->getCustomerThreadContextData(), (bool) $backOffice);
    }

    /**
     * Resolve the context of a domain entity even before a conversation exists.
     *
     * @return array<string, mixed>|null
     */
    public function getForEntity(int $entityTypeId, int $idEntity, $backOffice = true): ?array
    {
        if ($entityTypeId <= 0 || $idEntity <= 0) {
            return null;
        }

        $entityType = \CoreExtension\EntityTypeEnum::tryFrom($entityTypeId);
        if (!$entityType) {
            return null;
        }

        $className = $entityType->getObjectModelClassName();
        if (!$className || !class_exists($className)) {
            return null;
        }

        $entity = new $className($idEntity);
        if (!Validate::isLoadedObject($entity) || !$entity instanceof CustomerThreadContextSourceInterfaceCore) {
            return null;
        }

        return $this->prepareContext($entity->getCustomerThreadContextData(), (bool)$backOffice);
    }

    /**
     * Render only known transition-data keys. Never expose arbitrary JSON.
     */
    protected function getTransitionContext(CustomerThread $thread): ?array
    {
        $data = $thread->getDataArray();
        $type = (string) ($data['type'] ?? '');
        $titles = [
            'technical_problem' => 'Technical problem',
            'content_report' => 'Content report',
            'job_application' => 'Job application',
            'supplier_proposal' => 'Supplier proposal',
            'sponsoring' => 'Sponsoring request',
            'partnership' => 'Collaboration',
            'business_other' => 'Other business request',
        ];
        if (!isset($titles[$type])) {
            return null;
        }

        $context = ['context_type' => $type, 'title' => $titles[$type], 'fields' => []];
        $relatedOrderId = (int)($data['related_order_id'] ?? 0);
        if (
            $relatedOrderId <= 0
            && (int)$thread->entity_type === \CoreExtension\EntityTypeEnum::ORDER_VALUE
        ) {
            $relatedOrderId = (int)$thread->id_entity;
        }
        if ($relatedOrderId > 0) {
            $order = new Order($relatedOrderId);
            if (Validate::isLoadedObject($order)) {
                $context['related_order_id'] = (int) $order->id;
                $context['related_order_reference'] = (string) $order->reference;
            }
        }
        if (!empty($data['source_url']) && Validate::isUrl((string) $data['source_url'])) {
            $context['fields'][] = [
                'label' => $this->translate('Source page'),
                'value' => (string) $data['source_url'],
                'url' => (string) $data['source_url'],
            ];
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    protected function prepareContext(array $data, $backOffice = true): ?array
    {
        if (empty($data['title'])) {
            return null;
        }

        $data['title'] = $this->translate((string) $data['title']);
        if (!empty($data['related_order_reference'])) {
            $data['related_order_label'] = $this->translate('Order');
        }
        if (!$backOffice && !empty($data['related_order_id'])) {
            $data['related_order_url'] = $this->context->link->getPageLink(
                'order-detail',
                true,
                null,
                ['id_order' => (int) $data['related_order_id']]
            );
        }
        unset($data['related_order_id']);
        if (!empty($data['fields'])) {
            $fields = [];
            foreach ($data['fields'] as $field) {
                if (!$backOffice && !empty($field['is_status'])) {
                    continue;
                }
                $field['label'] = $this->translate((string) ($field['label'] ?? ''));
                if (!empty($field['translate_value'])) {
                    $field['value'] = $this->translate((string) ($field['value'] ?? ''));
                }
                if ($backOffice && !empty($field['target'])) {
                    $field['url'] = $this->buildAdminUrl((array) $field['target']);
                }
                unset($field['target'], $field['translate_value'], $field['is_status']);
                $fields[] = $field;
            }
            $data['fields'] = $fields;
        }

        if (!empty($data['product_quantity_label'])) {
            $data['product_quantity_label'] = $this->translate((string) $data['product_quantity_label']);
        }

        if (!empty($data['products'])) {
            foreach ($data['products'] as &$product) {
                $idProduct = (int) ($product['id_product'] ?? 0);
                if ($idProduct <= 0) {
                    continue;
                }

                if ($backOffice) {
                    $product['url'] = $this->context->link->getAdminLink('AdminProducts', true, [
                        'id_product'    => $idProduct,
                        'updateproduct' => true,
                    ]);
                    $product['real_stock'] = Product::getRealQuantity(
                        $idProduct,
                        (int) ($product['id_product_attribute'] ?? 0),
                        0,
                        (int) $this->context->shop->id
                    );
                } else {
                    $productObject = new Product(
                        $idProduct,
                        false,
                        (int)$this->context->language->id,
                        (int)$this->context->shop->id
                    );
                    if (Validate::isLoadedObject($productObject) && (bool)$productObject->active) {
                        $product['url'] = $this->context->link->getProductLink($productObject);
                    } else {
                        unset($product['url']);
                    }
                }

                $cover = Product::getCover($idProduct, $this->context);
                if (!empty($cover['id_image'])) {
                    $imageId = (int) $cover['id_image'];
                    $product['thumbnail_url'] = $this->context->link->getImageLink((string) $idProduct, $imageId, 'small_default');
                    $product['image_url'] = $this->context->link->getImageLink((string) $idProduct, $imageId);
                }
            }
            unset($product);
        }

        if ($backOffice && !empty($data['action'])) {
            $data['action']['label'] = $this->translate((string) ($data['action']['label'] ?? 'Open'));
            $data['action']['url'] = $this->buildAdminUrl((array) $data['action']);
            unset($data['action']['controller'], $data['action']['params']);
        }
        if (!$backOffice) {
            unset($data['action']);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $target
     */
    protected function buildAdminUrl(array $target): string
    {
        $controller = (string) ($target['controller'] ?? '');
        if ($controller === '') {
            return '';
        }

        return $this->context->link->getAdminLink(
            $controller,
            true,
            (array) ($target['params'] ?? [])
        );
    }

    protected function translate(string $source): string
    {
        return Translate::getAdminTranslation($source, 'CustomerThreadContext', false, false);
    }
}
