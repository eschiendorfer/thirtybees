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
 * Class PageNotFoundControllerCore
 */
class PageNotFoundControllerCore extends FrontController
{
    /** @var string $php_self */
    public $php_self = 'pagenotfound';
    /** @var string $page_name */
    public $page_name = 'pagenotfound';
    /** @var bool $ssl */
    public $ssl = true;

    /**
     * Assign template vars related to page content
     *
     * @see FrontController::initContent()
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function initContent()
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $urlPath = parse_url($requestUri, PHP_URL_PATH);

        $mainImageExtensions = implode('|', ImageManager::getAllowedImageExtensions(false, true));

        if ($urlPath && preg_match('/\.('.$mainImageExtensions.')$/i', $urlPath)) {
            $requestUri = urldecode((string)$urlPath);
            $requestUri = preg_replace('#^'.preg_quote(Context::getContext()->shop->getBaseURI(), '#').'#i', '/', $requestUri);

            $this->context->cookie->disallowWriting();

            $imageType = null;
            $sourcePath = null;
            $imageExtension = '';
            $highDpi = false;

            $imageInfo = $this->getImageInfoFromUri($requestUri);
            if ($imageInfo) {
                $imageEntity = $imageInfo['imageEntity'];
                $idEntity = $imageInfo['id'];
                $highDpi = $imageInfo['highDpi'];
                $imageExtension = $imageInfo['extension'];
                $imageType = $this->getImageType($imageInfo['imageType'], $imageEntity['imageTypes']);

                // As products have a sophisticated image system with folder structure
                $subfolder = ($imageEntity['name'] == ImageEntity::ENTITY_TYPE_PRODUCTS) ? Image::getImgFolderStatic($idEntity) : '';

                if ($imageInfo['imageType'] !== '' && ! $imageType) {
                    $sendPath = '';
                    $sourcePath = null;
                } elseif ($imageType) {
                    $highDpiDim = $highDpi ? '2x' : '';
                    $sendPath = $imageEntity['path'] . $subfolder . $idEntity . '-' . $imageType->name . $highDpiDim . '.' . $imageExtension;
                } else {
                    $sendPath = $imageEntity['path'] . $subfolder . $idEntity . '.' . $imageExtension;
                    $sourcePath = ImageManager::getSourceImage($imageEntity['path'] . $subfolder, $idEntity);
                }
                if ($imageType) {
                    $sourcePath = ImageManager::getSourceImage($imageEntity['path'] . $subfolder, $idEntity);
                }

            } else {
                // Check if source file is actually requested, but in a missing extension
                $sendPath = ImageManager::tryRestoreImage(_PS_ROOT_DIR_ . '/' . ltrim($requestUri, '/'));
            }

            if ($sendPath) {

                if ($sourcePath && $imageExtension && !file_exists($sendPath) && file_exists($sourcePath)) {
                    if ($imageType) {
                        // Automatically generate image type

                        $scale = $highDpi && ImageManager::retinaSupport() ? 2 : 1;
                        $width = (int)$imageType->width * $scale;
                        $height = (int)$imageType->height * $scale;

                        ImageManager::resizeByMode(
                            $sourcePath,
                            $sendPath,
                            $width,
                            $height,
                            $imageExtension,
                            $imageType->resize_mode ?? ImageType::RESIZE_MODE_CONTAIN
                        );
                    } else {
                        // request to source image in different format
                        ImageManager::convertImageToExtension(
                            $sourcePath,
                            $imageExtension,
                            $sendPath
                        );

                    }
                }

                if (file_exists($sendPath)) {
                    $this->sendImage($sendPath);
                }
            }

            if ($imageInfo && !empty($imageEntity['fallback_image'])) {
                $fallbackBasePath = ImageManager::getFallbackImagePathByEntity($imageEntity['name'], null, false, $imageExtension);
                $fallbackSource = false;
                if ($fallbackBasePath !== '') {
                    $fallbackDirectory = dirname($fallbackBasePath) . '/';
                    $fallbackName = pathinfo($fallbackBasePath, PATHINFO_FILENAME);
                    $fallbackSource = ImageManager::getSourceImage($fallbackDirectory, $fallbackName, $imageExtension, false);
                }

                if ($fallbackSource) {
                    if ($imageType) {
                        $fallbackPath = ImageManager::getFallbackImagePathByEntity($imageEntity['name'], $imageType->name, $highDpi, $imageExtension);
                        if (!file_exists($fallbackPath)) {
                            $scale = $highDpi && ImageManager::retinaSupport() ? 2 : 1;
                            ImageManager::resizeByMode(
                                $fallbackSource,
                                $fallbackPath,
                                (int)$imageType->width * $scale,
                                (int)$imageType->height * $scale,
                                $imageExtension,
                                $imageType->resize_mode ?? ImageType::RESIZE_MODE_CONTAIN
                            );
                        }
                    } else {
                        $fallbackPath = $fallbackDirectory . $fallbackName . '.' . $imageExtension;
                        if (!file_exists($fallbackPath)) {
                            ImageManager::convertImageToExtension($fallbackSource, $imageExtension, $fallbackPath);
                        }
                    }

                    if (file_exists($fallbackPath)) {
                        $this->sendImage($fallbackPath);
                    }
                }
            }

            // We haven't found any image, we try to display the default image
            $imageTypeName = $imageType ? $imageType->name : '';
            if ($notFoundImage = $this->context->link->getDefaultImageUri($this->context->language->iso_code, $imageTypeName, $highDpi, '', true)) {
                $this->sendImage($notFoundImage);
            }

            // We haven't even found the default image (should never happen in theory)
            header('HTTP/1.1 404 Not Found');
            header('Status: 404 Not Found');
            header('Content-Type: image/gif');
            readfile(_PS_IMG_DIR_.'404.gif');
            exit;

        } elseif (in_array(mb_strtolower(substr($requestUri, -3)), ['.js', 'css'])) {
            $this->context->cookie->disallowWriting();
            exit;
        }

        header('HTTP/1.1 404 Not Found');
        header('Status: 404 Not Found');
        parent::initContent();

        $this->setTemplate(_PS_THEME_DIR_.'404.tpl');
    }

    /**
     * Canonical redirection
     *
     * @param string $canonicalUrl
     *
     * @deprecated 1.0.1
     */
    protected function canonicalRedirection($canonicalUrl = '')
    {
        // 404 - no need to redirect to the canonical url
    }

