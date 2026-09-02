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
 * Class ContactControllerCore
 */
class ContactControllerCore extends FrontController
{
    /** @var string $php_self */
    public $php_self = 'contact';
    /** @var bool $ssl */
    public $ssl = true;

    /**
     * Set media
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        parent::setMedia();
        $this->addCSS(_THEME_CSS_DIR_.'contact-form.css');
        $this->addJS(_THEME_JS_DIR_.'contact-form.js');
        $this->addJS(_PS_JS_DIR_.'validate.js');
    }

    /**
     * Assign template vars related to page content
     *
     * @throws PrestaShopException
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        if ($attachmentId = Tools::getIntValue('downloadCustomerMessageAttachment')) {
            $this->downloadCustomerMessageAttachment($attachmentId);
        }

        parent::initContent();

        $response = Hook::getFirstResponse('actionOverrideContactForm', ['controller' => $this]);
        if (is_array($response) && isset($response['template']) && isset($response['params'])) {
            $this->context->smarty->assign($response['params']);
            $this->setTemplate($response['template']);
        } else {
            $this->initStandardContactForm();
        }
    }

    /**
     * Stream a private customer message attachment after checking ownership
     * through the logged-in customer or the customer thread token.
     *
     * @param int $attachmentId
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function downloadCustomerMessageAttachment($attachmentId)
    {
        $attachment = new CustomerMessageAttachment((int) $attachmentId);
        if (!Validate::isLoadedObject($attachment) || !$attachment->fileExists()) {
            Tools::redirect('index.php?controller=404');
        }

        $message = new CustomerMessage((int) $attachment->id_customer_message);
        $thread = Validate::isLoadedObject($message)
            ? new CustomerThread((int) $message->id_customer_thread)
            : null;
        $loggedCustomerMatches = $thread instanceof CustomerThread
            && Validate::isLoadedObject($thread)
            && !empty($this->context->customer)
            && $this->context->customer->isLogged()
            && (int) $thread->id_customer > 0
            && (int) $thread->id_customer === (int) $this->context->customer->id;
        $submittedToken = (string) Tools::getValue('token');
        $tokenMatches = $thread instanceof CustomerThread
            && Validate::isLoadedObject($thread)
            && $submittedToken !== ''
            && hash_equals((string) $thread->token, $submittedToken);

        if (!$thread || !Validate::isLoadedObject($thread) || (int) $message->private || (!$loggedCustomerMatches && !$tokenMatches)) {
            Tools::redirect('index.php?controller=404');
        }

        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }
        $showThumbnail = Tools::getIntValue('thumbnail') === 1;
        if ($showThumbnail && !$attachment->thumbnailExists()) {
            Tools::redirect('index.php?controller=404');
        }
        $path = $showThumbnail ? $attachment->getThumbnailFilePath() : $attachment->getFilePath();
        $mimeType = $showThumbnail ? 'image/webp' : (string) $attachment->mime_type;
        $inline = $showThumbnail || strpos($mimeType, 'image/') === 0 || $mimeType === 'application/pdf';

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (int) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="' . $attachment->getFullFileName() . '"');
        readfile($path);
        exit;
    }

    /**
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function initStandardContactForm()
    {
        $email = Tools::convertEmailToIdn(Tools::safeOutput(
            Tools::getValue(
                'from',
                ((isset($this->context->cookie) && isset($this->context->cookie->email) && Validate::isEmail($this->context->cookie->email)) ? $this->context->cookie->email : '')
            )
        ));
        $customerServiceModule = Module::getInstanceByName('genzo_crm');
        $flow = (string) Tools::getValue('flow');
        if (!in_array($flow, ['order', 'phone_order', 'product_wish', 'technical_problem', 'business', 'general'], true)) {
            $flow = '';
        }

        $businessType = (string) Tools::getValue('business_type');
        if (
            $flow !== 'business'
            || !in_array($businessType, ['supplier_proposal', 'sponsoring', 'partnership'], true)
        ) {
            $businessType = '';
        }
        $assistantOrigin = $flow === 'business' && Tools::getValue('origin') === 'customer_service'
            ? 'customer_service'
            : '';

        $orderIntent = $flow === 'order' ? (string) Tools::getValue('intent') : '';
        if (!in_array($orderIntent, ['release_date', 'service_case', 'return', 'cancellation', 'general'], true)) {
            $orderIntent = '';
        }

        $isLogged = $this->context->customer->isLogged();
        $selectedOrderId = Tools::getIntValue('id_order');
        $assistantOrders = [];
        $assistantProducts = [];
        $selectedOrder = null;
        if ($isLogged && $flow === 'order' && $selectedOrderId > 0) {
            $selectedOrder = (new \CrmModule\CustomerServiceOrderService($this->context))
                ->getOwnedOrderSummary($selectedOrderId, (int)$this->context->customer->id);
            if (!$selectedOrder) {
                $selectedOrderId = 0;
            }
        }
        if (
            $isLogged
            && $flow === 'order'
            && in_array($orderIntent, ['service_case', 'return', 'release_date', 'cancellation', 'general'], true)
        ) {
            $orderService = new \CrmModule\CustomerServiceOrderService($this->context);
            $idCustomer = (int) $this->context->customer->id;

            switch ($orderIntent) {
                case 'service_case':
                    $assistantProducts = $orderService->getServiceCaseProducts($idCustomer);
                    break;
                case 'return':
                    $assistantProducts = $orderService->getReturnProducts($idCustomer);
                    break;
                case 'release_date':
                    $assistantProducts = $orderService->getReleaseProducts($idCustomer);
                    break;
                case 'cancellation':
                    $assistantProducts = $orderService->getCancellationProducts($idCustomer);
                    break;
                case 'general':
                    $assistantOrders = $orderService->getRecentOrders($idCustomer);
                    break;
            }
        }

        $customerMessageRte = [];
        if ($flow === 'product_wish') {
            $customerMessageRte = $customerServiceModule->getCustomerMessageRteData(
                'message',
                (string)Tools::getValue('message'),
                (string)Tools::getValue('message_media')
            );
        }

        $generalRequestRte = [];
        if ($flow === 'general' || $flow === 'technical_problem' || ($flow === 'business' && $businessType !== '')) {
            $generalRequestRte = $customerServiceModule->getCustomerMessageRteData(
                'message',
                (string)Tools::getValue('message'),
                (string)Tools::getValue('message_media'),
                $customerServiceModule->l('Send', 'customer_threads'),
                'submitCustomerServiceInquiry'
            );
        }

        $orderRequestRte = [];
        if ($selectedOrder) {
            $orderRequestRte = $customerServiceModule->getCustomerMessageRteData(
                'message',
                (string)Tools::getValue('message'),
                (string)Tools::getValue('message_media'),
                $customerServiceModule->l('Send', 'customer_threads'),
                'submitMessage'
            );
        }

        $contactUrl = $this->context->link->getPageLink('contact', true);
        $assistantBackUrl = '';
        if ($flow === 'order' && $orderIntent !== '') {
            $assistantBackUrl = $this->context->link->getPageLink(
                'contact',
                true,
                null,
                $selectedOrderId > 0
                    ? ['flow' => 'order', 'id_order' => $selectedOrderId]
                    : ['flow' => 'order']
            );
        } elseif ($flow === 'business' && $businessType !== '') {
            $assistantBackUrl = $this->context->link->getPageLink(
                'contact',
                true,
                null,
                $assistantOrigin ? ['flow' => 'business', 'origin' => $assistantOrigin] : ['flow' => 'business']
            );
        } elseif ($flow === 'business' && $assistantOrigin === 'customer_service') {
            $assistantBackUrl = $contactUrl;
        } elseif ($flow !== '' && $flow !== 'business') {
            $assistantBackUrl = $contactUrl;
        }

        $shopPhone = (string) Configuration::get('PS_SHOP_PHONE');

        $this->context->smarty->assign(
            [
                'errors' => $this->errors,
                'email' => $email,
                'customerMessageRte' => $customerMessageRte,
                'generalRequestRte' => $generalRequestRte,
                'orderRequestRte' => $orderRequestRte,
                'contactFlow' => $flow,
                'businessType' => $businessType,
                'assistantOrigin' => $assistantOrigin,
                'orderIntent' => $orderIntent,
                'assistantOrders' => $assistantOrders,
                'assistantProducts' => $assistantProducts,
                'selectedOrder' => $selectedOrder,
                'selectedOrderId' => $selectedOrderId,
                'isLogged' => $isLogged,
                'assistantBackUrl' => $assistantBackUrl,
                'shopPhone' => $shopPhone,
                'shopPhoneLink' => preg_replace('/[^0-9+]/', '', $shopPhone),
                'shopEmail' => CustomerServiceReplyService::getSenderEmail((int)$this->context->shop->id),
                'productWishTypes' => array_map(static function ($label) use ($customerServiceModule) {
                    return $customerServiceModule->l($label, 'customer_threads');
                }, \CrmModule\ProductWish::getTypeLabels()),
            ]
        );

        $this->setTemplate(_PS_THEME_DIR_.'contact-form.tpl');
    }

}
