<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2018 thirty bees
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
 * @copyright 2017-2018 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class ImageEntityCore
 */
class ImageEntityCore extends ObjectModel
{
    const ENTITY_TYPE_PRODUCTS = 'products';
    const ENTITY_TYPE_CATEGORIES = 'categories';
    const ENTITY_TYPE_CATEGORIES_THUMB = 'categoriesthumb';
    const ENTITY_TYPE_MANUFACTURERS = 'manufacturers';
    const ENTITY_TYPE_SUPPLIERS = 'suppliers';
    const ENTITY_TYPE_SCENES = 'scenes';
    const ENTITY_TYPE_SCENES_THUMB = 'scenesthumb';
    const ENTITY_TYPE_STORES = 'stores';

    /**
     * @var string Name
     */
    public $id_image_entity;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Classname
     */
    public $classname;

    /**
     * @var string|string[]
     */
    public $display_name;

    /**
     * @var int Maximum source image width. Zero means unlimited.
     */
    public $source_max_width;

    /**
     * @var int Maximum source image height. Zero means unlimited.
     */
    public $source_max_height;

    /**
     * @var string Public URL pattern for this image entity. Empty means core default.
     */
    public $public_url_pattern;

    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'   => 'image_entity',
        'primary' => 'id_image_entity',
        'multilang' => true,
        'fields'  => [
            'name'              => ['type' => self::TYPE_STRING, 'validate' => 'isImageTypeName', 'required' => true, 'size' => 64],
            'classname'         => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 64],
            'source_max_width'  => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbType' => 'int(10) unsigned', 'dbDefault' => '0'],
            'source_max_height' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbType' => 'int(10) unsigned', 'dbDefault' => '0'],
            'public_url_pattern' => ['type' => self::TYPE_STRING, 'size' => 255, 'dbType' => 'varchar(255)', 'dbDefault' => ''],