    /**
     * SSL redirection
     *
     * @deprecated 1.0.1
     */
    protected function sslRedirection()
    {
        // 404 - no need to redirect
    }

    /**
     * returns image extension
     *
     * @param string $ext
     * @return string
     */
    protected function normalizeImageExtension($ext)
    {
        $ext = strtolower((string)$ext);
        if ($ext === 'jpeg') {
            return 'jpg';
        }
        return $ext;
    }

    /**
     * @param string $requestUri
     *
     * @return array|false
     * @throws PrestaShopException
     */
    protected function getImageInfoFromUri(string $requestUri)
    {
        $extensions = implode('|', ImageManager::getAllowedImageExtensions());
        $imageEntitites = implode('|', array_keys(ImageEntity::getImageEntities()));

        $directImageInfo = $this->getImageInfoFromDirectPath($requestUri, $extensions);
        if ($directImageInfo) {
            return $directImageInfo;
        }

        $patternImageInfo = $this->getImageInfoFromPublicUrlPattern($requestUri, $extensions);
        if ($patternImageInfo) {
            return $patternImageInfo;
        }

        // try match image route: /entityType/id-imageType/name.ext
        // example: /products/100-home/candle.jpg
        //          /manufactures/5/manufacturer.jpg
        $imageRoute = '#^/(?P<imageEntity>'.$imageEntitites.'+)/(?P<id>[0-9]+)(-(?P<imageType>[a-zA-Z0-9_ -]+))?/(?P<name>.*)\.(?P<extension>'.$extensions.')$#u';
        if (preg_match($imageRoute, $requestUri, $matches)) {
            $name = (string)$matches['name'];
            $highDpi = false;
            if (str_ends_with($name, '2x')) {
                $name = substr($name, 0, -2);
                $highDpi = true;
            }
            return [
                'imageEntity' => ImageEntity::getImageEntityInfo($matches['imageEntity']),
                'id' => $matches['id'],
                'imageType' => $matches['imageType'] ?? '',
                'name' => $name,
                'extension' => $matches['extension'],
                'highDpi' => $highDpi,
            ];
        }

        // test legacy product image route -- without image entity section
        // example: /100-home/candle.jpg
        $legacyProductImageRoute = '#^/(?P<id>[0-9]+)(-(?P<imageType>[a-zA-Z0-9_ -]+))?/(?P<name>.*)\.(?P<extension>'.$extensions.')$#u';
        if (preg_match($legacyProductImageRoute, $requestUri, $matches)) {
            $name = (string)$matches['name'];
            $highDpi = false;
            if (str_ends_with($name, '2x')) {
                $name = substr($name, 0, -2);
                $highDpi = true;
            }
            return [
                'imageEntity' => ImageEntity::getImageEntityInfo('products'),
                'id' => $matches['id'],
                'imageType' => $matches['imageType'] ?? '',
                'name' => $name,
                'extension' => $matches['extension'],
                'highDpi' => $highDpi,
            ];
        }

        return false;
    }

