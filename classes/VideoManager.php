<?php
/**
 * Minimal synchronous video processing for ObjectModel video entities.
 */
class VideoManagerCore
{
    public const EXTENSION = 'mp4';

    public const RATIO_LANDSCAPE = '16:9';
    public const RATIO_SQUARE = '1:1';
    public const RATIO_PORTRAIT = '9:16';

    /**
     * @return array|null
     *
     * @throws PrestaShopException
     */
    public static function getVideoEntityInfo($className, $videoEntityName)
    {
        $definition = ObjectModel::getDefinition((string)$className);
        $videoDefinition = $definition['videos'][(string)$videoEntityName] ?? null;

        if (!is_array($videoDefinition)) {
            return null;
        }

        $videoDefinition['name'] = (string)$videoEntityName;
        $videoDefinition['classname'] = (string)$className;
        $aspectRatios = $videoDefinition['aspectRatios'] ?? null;
        if (!is_array($aspectRatios) || !$aspectRatios) {
            throw new PrestaShopException('At least one video aspect ratio must be configured.');
        }
        $supportedAspectRatios = [
            static::RATIO_LANDSCAPE,
            static::RATIO_SQUARE,
            static::RATIO_PORTRAIT,
        ];
        $normalizedAspectRatios = [];
        foreach ($aspectRatios as $aspectRatio) {
            $aspectRatio = trim((string)$aspectRatio);
            if (!in_array($aspectRatio, $supportedAspectRatios, true)) {
                throw new PrestaShopException('Unsupported video aspect ratio "'.$aspectRatio.'".');
            }
            $normalizedAspectRatios[] = $aspectRatio;
        }
        $videoDefinition['aspectRatios'] = array_values(array_unique($normalizedAspectRatios));

        return $videoDefinition;
    }

    /**
     * @return string
     */
    public static function getNameByInputName(array $fieldVideoSettings, $inputName)
    {
        $inputName = (string)$inputName;
        if ($inputName === '') {
            return '';
        }

        foreach ($fieldVideoSettings as $videoEntityName => $videoDefinition) {
            if (is_string($videoEntityName)
                && is_array($videoDefinition)
                && (string)($videoDefinition['inputName'] ?? '') === $inputName
            ) {
                return $videoEntityName;
            }
        }

        return '';
    }

    /**
     * @param int|string $error
     *
     * @return bool
     */
    public static function uploadVideoByEntity($className, $videoEntityName, $idEntity, array $file, &$error = 0)
    {
        $error = 0;
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $error = Tools::decodeUploadError($uploadError);
            return false;
        }

        $sourcePath = (string)($file['tmp_name'] ?? '');
        if ($sourcePath === '' || !is_uploaded_file($sourcePath)) {
            $error = Tools::displayError('Invalid video upload payload.');
            return false;
        }

        try {
            $videoDefinition = static::getVideoEntityInfo($className, $videoEntityName);
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
            return false;
        }
        if (!$videoDefinition) {
            $error = Tools::displayError('Unknown video entity.');
            return false;
        }

        $fileSize = (int)@filesize($sourcePath);
        $maxFileSize = max(0, (int)($videoDefinition['maxFileSize'] ?? 0));
        if ($fileSize <= 0 || ($maxFileSize > 0 && $fileSize > $maxFileSize)) {
            $error = $maxFileSize > 0
                ? sprintf(Tools::displayError('The video exceeds the maximum allowed size of %s MB.'), round($maxFileSize / 1048576, 1))
                : Tools::displayError('The uploaded video is empty.');
            return false;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'tb-video-');
        if (!$temporaryPath || !move_uploaded_file($sourcePath, $temporaryPath)) {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
            $error = Tools::displayError('The uploaded video could not be stored temporarily.');
            return false;
        }

