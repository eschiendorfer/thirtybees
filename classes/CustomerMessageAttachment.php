<?php
/**
 * Copyright (C) 2026 Emanuel Schiendorfer
 *
 * @author Emanuel Schiendorfer
 * @license All rights reserved.
 */

/**
 * Attachment belonging to a customer service message.
 */
class CustomerMessageAttachmentCore extends ObjectModel
{
    const DEFAULT_MAX_ATTACHMENTS = 10;
    const DEFAULT_MAX_TOTAL_SIZE_MB = 100;
    const DEFAULT_MAX_EMAIL_TOTAL_SIZE_MB = 15;
    const IMAGE_MAX_WIDTH = 1400;
    const IMAGE_MAX_HEIGHT = 1000;
    const THUMBNAIL_MAX_WIDTH = 150;
    const THUMBNAIL_MAX_HEIGHT = 150;

    /** @var int */
    public $id_customer_message;

    /** @var int */
    public $id_customer;

    /** @var int */
    public $id_visitor;

    /** @var int */
    public $id_employee;

    /** @var string */
    public $file_name;

    /** @var string */
    public $mime_type;

    /** @var int */
    public $file_size;

    /** @var int */
    public $upload_size;

    /** @var string */
    public $date_add;

    public static $definition = [
        'table'   => 'customer_message_attachment',
        'primary' => 'id_customer_message_attachment',
        'fields'  => [
            'id_customer_message' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'id_customer'         => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'id_visitor'          => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'id_employee'         => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'file_name'           => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 180],
            'mime_type'           => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 128],
            'file_size'           => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbDefault' => '0'],
            'upload_size'         => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbDefault' => '0'],
            'date_add'            => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'customer_message_attachment' => [
                'id_customer_message' => ['type' => ObjectModel::KEY, 'columns' => ['id_customer_message']],
                'id_customer'         => ['type' => ObjectModel::KEY, 'columns' => ['id_customer']],
                'id_visitor'          => ['type' => ObjectModel::KEY, 'columns' => ['id_visitor']],
                'id_employee'         => ['type' => ObjectModel::KEY, 'columns' => ['id_employee']],
                'pending'             => ['type' => ObjectModel::KEY, 'columns' => ['id_customer_message', 'date_add']],
            ],
        ],
    ];

    /**
     * @return string
     */
    public static function getStorageDirectory()
    {
        return rtrim(_PS_DOWNLOAD_DIR_, '/\\') . DIRECTORY_SEPARATOR . 'customer_message' . DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    public function getFullFileName()
    {
        return (int) $this->id . '-' . basename((string) $this->file_name);
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return static::getStorageDirectory() . $this->getFullFileName();
    }

    /**
     * @return string
     */
    public function getThumbnailFilePath()
    {
        return static::getStorageDirectory() . 'thumbnail' . DIRECTORY_SEPARATOR . (int) $this->id . '.webp';
    }

    /**
     * @return bool
     */
    public function fileExists()
    {
        return is_file($this->getFilePath());
    }

    /**
     * @return bool
     */
    public function thumbnailExists()
    {
        return $this->isImage() && is_file($this->getThumbnailFilePath());
    }

    /**
     * @return bool
     */
    public function isImage()
    {
        return strpos((string) $this->mime_type, 'image/') === 0;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function delete()
    {
        $path = $this->getFilePath();
        $thumbnailPath = $this->getThumbnailFilePath();
        $deleted = parent::delete();
        if ($deleted && is_file($path)) {
            @unlink($path);
        }
        if ($deleted && is_file($thumbnailPath)) {
            @unlink($thumbnailPath);
        }

        return $deleted;
    }

    /**
     * @param int[] $messageIds
     *
     * @return array<int, array<int, array>>
     * @throws PrestaShopDatabaseException
     */
    public static function getByMessageIds(array $messageIds)
    {
        $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        $attachments = [];
        if (!$messageIds) {
            return $attachments;
        }

        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('*')
                ->from(static::$definition['table'])
                ->where('`id_customer_message` IN (' . implode(',', $messageIds) . ')')
                ->orderBy('`id_customer_message`, `id_customer_message_attachment`')
        );

        foreach ($rows as $row) {
            $idAttachment = (int) $row['id_customer_message_attachment'];
            $row['full_file_name'] = $idAttachment . '-' . basename($row['file_name']);
            $row['is_image'] = strpos((string) $row['mime_type'], 'image/') === 0;
            $row['has_thumbnail'] = $row['is_image'] && is_file(
                static::getStorageDirectory() . 'thumbnail' . DIRECTORY_SEPARATOR . $idAttachment . '.webp'
            );
            $attachments[(int) $row['id_customer_message']][] = $row;
        }

        return $attachments;
    }

    /**
     * Add every attachment to its message without reducing the result to one row.
     *
     * @param array $messages
     *
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function appendToMessages(array $messages)
    {
        $messageIds = [];
        foreach ($messages as $message) {
            if (!empty($message['id_customer_message'])) {
                $messageIds[] = (int) $message['id_customer_message'];
            }
        }
        $attachments = static::getByMessageIds($messageIds);

        foreach ($messages as &$message) {
            $idMessage = (int) ($message['id_customer_message'] ?? 0);
            $message['attachments'] = $attachments[$idMessage] ?? [];
        }
        unset($message);

        return $messages;
    }

    /**
     * Build the database and filesystem part of an attachment deletion before
     * deleting its message. The files are removed only after the caller commits.
     *
     * @param int[] $messageIds
     *
     * @return array{ids: int[], paths: string[]}
     * @throws PrestaShopDatabaseException
     */
    public static function prepareDeletionByMessageIds(array $messageIds)
    {
        $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        $plan = ['ids' => [], 'paths' => []];
        if (!$messageIds) {
            return $plan;
        }

        /*
         * Todo: Replace this manual database/filesystem deletion plan with a centralized
         * attachment lifecycle that removes files only after a successful transaction commit.
         */
        $rows = Db::getInstance()->getArray(
            (new DbQuery())
                ->select('`id_customer_message_attachment`, `file_name`')
                ->from(static::$definition['table'])
                ->where('`id_customer_message` IN ('.implode(',', $messageIds).')')
                ->orderBy('`id_customer_message_attachment`')
        );
        foreach ($rows as $row) {
            $idAttachment = (int) $row['id_customer_message_attachment'];
            $plan['ids'][] = $idAttachment;
            $plan['paths'][] = static::getStorageDirectory()
                .$idAttachment.'-'.basename((string) $row['file_name']);
            $plan['paths'][] = static::getStorageDirectory()
                .'thumbnail'.DIRECTORY_SEPARATOR.$idAttachment.'.webp';
        }

        return $plan;
    }

    /**
     * Delete the database rows in a caller-owned transaction.
     *
     * @param array{ids: int[], paths: string[]} $plan
     *
     * @return bool
     */
    public static function deletePreparedRecords(array $plan)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $plan['ids'] ?? []))));

        return !$ids || Db::getInstance()->delete(
            static::$definition['table'],
            '`id_customer_message_attachment` IN ('.implode(',', $ids).')'
        );
    }

    /**
     * Remove files after the corresponding database transaction committed.
     *
     * @param array{ids: int[], paths: string[]} $plan
     */
    public static function deletePreparedFiles(array $plan)
    {
        foreach (array_unique($plan['paths'] ?? []) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Normalize PHP's single and multiple upload shapes.
     *
     * @param string $inputName
     *
     * @return array
     */
    public static function getUploadedFiles($inputName)
    {
        if (empty($_FILES[$inputName]) || !isset($_FILES[$inputName]['name'])) {
            return [];
        }

        $upload = $_FILES[$inputName];
        if (!is_array($upload['name'])) {
            return empty($upload['name']) || (int) $upload['error'] === UPLOAD_ERR_NO_FILE ? [] : [$upload];
        }

        $files = [];
        foreach ($upload['name'] as $index => $name) {
            if ($name === '' || (int) ($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => $name,
                'type'     => $upload['type'][$index] ?? '',
                'tmp_name' => $upload['tmp_name'][$index] ?? '',
                'error'    => (int) ($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($upload['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

    /**
     * @param array $files
     * @param bool $backOffice
     * @param int $existingCount
     * @param int $existingSize
     *
     * @return string[]
     */
    public static function validateUploadedFiles(array $files, $backOffice = false, $existingCount = 0, $existingSize = 0)
    {
        $errors = [];
        $maxCount = static::getMaximumAttachmentCount();
        $maxTotalBytes = static::getMaximumTotalBytes();

        if ($files && !$backOffice && !Configuration::get(Configuration::CUSTOMER_SERVICE_FILE_UPLOAD)) {
            return [Tools::displayError('File uploads are disabled.')];
        }

        if ((int) $existingCount + count($files) > $maxCount) {
            $errors[] = sprintf(Tools::displayError('A maximum of %d attachments is allowed per message.'), $maxCount);
        }

        $totalSize = (int) $existingSize;
        foreach ($files as $file) {
            $name = (string) ($file['name'] ?? '');
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = sprintf(Tools::displayError('The file "%s" could not be uploaded.'), $name);
                continue;
            }

            $size = (int) ($file['size'] ?? 0);
            $totalSize += $size;
            if ($size <= 0) {
                $errors[] = sprintf(Tools::displayError('The file "%s" is empty.'), $name);
                continue;
            }

            $fileInfo = static::getAllowedFileInformation($name, (bool) $backOffice);
            if (!$fileInfo) {
                $errors[] = sprintf(Tools::displayError('The file type of "%s" is not allowed.'), $name);
                continue;
            }

            $mimeType = static::detectMimeType((string) ($file['tmp_name'] ?? ''));
            $allowedMimeTypes = $fileInfo['mimeTypes'] ?? [$fileInfo['mimeType']];
            if ($mimeType === '' || !in_array($mimeType, $allowedMimeTypes, true)) {
                $errors[] = sprintf(Tools::displayError('The content of "%s" does not match its file type.'), $name);
            }
        }

        if ($totalSize > $maxTotalBytes) {
            $errors[] = sprintf(Tools::displayError('The attachments exceed the maximum total size of %d MB.'), static::getMaximumTotalSizeMb());
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param array $files
     * @param int $idCustomerMessage
     * @param int $idCustomer
     * @param int $idVisitor
     * @param int $idEmployee
     *
     * @return CustomerMessageAttachment[]
     * @throws PrestaShopException
     */
    public static function storeUploadedFiles(array $files, $idCustomerMessage, $idCustomer = 0, $idVisitor = 0, $idEmployee = 0)
    {
        if (!$files) {
            return [];
        }

        static::ensureStorageDirectory();
        $stored = [];
        try {
            foreach ($files as $file) {
                $fileInformation = static::getAllowedFileInformation((string) $file['name'], true);
                if (!$fileInformation) {
                    throw new PrestaShopException('The customer message attachment type is not allowed.');
                }

                $isImage = !empty($fileInformation['imageSupport']);
                $fileName = Tools::generateFileName((string) $file['name']);
                if ($isImage) {
                    if (!ImageManager::serverSupportsWebp()) {
                        throw new PrestaShopException('WebP image conversion is not available.');
                    }
                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.webp';
                }

                $attachment = new static();
                $attachment->id_customer_message = (int) $idCustomerMessage;
                $attachment->id_customer = (int) $idCustomer;
                $attachment->id_visitor = (int) $idVisitor;
                $attachment->id_employee = (int) $idEmployee;
                $attachment->file_name = $fileName;
                $attachment->mime_type = $isImage ? 'image/webp' : static::detectMimeType((string) $file['tmp_name']);
                $attachment->file_size = 0;
                $attachment->upload_size = (int) $file['size'];
                if (!$attachment->add()) {
                    throw new PrestaShopException('Could not save customer message attachment.');
                }
                $stored[] = $attachment;

                $source = (string) $file['tmp_name'];
                $target = $attachment->getFilePath();
                if ($isImage) {
                    $imageError = 0;
                    $storedSuccessfully = ImageManager::saveSourceImage(
                        $source,
                        $target,
                        static::IMAGE_MAX_WIDTH,
                        static::IMAGE_MAX_HEIGHT,
                        'webp',
                        $imageError
                    ) && ImageManager::saveSourceImage(
                        $source,
                        $attachment->getThumbnailFilePath(),
                        static::THUMBNAIL_MAX_WIDTH,
                        static::THUMBNAIL_MAX_HEIGHT,
                        'webp',
                        $imageError
                    );
                    if ($storedSuccessfully) {
                        @unlink($source);
                        @chmod($attachment->getThumbnailFilePath(), 0640);
                    }
                } else {
                    $storedSuccessfully = is_uploaded_file($source)
                        ? move_uploaded_file($source, $target)
                        : @rename($source, $target);
                }
                if (!$storedSuccessfully || !is_file($target)) {
                    throw new PrestaShopException('Could not store customer message attachment.');
                }
                @chmod($target, 0640);
                $attachment->file_size = (int) filesize($target);
                if (!$attachment->update()) {
                    throw new PrestaShopException('Could not update customer message attachment.');
                }
            }
        } catch (Exception $exception) {
            foreach ($stored as $attachment) {
                $attachment->delete();
            }
            throw $exception;
        }

        return $stored;
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     *
     * @return array|null
     */
    public static function buildMailAttachments(array $attachments)
    {
        if (!static::canAttachToEmail($attachments)) {
            return null;
        }

        $mailAttachments = [];
        foreach ($attachments as $attachment) {
            if (!$attachment instanceof CustomerMessageAttachment || !$attachment->fileExists()) {
                continue;
            }
            $mailAttachments[] = [
                'name'    => $attachment->getFullFileName(),
                'mime'    => $attachment->mime_type,
                'content' => file_get_contents($attachment->getFilePath()),
            ];
        }

        return $mailAttachments ?: null;
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     *
     * @return bool
     */
    public static function canAttachToEmail(array $attachments)
    {
        return static::getTotalSize($attachments) <= static::getMaximumEmailTotalBytes();
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     * @param int $idCustomerMessage
     *
     * @return bool
     */
    public static function assignToMessage(array $attachments, $idCustomerMessage)
    {
        foreach ($attachments as $attachment) {
            if (!$attachment instanceof CustomerMessageAttachment || (int) $attachment->id_customer_message !== 0) {
                return false;
            }
            $attachment->id_customer_message = (int) $idCustomerMessage;
            if (!$attachment->update()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Load every submitted pending attachment and verify its owner.
     *
     * @param array $attachmentIds
     * @param int $idCustomer
     * @param int $idVisitor
     * @param int $idEmployee
     *
     * @return CustomerMessageAttachment[]|false
     * @throws PrestaShopException
     */
    public static function getOwnedPending(array $attachmentIds, $idCustomer = 0, $idVisitor = 0, $idEmployee = 0)
    {
        $attachmentIds = array_values(array_unique(array_filter(array_map('intval', $attachmentIds))));
        if (!$attachmentIds) {
            return [];
        }

        // Pending uploads are read immediately after creation, so a replica may still be stale.
        $rows = Db::getInstance()->getArray(
            (new DbQuery())
                ->select('`id_customer_message_attachment`')
                ->from(static::$definition['table'])
                ->where('`id_customer_message_attachment` IN (' . implode(',', $attachmentIds) . ')')
                ->where('`id_customer_message` = 0')
                ->orderBy('`id_customer_message_attachment`')
        );
        if (count($rows) !== count($attachmentIds)) {
            return false;
        }

        $attachments = [];
        foreach ($rows as $row) {
            $attachment = new static((int) $row['id_customer_message_attachment']);
            if (!Validate::isLoadedObject($attachment)) {
                return false;
            }
            $ownerMatches = (
                (int) $idEmployee > 0
                && (int) $attachment->id_employee === (int) $idEmployee
            ) || (
                (int) $idCustomer > 0
                && (int) $attachment->id_customer === (int) $idCustomer
            ) || (
                (int) $idVisitor > 0
                && (int) $attachment->id_visitor === (int) $idVisitor
            );
            if (!$ownerMatches) {
                return false;
            }
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * @param int $idCustomer
     * @param int $idVisitor
     * @param int $idEmployee
     *
     * @return array{count: int, upload_size: int}
     */
    public static function getPendingUsage($idCustomer = 0, $idVisitor = 0, $idEmployee = 0)
    {
        if ((int) $idEmployee > 0) {
            $ownerCondition = '`id_employee` = '.(int) $idEmployee;
        } elseif ((int) $idCustomer > 0) {
            $ownerCondition = '`id_customer` = '.(int) $idCustomer;
        } elseif ((int) $idVisitor > 0) {
            $ownerCondition = '`id_visitor` = '.(int) $idVisitor;
        } else {
            return ['count' => 0, 'upload_size' => 0];
        }

        // Count all server-side pending files: submitted attachment IDs are client-controlled
        // and must not allow abandoned uploads to bypass the count or total-size limit.
        $usage = Db::getInstance()->getRow(
            (new DbQuery())
                ->select('COUNT(*) AS `attachment_count`, COALESCE(SUM(`upload_size`), 0) AS `upload_size`')
                ->from(static::$definition['table'])
                ->where('`id_customer_message` = 0')
                ->where($ownerCondition)
        );

        return [
            'count' => (int) ($usage['attachment_count'] ?? 0),
            'upload_size' => (int) ($usage['upload_size'] ?? 0),
        ];
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     *
     * @return int
     */
    public static function getTotalSize(array $attachments)
    {
        $size = 0;
        foreach ($attachments as $attachment) {
            if ($attachment instanceof CustomerMessageAttachment) {
                $size += (int) $attachment->file_size;
            }
        }

        return $size;
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     *
     * @return int
     */
    public static function getTotalUploadSize(array $attachments)
    {
        $size = 0;
        foreach ($attachments as $attachment) {
            if ($attachment instanceof CustomerMessageAttachment) {
                $size += (int) $attachment->upload_size;
            }
        }

        return $size;
    }

    /**
     * @param CustomerMessageAttachment[] $attachments
     *
     * @return int[]
     */
    public static function getIds(array $attachments)
    {
        $ids = [];
        foreach ($attachments as $attachment) {
            if ($attachment instanceof CustomerMessageAttachment) {
                $ids[] = (int) $attachment->id;
            }
        }

        return $ids;
    }

    /**
     * @return int
     * @throws PrestaShopException
     */
    public static function deleteExpiredPending()
    {
        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_customer_message_attachment`')
                ->from(static::$definition['table'])
                ->where('`id_customer_message` = 0')
                ->where('`date_add` < DATE_SUB(NOW(), INTERVAL 24 HOUR)')
                ->orderBy('`id_customer_message_attachment`')
        );

        $deleted = 0;
        foreach ($rows as $row) {
            $attachment = new static((int) $row['id_customer_message_attachment']);
            if (Validate::isLoadedObject($attachment) && $attachment->delete()) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    /** @return int */
    public static function getMaximumAttachmentCount()
    {
        $value = (int) Configuration::get(Configuration::CUSTOMER_SERVICE_MAX_ATTACHMENTS);
        return $value > 0 ? $value : static::DEFAULT_MAX_ATTACHMENTS;
    }

    /** @return int */
    public static function getMaximumTotalSizeMb()
    {
        $value = (int) Configuration::get(Configuration::CUSTOMER_SERVICE_MAX_ATTACHMENTS_TOTAL_SIZE);
        return $value > 0 ? $value : static::DEFAULT_MAX_TOTAL_SIZE_MB;
    }

    /** @return int */
    public static function getMaximumEmailTotalSizeMb()
    {
        $value = (int) Configuration::get(Configuration::CUSTOMER_SERVICE_MAX_EMAIL_ATTACHMENTS_TOTAL_SIZE);
        return $value > 0 ? $value : static::DEFAULT_MAX_EMAIL_TOTAL_SIZE_MB;
    }

    /** @return int */
    public static function getMaximumTotalBytes()
    {
        return static::getMaximumTotalSizeMb() * 1024 * 1024;
    }

    /** @return int */
    public static function getMaximumEmailTotalBytes()
    {
        return static::getMaximumEmailTotalSizeMb() * 1024 * 1024;
    }

    /**
     * Return the extensions accepted by the customer-message upload rules.
     *
     * @param bool $backOffice
     *
     * @return string[]
     */
    public static function getAllowedExtensions($backOffice = false)
    {
        $uploadFlag = $backOffice ? 'uploadBackOffice' : 'uploadFrontOffice';
        $extensions = [];
        foreach (Media::getFileInformations() as $type) {
            foreach ($type as $information) {
                if (empty($information[$uploadFlag])) {
                    continue;
                }
                foreach ((array) ($information['extensions'] ?? []) as $extension) {
                    $extension = mb_strtolower((string) $extension, 'UTF-8');
                    if ($extension !== '') {
                        $extensions[$extension] = $extension;
                    }
                }
            }
        }

        return array_values($extensions);
    }

    /**
     * @param string $fileName
     * @param bool $backOffice
     *
     * @return array|false
     */
    protected static function getAllowedFileInformation($fileName, $backOffice)
    {
        $extension = mb_strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION), 'UTF-8');
        $uploadFlag = $backOffice ? 'uploadBackOffice' : 'uploadFrontOffice';
        foreach (Media::getFileInformations() as $type) {
            foreach ($type as $information) {
                if (!empty($information[$uploadFlag]) && in_array($extension, $information['extensions'], true)) {
                    return $information;
                }
            }
        }

        return false;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected static function detectMimeType($path)
    {
        if (!is_file($path)) {
            return '';
        }
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            return (string) $finfo->file($path);
        }
        if (function_exists('mime_content_type')) {
            return (string) mime_content_type($path);
        }

        return '';
    }

    /**
     * @return void
     * @throws PrestaShopException
     */
    protected static function ensureStorageDirectory()
    {
        $directories = [
            static::getStorageDirectory(),
            static::getStorageDirectory() . 'thumbnail' . DIRECTORY_SEPARATOR,
        ];
        foreach ($directories as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new PrestaShopException('Could not create customer message attachment directory.');
            }
        }
    }
}