    /**
     * Match direct /img/... URLs generated for custom image entities.
     *
     * @param string $requestUri
     * @param string $extensions
     *
     * @return array|false
     * @throws PrestaShopException
     */
    protected function getImageInfoFromDirectPath(string $requestUri, string $extensions)
    {
        $imageRoot = rtrim(str_replace('\\', '/', _PS_IMG_DIR_), '/') . '/';

        foreach (ImageEntity::getImageEntities() as $imageEntity) {
            $entityPath = rtrim(str_replace('\\', '/', (string)($imageEntity['path'] ?? '')), '/') . '/';
            if (!str_starts_with($entityPath, $imageRoot)) {
                continue;
            }

            $imageTypeNames = array_keys((array)($imageEntity['imageTypesByName'] ?? []));
            usort($imageTypeNames, static function ($first, $second) {
                return strlen($second) <=> strlen($first);
            });
            $imageTypePattern = $imageTypeNames
                ? '(?:-(?P<imageType>'.implode('|', array_map(static function ($name) {
                    return preg_quote((string)$name, '#');
                }, $imageTypeNames)).'))?'
                : '';

            $relativePath = substr($entityPath, strlen($imageRoot));
            $pattern = '#^/img/'.preg_quote($relativePath, '#').'(?P<id>[0-9]+)'.$imageTypePattern.'(?P<highDpi>2x)?\.(?P<extension>'.$extensions.')$#u';
            if (preg_match($pattern, $requestUri, $matches)) {
                return [
                    'imageEntity' => $imageEntity,
                    'id' => $matches['id'],
                    'imageType' => $matches['imageType'] ?? '',
                    'name' => $matches['id'],
                    'extension' => $matches['extension'],
                    'highDpi' => !empty($matches['highDpi']),
                ];
            }
        }

        return false;
    }

    /**
     * Send an image file and stop request processing.
     *
     * @param string $path
     *
     * @return never
     */
    protected function sendImage(string $path)
    {
        $imageExtension = pathinfo($path, PATHINFO_EXTENSION);
        $mimeType = Media::getFileInformations('images', $imageExtension)['mimeType'] ?? 'image/jpeg';
        header('HTTP/1.1 200 Found');
        header('Status: 200 Found');
        header('Content-Type: '.$mimeType);
        readfile($path);
        exit;
    }

    /**
     * Match custom public image URL patterns registered by image entities.
     *
     * @param string $requestUri
     * @param string $extensions
     *
     * @return array|false
     *
     * @throws PrestaShopException
     */
    protected function getImageInfoFromPublicUrlPattern(string $requestUri, string $extensions)
    {
        $requestPath = ltrim($requestUri, '/');

        foreach (ImageEntity::getImageEntities() as $imageEntity) {
            $pattern = trim((string)($imageEntity['public_url_pattern'] ?? ''), '/');
            if ($pattern === '' || !str_contains($pattern, '{id}')) {
                continue;
            }

            $regex = $this->buildPublicUrlPatternRegex($pattern, $extensions);
            if ($regex === '' || !preg_match($regex, $requestPath, $matches)) {
                continue;
            }

            $imageType = (string)($matches['imageType'] ?? '');
            if ($imageType === 'original') {
                $imageType = '';
            }

            return [
                'imageEntity' => $imageEntity,
                'id' => (int)$matches['id'],
                'imageType' => $imageType,
                'name' => (string)($matches['name'] ?? $matches['rewrite'] ?? ''),
                'extension' => $this->normalizeImageExtension((string)($matches['extension'] ?? pathinfo($requestPath, PATHINFO_EXTENSION))),
                'highDpi' => !empty($matches['highDpi']),
            ];
        }

        return false;
    }

    /**
     * @param string $pattern
     * @param string $extensions
     *
     * @return string
     */
    protected function buildPublicUrlPatternRegex(string $pattern, string $extensions): string
    {
        $placeholderRegex = [
            '{entity}' => '(?P<imageEntity>[a-zA-Z0-9_ -]+)',
            '{id}' => '(?P<id>[0-9]+)',
            '{type}' => '(?P<imageType>[a-zA-Z0-9_ -]+?)',
            '{rewrite}' => '(?P<rewrite>[^/]+)',
            '{ext}' => '(?P<extension>'.$extensions.')',
            '{high_dpi}' => '(?P<highDpi>2x)?',
        ];

        $regex = '';
        $offset = 0;
        if (!preg_match_all('/\{[a-z_]+\}/', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        foreach ($matches[0] as $match) {
            $placeholder = $match[0];
            $position = (int)$match[1];
            $regex .= preg_quote(substr($pattern, $offset, $position - $offset), '#');
            $regex .= $placeholderRegex[$placeholder] ?? preg_quote($placeholder, '#');
            $offset = $position + strlen($placeholder);
        }

        $regex .= preg_quote(substr($pattern, $offset), '#');

        return '#^'.$regex.'$#iu';
    }

    /**
     * @param string $imageTypeName
     * @param array $imageTypes
     *
     * @return ImageType|null
     * @throws PrestaShopException
     */
    protected function getImageType(string $imageTypeName, array $imageTypes): ?ImageType
    {
        if ($imageTypeName) {
            // find image type
            $formattedName = ImageType::getFormatedName($imageTypeName);
            foreach ($imageTypes as $imageType) {
                $name = ImageType::getFormatedName((string)($imageType['name'] ?? ''));
                $rewrite = ImageType::getFormatedName((string)($imageType['rewrite'] ?? ''));
                if ($formattedName && ($formattedName === $name || $formattedName === $rewrite) && ImageType::typeAlreadyExists($name)) {
                    return ImageType::getInstanceByName($name);
                }
            }
        }
        return null;
    }
}