            /* Lang fields */
            'display_name'      => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 128, 'lang' => true],
        ],
        'keys' => [
            'image_entity' => [
                'image_entity_name' => ['type' => ObjectModel::KEY, 'columns' => ['name']],
            ],
        ],
    ];

    /**
     * @var array Webservice parameters
     */
    protected $webserviceParameters = [
        'objectsNodeName' => 'image_entities',
        'objectNodeName'  => 'image_entity',
        'fields'          => [],
        'associations'    => [
            'image_types' => [
                'resource' => 'image_types',
                'fields'   => [
                    'id' => [],
                ],
            ],
        ],
    ];

    /**
     * @param string $classname This needs to be the classname like defined in AdminController (with namespace)
     * @param array $images The structure is defined ObjectModel $definition['images']
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function rebuildImageEntities($classname, $images)
    {
        // Adding images from themes
        foreach (Theme::getUsedThemes() as $theme) {
            $xml = $theme->loadConfigFile();

            foreach ($xml->images->image as $imageDefinition) {

                $width = (int)$imageDefinition['width'];
                $height = (int)$imageDefinition['height'];
                $name = $theme->name . '_' . $imageDefinition['name'];

                if ((string)$imageDefinition[static::ENTITY_TYPE_PRODUCTS] === 'true') {
                    $images[static::ENTITY_TYPE_PRODUCTS]['classname'] = 'Product';
                    $images[static::ENTITY_TYPE_PRODUCTS]['imageTypes'][] = [
                        'name' => $name,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
                if ((string)$imageDefinition[static::ENTITY_TYPE_CATEGORIES] === 'true') {
                    $images[static::ENTITY_TYPE_CATEGORIES]['classname'] = 'Category';
                    $images[static::ENTITY_TYPE_CATEGORIES]['imageTypes'][] = [
                        'name' => $name,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
                if ((string)$imageDefinition[static::ENTITY_TYPE_CATEGORIES_THUMB] === 'true') {
                    $images[static::ENTITY_TYPE_CATEGORIES_THUMB]['classname'] = 'Category';
                    $images[static::ENTITY_TYPE_CATEGORIES_THUMB]['imageTypes'][] = [
                        'name' => $name,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
                if ((string)$imageDefinition[static::ENTITY_TYPE_MANUFACTURERS] === 'true') {
                    $images[static::ENTITY_TYPE_MANUFACTURERS]['classname'] = 'Manufacturer';
                    $images[static::ENTITY_TYPE_MANUFACTURERS]['imageTypes'][] = [
                        'name' => $name,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
                if ((string)$imageDefinition[static::ENTITY_TYPE_SUPPLIERS] === 'true') {
                    $images[static::ENTITY_TYPE_SUPPLIERS]['classname'] = 'Supplier';
                    $images[static::ENTITY_TYPE_SUPPLIERS]['imageTypes'][] = [
                        'name' => $name,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
                if (Tab::getIdFromClassName('AdminScenes')) {
                    if ((string)$imageDefinition[static::ENTITY_TYPE_SCENES] === 'true') {
                        $images[static::ENTITY_TYPE_SCENES]['classname'] = 'Scene';
                        $images[static::ENTITY_TYPE_SCENES]['imageTypes'][] = [
                            'name' => $name,
                            'width' => $width,
                            'height' => $height,
                        ];
                    }
                    if ((string)$imageDefinition[static::ENTITY_TYPE_SCENES_THUMB] === 'true') {
                        $images[static::ENTITY_TYPE_SCENES_THUMB]['classname'] = 'Scene';
                        $images[static::ENTITY_TYPE_SCENES_THUMB]['imageTypes'][] = [
                            'name' => $name,
                            'width' => $width,
                            'height' => $height,
                        ];
                    }
                }
                if ((string)$imageDefinition[static::ENTITY_TYPE_STORES] === 'true') {
                    $images[static::ENTITY_TYPE_STORES]['classname'] = 'Store';
                    $images[static::ENTITY_TYPE_STORES]['imageTypes'][] = [
                        'name' => $name,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
            }
        }

        foreach ($images as $imageEntityName => $imageEntity) {

            $existingImageEntity = static::getImageEntityInfo($imageEntityName);
            $imageEntityId = isset($existingImageEntity['id_image_entity']) ? (int)$existingImageEntity['id_image_entity'] : 0;

            $imageEntityObj = new ImageEntity($imageEntityId);
            $imageEntityObj->name = $imageEntityName;
            $imageEntityObj->classname = $imageEntity['classname'] ?? $classname;
            $imageEntityObj->source_max_width = max(0, (int)($imageEntity['sourceMaxWidth'] ?? 0));
            $imageEntityObj->source_max_height = max(0, (int)($imageEntity['sourceMaxHeight'] ?? 0));
            $imageEntityObj->public_url_pattern = trim((string)($imageEntity['publicUrlPattern'] ?? $imageEntity['public_url_pattern'] ?? ''));
            $displayName = [];
            foreach (Language::getLanguages(false, false, true) as $langId) {
                if (isset($imageEntityObj->display_name[$langId]) && $imageEntityObj->display_name[$langId]) {
                    $displayName[$langId] = $imageEntityObj->display_name[$langId];
                }  else {
                    $displayName[$langId] = $imageEntity['displayName'] ?? ucfirst($imageEntityName);
                }
            }
            $imageEntityObj->display_name = $displayName;
            $imageEntityObj->save();
            $imageEntityId = (int)$imageEntityObj->id;

            if ($imageEntityId && !empty($imageEntity['imageTypes']) && is_array($imageEntity['imageTypes'])) {
                foreach ($imageEntity['imageTypes'] as $imageType) {

                    $imageTypeNameFormated = ImageType::getFormatedName($imageType['name']);
                    $imageTypeObj = ImageType::getInstanceByName($imageTypeNameFormated);
                    $width = (int)($imageType['width'] ?? 0);
                    $height = (int)($imageType['height'] ?? 0);
                    $resizeMode = ImageType::normalizeResizeMode($imageType['resize_mode'] ?? null);

                    // Adding missing image types
                    if (!$imageTypeObj->id) {
                        $imageTypeObj->name = $imageType['name'];
                        $imageTypeObj->width = $width;
                        $imageTypeObj->height = $height;
                        $imageTypeObj->resize_mode = $resizeMode;
                        $imageTypeObj->add();
                    } else {
                        $needsUpdate = false;
                        if ((int)$imageTypeObj->width !== $width) {
                            $imageTypeObj->width = $width;
                            $needsUpdate = true;
                        }
                        if ((int)$imageTypeObj->height !== $height) {
                            $imageTypeObj->height = $height;
                            $needsUpdate = true;
                        }
                        if (ImageType::normalizeResizeMode($imageTypeObj->resize_mode ?? null) !== $resizeMode) {
                            $imageTypeObj->resize_mode = $resizeMode;
                            $needsUpdate = true;
                        }
                        if ($needsUpdate) {
                            $imageTypeObj->update();
                        }
                    }

                    // Link imageType to imageEntity
                    if ($imageTypeObj->id) {
                        $imageEntityObj->associateImageType($imageTypeObj->id);
                    }
                }
            }
        }

        static::rebuildBasedOnOldTypes();

        ImageType::cleanCache();
        Cache::clean('ImageEntity::getImageEntities');
        Configuration::updateGlobalValue('TB_IMAGE_ENTITY_REBUILD_LAST', date('Y-m-d H:i:s'));
    }

    /**
     * Function to transform the old entity structure into table image_entity_type
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private static function rebuildBasedOnOldTypes()
    {
        // This function should only be executed once
        if (!Configuration::get('TB_IMAGE_ENTITY_REBUILD_LAST')) {

            $db = Db::getInstance();

            $query = new DbQuery();
            $query->select('*');
            $query->from('image_type');
            $imageTypes = $db->getArray($query);

            // Get ids_image_entity
            $ids_image_entity = [];

            foreach (static::getImageEntities() as $imageEntity) {
                $ids_image_entity[$imageEntity['name']] = $imageEntity['id_image_entity'];
            }

            $oldEntityTypes = static::getLegacyImageEntities();

            foreach ($imageTypes as $imageType) {
                foreach ($oldEntityTypes as $oldEntityType) {
                    if ($imageType[$oldEntityType] && isset($ids_image_entity[$oldEntityType])) {
                        $data = [
                            'id_image_entity' => $ids_image_entity[$oldEntityType],
                            'id_image_type' => $imageType['id_image_type']
                        ];
                        $db->insert('image_entity_type', $data, false, true, Db::REPLACE);
                    }
                }
            }
        }
    }

    /**
     * @return ImageEntity[]
     *
     * @throws PrestaShopException
     */
    public static function getAll()
    {
        $collection = new PrestaShopCollection('ImageEntity');
        return $collection->getResults();
    }

    /**
     * @param string $imageEntityName
     *
     * @return array|null
     *
     * @throws PrestaShopException
     */
    public static function getImageEntityInfo(string $imageEntityName)
    {
        if ($imageEntityName) {
            $entities = static::getImageEntities();
            if (isset($entities[$imageEntityName])) {
                return $entities[$imageEntityName];
            }
        }
        return null;
    }

    /**
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getImageEntities(): array
    {
        $langId = (int)Configuration::get('PS_LANG_DEFAULT');
        $cacheKey = 'ImageEntity::getImageEntities';
        if (! Cache::isStored($cacheKey)) {
            $query = new DbQuery();
            $query->select('ie.*');
            $query->select('l.display_name');
            $query->from(static::$definition['table'], 'ie');
            $query->select('it.id_image_type, it.name AS image_type, it.width, it.height, it.resize_mode, it.id_image_type_parent');
            $query->leftJoin('image_entity_type', 'iet', '(iet.id_image_entity = ie.id_image_entity)');
            $query->leftJoin('image_type', 'it', '(iet.id_image_type = it.id_image_type)');
            $query->leftJoin('image_entity_lang', 'l', '(l.id_image_entity = ie.id_image_entity AND l.id_lang = '.$langId.')');
            $query->orderBy('ie.name ASC');

            $result = Db::getInstance()->getArray($query);

            $imageEntities = [];

            foreach ($result as $res) {

                $name = $res['name'];

                if (!isset($imageEntities[$name])) {
                    // Get data from object model $definition
                    $className = $res['classname'];
                    $definition = ObjectModel::getDefinition($className);
                    $sourceMaxWidth = (int)($res['source_max_width'] ?? 0);
                    $sourceMaxHeight = (int)($res['source_max_height'] ?? 0);
                    if ($sourceMaxWidth <= 0) {
                        $sourceMaxWidth = max(0, (int)($definition['images'][$name]['sourceMaxWidth'] ?? 0));
                    }
                    if ($sourceMaxHeight <= 0) {
                        $sourceMaxHeight = max(0, (int)($definition['images'][$name]['sourceMaxHeight'] ?? 0));
                    }
                    $imageDefinition = $definition['images'][$name] ?? [];
                    $publicUrlPattern = trim((string)(
                        $imageDefinition['publicUrlPattern']
                        ?? $imageDefinition['public_url_pattern']
                        ?? ''
                    ));
                    $adminPreviewImageType = trim((string)(
                        $imageDefinition['adminPreviewImageType']
                        ?? $imageDefinition['admin_preview_image_type']
                        ?? ''
                    ));

                    $imageEntities[$name] = [
                        'table' => $definition['table'],
                        'primary' => $definition['primary'],
                        'path' => $imageDefinition['path'] ?? '',
                        'name' => $name,
                        'display_name' => $res['display_name'] ? $res['display_name'] : ucfirst($name),
                        'classname' => $className,
                        'id_image_entity' => (int)$res['id_image_entity'],
                        'source_max_width' => $sourceMaxWidth,
                        'source_max_height' => $sourceMaxHeight,
                        'sourceMaxWidth' => $sourceMaxWidth,
                        'sourceMaxHeight' => $sourceMaxHeight,
                        'public_url_pattern' => $publicUrlPattern,
                        'publicUrlPattern' => $publicUrlPattern,
                        'admin_preview_image_type' => $adminPreviewImageType,
                        'adminPreviewImageType' => $adminPreviewImageType,
                        'imageTypes' => [],
                        'imageTypesByName' => [],
                        'imageTypesByRewrite' => [],
                    ];
                }

                $imageTypeId = (int)$res['id_image_type'];
                if ($imageTypeId) {
                    $imageTypeName = (string)$res['image_type'];
                    $imageTypeDefinition = static::getImageTypeDefinition($definition['images'][$name]['imageTypes'] ?? [], $imageTypeName);
                    $imageTypeRewrite = trim((string)($imageTypeDefinition['rewrite'] ?? ''));
                    if ($imageTypeRewrite === '') {
                        $imageTypeRewrite = $imageTypeName;
                    }

                    $imageTypeInfo = [
                        'id_image_type' => $imageTypeId,
                        'name' => $imageTypeName,
                        'rewrite' => $imageTypeRewrite,
                        'width' => (int)$res['width'],
                        'height' => (int)$res['height'],
                        'resize_mode' => ImageType::normalizeResizeMode($res['resize_mode'] ?? null),
                        'id_image_type_parent' => (int)$res['id_image_type_parent'],
                    ];

                    $imageEntities[$name]['imageTypes'][] = $imageTypeInfo;
                    $imageEntities[$name]['imageTypesByName'][$imageTypeName] = $imageTypeInfo;
                    $imageEntities[$name]['imageTypesByRewrite'][$imageTypeRewrite] = $imageTypeInfo;
                }
            }

            Cache::store($cacheKey, $imageEntities);
            return $imageEntities;
        }

        return Cache::retrieve($cacheKey);
    }

    /**
     * @param array<int, array<string, mixed>> $imageTypes
     * @param string $imageTypeName
     *
     * @return array<string, mixed>
     */
    protected static function getImageTypeDefinition(array $imageTypes, string $imageTypeName): array
    {
        $imageTypeName = ImageType::getFormatedName($imageTypeName);
        foreach ($imageTypes as $imageType) {
            if (ImageType::getFormatedName((string)($imageType['name'] ?? '')) === $imageTypeName) {
                return $imageType;
            }
        }

        return [];
    }

    /**
     * Method associates ImageTypes records with this ImageEntity object
     *
     * @param int[] $imageTypeIds ids of image types
     * @param bool $deleteExisting if true, existing associations will be deleted first
     *
     * @throws PrestaShopException
     */
    public function associateImageTypes(array $imageTypeIds, $deleteExisting = false)
    {
        $imageEntityId = (int)$this->id;
        if ($imageEntityId) {
            $conn = Db::getInstance();
            if ($deleteExisting) {
                $conn->delete('image_entity_type', "id_image_entity = $imageEntityId");

                // BC: keep legacy properties in tb_image_type synchronized
                if (in_array($this->name, static::getLegacyImageEntities())) {
                    $conn->update('image_type', [ $this->name => 0]);
                }
            }

            foreach ($imageTypeIds as $imageTypeId) {
                $this->associateImageType((int)$imageTypeId);
            }
        }
    }

    /**
     * @param int $imageTypeId
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    public function associateImageType(int $imageTypeId)
    {
        $imageEntityId = (int)$this->id;
        $imageTypeId = (int)$imageTypeId;
        if ($imageEntityId && $imageTypeId) {
            $conn = Db::getInstance();
            $conn->insert('image_entity_type', [
                'id_image_entity' => $imageEntityId,
                'id_image_type' => $imageTypeId,
            ], false, true, Db::INSERT_IGNORE);

            // BC: keep legacy properties in tb_image_type synchronized
            if (in_array($this->name, static::getLegacyImageEntities())) {
                $conn->update('image_type', [ $this->name => 1 ], 'id_image_type = ' . $imageTypeId);
            }
            ImageType::cleanCache();
        }
    }

    /**
     * @param bool $autoDate
     * @param bool $nullValues
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function add($autoDate = true, $nullValues = false)
    {
        $res = parent::add($autoDate, $nullValues);
        ImageType::cleanCache();
        return $res;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getWsImageTypes()
    {
        $result = [];
        $info = static::getImageEntityInfo($this->name);
        if ($info) {
            foreach ($info['imageTypes'] as $type) {
                $result[] = [
                    'id' => (int)$type['id_image_type']
                ];
            }
        }
        return $result;
    }

    /**
     * @return string[]
     */
    public static function getLegacyImageEntities()
    {
        return [
            static::ENTITY_TYPE_PRODUCTS,
            static::ENTITY_TYPE_CATEGORIES,
            static::ENTITY_TYPE_MANUFACTURERS,
            static::ENTITY_TYPE_SUPPLIERS,
            static::ENTITY_TYPE_SCENES,
            static::ENTITY_TYPE_STORES,
        ];
    }
}