        try {
            return static::saveSourceVideoByEntity($className, $videoEntityName, $idEntity, $temporaryPath, $error);
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @param int|string $error
     *
     * @return bool
     */
    public static function saveSourceVideoByEntity($className, $videoEntityName, $idEntity, $sourcePath, &$error = 0)
    {
        $error = 0;
        $idEntity = (int)$idEntity;
        $sourcePath = (string)$sourcePath;
        if ($idEntity <= 0 || !is_file($sourcePath)) {
            $error = Tools::displayError('The source video does not exist.');
            return false;
        }

        try {
            $videoDefinition = static::getVideoEntityInfo($className, $videoEntityName);
            if (!$videoDefinition) {
                $error = Tools::displayError('Unknown video entity.');
                return false;
            }

            $fileSize = (int)@filesize($sourcePath);
            $maxFileSize = max(0, (int)($videoDefinition['maxFileSize'] ?? 0));
            if ($fileSize <= 0 || ($maxFileSize > 0 && $fileSize > $maxFileSize)) {
                $error = $maxFileSize > 0
                    ? sprintf(Tools::displayError('The video exceeds the maximum allowed size of %s MB.'), round($maxFileSize / 1048576, 1))
                    : Tools::displayError('The source video is empty.');
                return false;
            }

            // Todo: Move video probing, conversion, and poster generation to an asynchronous background job.
            $probe = static::probe($sourcePath, $videoDefinition);
            if (!$probe['success']) {
                $error = $probe['error'];
                return false;
            }

            $metadata = $probe['metadata'];
            $maxDuration = max(0, (float)($videoDefinition['maxDuration'] ?? 0));
            if ($maxDuration > 0 && $metadata['duration'] > $maxDuration) {
                $error = sprintf(Tools::displayError('The video may not be longer than %s seconds.'), (int)$maxDuration);
                return false;
            }

            $directory = static::getEntityDirectory($videoDefinition);
            if ($directory === '' || !static::ensureDirectory($directory)) {
                $error = Tools::displayError('The video directory could not be created.');
                return false;
            }

            $temporaryBase = tempnam($directory, 'video-');
            if (!$temporaryBase) {
                $error = Tools::displayError('A temporary video file could not be created.');
                return false;
            }
            @unlink($temporaryBase);
            $posterExtension = static::getPosterExtension();
            $temporaryVideo = $temporaryBase.'.'.static::EXTENSION;
            $temporaryPosterFrame = $temporaryBase.'-poster-source.png';
            $temporaryPoster = $temporaryBase.'-poster.'.$posterExtension;

            try {
                $output = static::buildOutputSettings($metadata, $videoDefinition);
                $command = static::buildVideoCommand($sourcePath, $temporaryVideo, $metadata, $output, $videoDefinition);
                $process = static::runProcess($command, (float)($videoDefinition['timeout'] ?? 90));
                if (!$process['success'] || !is_file($temporaryVideo) || filesize($temporaryVideo) <= 0) {
                    $error = $process['error'] ?: Tools::displayError('The video could not be converted.');
                    return false;
                }

                $posterTime = max(0, (float)($videoDefinition['posterTime'] ?? 1));
                if ($metadata['duration'] > 0) {
                    $posterTime = min($posterTime, max(0, $metadata['duration'] - 0.1));
                }
                $posterCommand = [
                    'ffmpeg',
                    '-nostdin',
                    '-hide_banner',
                    '-loglevel',
                    'error',
                    '-ss',
                    static::formatDecimal($posterTime),
                    '-i',
                    $temporaryVideo,
                    '-frames:v',
                    '1',
                    $temporaryPosterFrame,
                ];
                $posterProcess = static::runProcess($posterCommand, min(30, (float)($videoDefinition['timeout'] ?? 90)));
                if (!$posterProcess['success'] || !is_file($temporaryPosterFrame) || filesize($temporaryPosterFrame) <= 0) {
                    $error = $posterProcess['error'] ?: Tools::displayError('The video preview could not be generated.');
                    return false;
                }

                $imageError = 0;
                if (!ImageManager::resize(
                    $temporaryPosterFrame,
                    $temporaryPoster,
                    null,
                    null,
                    $posterExtension,
                    false,
                    $imageError
                )) {
                    $error = Tools::displayError('The video preview could not be stored in the configured image format.');
                    return false;
                }

                $targetVideo = static::getVideoPathFromDefinition($videoDefinition, $idEntity);
                $targetPoster = static::getPosterPathFromDefinition($videoDefinition, $idEntity);
                if (!static::installOutputFiles($temporaryVideo, $temporaryPoster, $targetVideo, $targetPoster, $error)) {
                    return false;
                }

                @chmod($targetVideo, 0644);
                @chmod($targetPoster, 0644);

                return true;
            } finally {
                if (is_file($temporaryVideo)) {
                    @unlink($temporaryVideo);
                }
                if (is_file($temporaryPosterFrame)) {
                    @unlink($temporaryPosterFrame);
                }
                if (is_file($temporaryPoster)) {
                    @unlink($temporaryPoster);
                }
            }
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
            return false;
        }
    }

    /**
     * @return bool
     */
    public static function videoExistsByEntity($className, $videoEntityName, $idEntity)
    {
        $path = static::getVideoPathByEntity($className, $videoEntityName, $idEntity);
        return $path !== '' && is_file($path);
    }

    /**
     * @return string
     */
    public static function getVideoPathByEntity($className, $videoEntityName, $idEntity)
    {
        try {
            $definition = static::getVideoEntityInfo($className, $videoEntityName);
            return $definition ? static::getVideoPathFromDefinition($definition, (int)$idEntity) : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }

    /**
     * @return string
     */
    public static function getPosterPathByEntity($className, $videoEntityName, $idEntity)
    {
        try {
            $definition = static::getVideoEntityInfo($className, $videoEntityName);
            if (!$definition || (int)$idEntity <= 0) {
                return '';
            }

            $path = ImageManager::getSourceImage(
                static::getEntityDirectory($definition),
                (int)$idEntity.'-poster',
                static::getPosterExtension()
            );

            return is_string($path) ? $path : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }

    /**
     * @return string
     */
    public static function getVideoLinkByEntity($className, $videoEntityName, $idEntity)
    {
        return static::getPublicLink(static::getVideoPathByEntity($className, $videoEntityName, $idEntity));
    }

    /**
     * @return string
     */
    public static function getPosterLinkByEntity($className, $videoEntityName, $idEntity)
    {
        return static::getPublicLink(static::getPosterPathByEntity($className, $videoEntityName, $idEntity));
    }

    /**
     * @return string[]
     */
    public static function getPublicUrls($className, $videoEntityName, $idEntity)
    {
        return array_values(array_filter([
            static::getVideoLinkByEntity($className, $videoEntityName, $idEntity),
            static::getPosterLinkByEntity($className, $videoEntityName, $idEntity),
        ]));
    }

    /**
     * @return bool
     */
    public static function deleteVideoByEntity($className, $videoEntityName, $idEntity)
    {
        $success = true;
        $paths = [static::getVideoPathByEntity($className, $videoEntityName, $idEntity)];
        try {
            $definition = static::getVideoEntityInfo($className, $videoEntityName);
            if ($definition) {
                foreach (ImageManager::getAllowedImageExtensions(true, true) as $extension) {
                    $paths[] = static::getEntityDirectory($definition).(int)$idEntity.'-poster.'.$extension;
                }
            }
        } catch (Throwable $throwable) {
            $success = false;
        }

        foreach (array_unique($paths) as $path) {
            if ($path !== '' && is_file($path) && !@unlink($path)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @return array{success:bool,error:string,metadata:array}
     */
    protected static function probe($sourcePath, array $videoDefinition)
    {
        $process = static::runProcess([
            'ffprobe',
            '-v',
            'error',
            '-show_streams',
            '-show_format',
            '-of',
            'json',
            (string)$sourcePath,
        ], min(30, (float)($videoDefinition['timeout'] ?? 90)));

        if (!$process['success']) {
            return ['success' => false, 'error' => $process['error'], 'metadata' => []];
        }

        $data = json_decode($process['output'], true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => Tools::displayError('The uploaded file is not a readable video.'), 'metadata' => []];
        }

        $videoStream = null;
        $audioStream = null;
        foreach ((array)($data['streams'] ?? []) as $stream) {
            if (!is_array($stream)) {
                continue;
            }
            if ($videoStream === null && ($stream['codec_type'] ?? '') === 'video') {
                $videoStream = $stream;
            } elseif ($audioStream === null && ($stream['codec_type'] ?? '') === 'audio') {
                $audioStream = $stream;
            }
        }

        $width = (int)($videoStream['width'] ?? 0);
        $height = (int)($videoStream['height'] ?? 0);
        if (!$videoStream || $width <= 0 || $height <= 0) {
            return ['success' => false, 'error' => Tools::displayError('The uploaded file does not contain a valid video stream.'), 'metadata' => []];
        }

        $rotation = static::getRotation($videoStream);
        if ($rotation === 90 || $rotation === 270) {
            [$width, $height] = [$height, $width];
        }

        $duration = (float)($data['format']['duration'] ?? $videoStream['duration'] ?? 0);
        if ($duration <= 0) {
            return ['success' => false, 'error' => Tools::displayError('The video duration could not be determined.'), 'metadata' => []];
        }

        return [
            'success' => true,
            'error' => '',
            'metadata' => [
                'width' => $width,
                'height' => $height,
                'rotation' => $rotation,
                'duration' => $duration,
                'video_codec' => (string)($videoStream['codec_name'] ?? ''),
                'pixel_format' => (string)($videoStream['pix_fmt'] ?? ''),
                'frame_rate' => static::parseFrameRate((string)($videoStream['avg_frame_rate'] ?? $videoStream['r_frame_rate'] ?? '0/0')),
                'audio_codec' => $audioStream ? (string)($audioStream['codec_name'] ?? '') : '',
                'has_audio' => (bool)$audioStream,
            ],
        ];
    }

    /**
     * @return array
     */
    protected static function buildOutputSettings(array $metadata, array $videoDefinition)
    {
        $sourceWidth = (int)$metadata['width'];
        $sourceHeight = (int)$metadata['height'];
        $sourceRatio = $sourceWidth / $sourceHeight;
        $aspectRatio = static::selectClosestAspectRatio($sourceRatio, $videoDefinition['aspectRatios']);
        if ($aspectRatio === static::RATIO_SQUARE) {
            $resolutionLimit = [1080, 1080];
        } elseif ($aspectRatio === static::RATIO_PORTRAIT) {
            $resolutionLimit = [1080, 1920];
        } else {
            $resolutionLimit = [1920, 1080];
        }
        $maxWidth = max(2, min($resolutionLimit[0], (int)($videoDefinition['maxWidth'] ?? $resolutionLimit[0])));
        $maxHeight = max(2, min($resolutionLimit[1], (int)($videoDefinition['maxHeight'] ?? $resolutionLimit[1])));
        [$ratioWidth, $ratioHeight] = static::parseRatioParts($aspectRatio);

        $cropScale = static::even((int)floor(min($sourceWidth / $ratioWidth, $sourceHeight / $ratioHeight)));
        $maxScale = static::even((int)floor(min($maxWidth / $ratioWidth, $maxHeight / $ratioHeight)));
        if ($cropScale < 2 || $maxScale < 2) {
            throw new PrestaShopException('The video dimensions are too small for the selected aspect ratio.');
        }

        $cropWidth = $ratioWidth * $cropScale;
        $cropHeight = $ratioHeight * $cropScale;
        $outputScale = min($cropScale, $maxScale);
        $outputWidth = $ratioWidth * $outputScale;
        $outputHeight = $ratioHeight * $outputScale;
        $filters = [];

        if ($cropWidth !== $sourceWidth || $cropHeight !== $sourceHeight) {
            $filters[] = sprintf(
                'crop=%d:%d:(iw-%d)/2:(ih-%d)/2',
                $cropWidth,
                $cropHeight,
                $cropWidth,
                $cropHeight
            );
        }
        if ($outputWidth !== $cropWidth || $outputHeight !== $cropHeight) {
            $filters[] = sprintf('scale=%d:%d', $outputWidth, $outputHeight);
        }

        $filter = implode(',', $filters);

        return [
            'aspect_ratio' => $aspectRatio,
            'width' => $outputWidth,
            'height' => $outputHeight,
            'filter' => $filter,
            'transcode' => $filter !== ''
                || $metadata['video_codec'] !== 'h264'
                || $metadata['pixel_format'] !== 'yuv420p'
                || ($metadata['has_audio'] && $metadata['audio_codec'] !== 'aac')
                || $metadata['frame_rate'] > 30,
        ];
    }

    /**
     * @return string[]
     */
    protected static function buildVideoCommand($sourcePath, $targetPath, array $metadata, array $output, array $videoDefinition)
    {
        $command = [
            'ffmpeg',
            '-nostdin',
            '-hide_banner',
            '-loglevel',
            'error',
            '-i',
            (string)$sourcePath,
            '-map',
            '0:v:0',
            '-map',
            '0:a:0?',
        ];

        if (!$output['transcode']) {
            $command[] = '-c';
            $command[] = 'copy';
        } else {
            if ($output['filter'] !== '') {
                $command[] = '-vf';
                $command[] = $output['filter'];
            }
            $command[] = '-c:v';
            $command[] = 'libx264';
            $command[] = '-preset';
            $command[] = (string)($videoDefinition['preset'] ?? 'veryfast');
            $command[] = '-crf';
            $command[] = (string)max(0, min(51, (int)($videoDefinition['crf'] ?? 23)));
            $command[] = '-pix_fmt';
            $command[] = 'yuv420p';
            if ($metadata['frame_rate'] > 30) {
                $command[] = '-r';
                $command[] = '30';
            }
            $command[] = '-c:a';
            $command[] = 'aac';
            $command[] = '-b:a';
            $command[] = '128k';
        }

        $command[] = '-movflags';
        $command[] = '+faststart';
        $command[] = (string)$targetPath;

        return $command;
    }

    /**
     * @return array{success:bool,error:string,output:string}
     */
    protected static function runProcess(array $command, $timeout)
    {
        if (!function_exists('proc_open')) {
            return ['success' => false, 'error' => Tools::displayError('PHP process execution is not available.'), 'output' => ''];
        }

        $pipes = [];
        $process = null;
        try {
            $process = proc_open(
                array_map('strval', $command),
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true]
            );
            if (!is_resource($process)) {
                return ['success' => false, 'error' => Tools::displayError('Video processing could not be started.'), 'output' => ''];
            }

            fclose($pipes[0]);
            unset($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $standardOutput = '';
            $errorOutput = '';
            $exitCode = -1;
            $timedOut = false;
            $startedAt = microtime(true);
            $timeout = max(1, (float)$timeout);

            do {
                $standardOutput .= (string)stream_get_contents($pipes[1]);
                $errorOutput .= (string)stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exitCode = (int)$status['exitcode'];
                    break;
                }
                if (microtime(true) - $startedAt >= $timeout) {
                    $timedOut = true;
                    proc_terminate($process);
                    $errorOutput = trim($errorOutput."\n".Tools::displayError('Video processing timed out.'));
                    break;
                }
                usleep(10000);
            } while (true);

            $standardOutput .= (string)stream_get_contents($pipes[1]);
            $errorOutput .= (string)stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $pipes = [];

            $closeExitCode = proc_close($process);
            $process = null;
            if ($exitCode < 0) {
                $exitCode = (int)$closeExitCode;
            }
            if ($timedOut || $exitCode !== 0) {
                $message = trim($errorOutput);
                return [
                    'success' => false,
                    'error' => $message !== '' ? $message : Tools::displayError('Video processing failed.'),
                    'output' => '',
                ];
            }

            return [
                'success' => true,
                'error' => '',
                'output' => $standardOutput,
            ];
        } catch (Throwable $throwable) {
            return ['success' => false, 'error' => $throwable->getMessage(), 'output' => ''];
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }

    /**
     * @return string
     */
    protected static function getEntityDirectory(array $videoDefinition)
    {
        return rtrim((string)($videoDefinition['path'] ?? ''), '/\\').DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    protected static function getPosterExtension()
    {
        return ImageManager::getDefaultImageExtension();
    }

    /**
     * @return string
     */
    protected static function getVideoPathFromDefinition(array $videoDefinition, $idEntity)
    {
        if ((int)$idEntity <= 0) {
            return '';
        }
        return static::getEntityDirectory($videoDefinition).(int)$idEntity.'.'.static::EXTENSION;
    }

    /**
     * @return string
     */
    protected static function getPosterPathFromDefinition(array $videoDefinition, $idEntity)
    {
        if ((int)$idEntity <= 0) {
            return '';
        }
        return static::getEntityDirectory($videoDefinition).(int)$idEntity.'-poster.'.static::getPosterExtension();
    }

    /**
     * @return string
     */
    protected static function getPublicLink($path)
    {
        $path = str_replace('\\', '/', (string)$path);
        $root = rtrim(str_replace('\\', '/', _PS_ROOT_DIR_), '/').'/';
        if ($path === '' || !is_file($path) || strpos($path, $root) !== 0) {
            return '';
        }

        $uriPath = __PS_BASE_URI__.ltrim(substr($path, strlen($root)), '/');
        $modifiedAt = (int)@filemtime($path);
        $fileSize = (int)@filesize($path);
        $version = $modifiedAt > 0 ? $modifiedAt.'-'.$fileSize : '';

        return Tools::getShopProtocol().Tools::getMediaServer($uriPath).$uriPath.($version !== '' ? '?v='.$version : '');
    }

    /**
     * @return bool
     */
    protected static function ensureDirectory($directory)
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        return @mkdir($directory, 0775, true) && is_writable($directory);
    }

    /**
     * Replace video and preview together and restore the previous pair on failure.
     *
     * @param int|string $error
     *
     * @return bool
     */
    protected static function installOutputFiles($temporaryVideo, $temporaryPoster, $targetVideo, $targetPoster, &$error)
    {
        $backupBase = tempnam(dirname($targetVideo), 'video-backup-');
        if (!$backupBase) {
            $error = Tools::displayError('The existing video could not be secured before replacement.');
            return false;
        }
        @unlink($backupBase);

        $backupVideo = $backupBase.'.'.static::EXTENSION;
        $backupPoster = $backupBase.'-poster.'.pathinfo($targetPoster, PATHINFO_EXTENSION);
        $hadVideo = is_file($targetVideo);
        $hadPoster = is_file($targetPoster);

        if ($hadVideo && !@rename($targetVideo, $backupVideo)) {
            $error = Tools::displayError('The existing video could not be secured before replacement.');
            return false;
        }
        if ($hadPoster && !@rename($targetPoster, $backupPoster)) {
            if ($hadVideo) {
                @rename($backupVideo, $targetVideo);
            }
            $error = Tools::displayError('The existing video preview could not be secured before replacement.');
            return false;
        }

        if (!@rename($temporaryVideo, $targetVideo)) {
            static::restoreOutputFiles($targetVideo, $targetPoster, $backupVideo, $backupPoster, $hadVideo, $hadPoster);
            $error = Tools::displayError('The converted video could not be saved.');
            return false;
        }
        if (!@rename($temporaryPoster, $targetPoster)) {
            static::restoreOutputFiles($targetVideo, $targetPoster, $backupVideo, $backupPoster, $hadVideo, $hadPoster);
            $error = Tools::displayError('The video preview could not be saved.');
            return false;
        }

        if ($hadVideo) {
            @unlink($backupVideo);
        }
        if ($hadPoster) {
            @unlink($backupPoster);
        }

        return true;
    }

    /**
     * @return void
     */
    protected static function restoreOutputFiles($targetVideo, $targetPoster, $backupVideo, $backupPoster, $hadVideo, $hadPoster)
    {
        if (is_file($targetVideo)) {
            @unlink($targetVideo);
        }
        if (is_file($targetPoster)) {
            @unlink($targetPoster);
        }
        if ($hadVideo && is_file($backupVideo)) {
            @rename($backupVideo, $targetVideo);
        }
        if ($hadPoster && is_file($backupPoster)) {
            @rename($backupPoster, $targetPoster);
        }
    }

    /**
     * @return float
     */
    protected static function parseRatio($ratio)
    {
        if (!preg_match('/^(\d+):(\d+)$/', trim((string)$ratio), $matches) || (int)$matches[2] <= 0) {
            return 0;
        }

        return (int)$matches[1] / (int)$matches[2];
    }

    /**
     * @return int[]
     */
    protected static function parseRatioParts($ratio)
    {
        if (!preg_match('/^(\d+):(\d+)$/', trim((string)$ratio), $matches)
            || (int)$matches[1] <= 0
            || (int)$matches[2] <= 0
        ) {
            throw new PrestaShopException('Invalid video aspect ratio.');
        }

        return [(int)$matches[1], (int)$matches[2]];
    }

    /**
     * Choose the target that retains the largest part of the source image.
     * Matching orientation wins exact ties such as 4:3 versus 16:9 and 1:1.
     *
     * @return string
     */
    protected static function selectClosestAspectRatio($sourceRatio, array $aspectRatios)
    {
        $sourceRatio = (float)$sourceRatio;
        $sourceOrientation = static::getRatioOrientation($sourceRatio);
        $selectedRatio = '';
        $selectedScore = -1;
        $selectedOrientationMatches = false;

        foreach ($aspectRatios as $aspectRatio) {
            $aspectRatio = (string)$aspectRatio;
            $targetRatio = static::parseRatio($aspectRatio);
            if ($targetRatio <= 0) {
                continue;
            }

            $score = min($sourceRatio / $targetRatio, $targetRatio / $sourceRatio);
            $orientationMatches = static::getRatioOrientation($targetRatio) === $sourceOrientation;
            if ($score > $selectedScore + 0.000000001
                || (abs($score - $selectedScore) <= 0.000000001 && $orientationMatches && !$selectedOrientationMatches)
            ) {
                $selectedRatio = $aspectRatio;
                $selectedScore = $score;
                $selectedOrientationMatches = $orientationMatches;
            }
        }

        if ($selectedRatio === '') {
            throw new PrestaShopException('No valid video aspect ratio is configured.');
        }

        return $selectedRatio;
    }

    /**
     * @return int -1 portrait, 0 square, 1 landscape
     */
    protected static function getRatioOrientation($ratio)
    {
        $ratio = (float)$ratio;
        if (abs($ratio - 1) <= 0.000000001) {
            return 0;
        }

        return $ratio > 1 ? 1 : -1;
    }

    /**
     * @return int
     */
    protected static function getRotation(array $videoStream)
    {
        $rotation = (float)($videoStream['tags']['rotate'] ?? 0);
        foreach ((array)($videoStream['side_data_list'] ?? []) as $sideData) {
            if (is_array($sideData) && array_key_exists('rotation', $sideData)) {
                $rotation = (float)$sideData['rotation'];
                break;
            }
        }

        return ((int)round($rotation) % 360 + 360) % 360;
    }

    /**
     * @return float
     */
    protected static function parseFrameRate($frameRate)
    {
        if (preg_match('/^(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/', (string)$frameRate, $matches)) {
            $denominator = (float)$matches[2];
            return $denominator > 0 ? (float)$matches[1] / $denominator : 0;
        }

        return max(0, (float)$frameRate);
    }

    /**
     * @return int
     */
    protected static function even($value)
    {
        $value = max(0, (int)$value);
        return $value % 2 === 0 ? $value : $value - 1;
    }

    /**
     * @return string
     */
    protected static function formatDecimal($value)
    {
        $formatted = rtrim(rtrim(number_format((float)$value, 3, '.', ''), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }
}
