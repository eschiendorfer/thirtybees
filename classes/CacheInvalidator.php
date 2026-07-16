<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2026 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSES/OSL-3.0.txt.
 */

/**
 * Class CacheInvalidatorCore
 */
class CacheInvalidatorCore
{
    public const HOOK_INVALIDATE_CACHE = 'actionInvalidateCache';

    /**
     * @return bool
     */
    public static function hasProvider(): bool
    {
        try {
            return (bool)Hook::getHookModuleExecList(static::HOOK_INVALIDATE_CACHE);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Invalidate public cache entries for the supplied URLs.
     *
     * Hook providers must return false when invalidation fails.
     *
     * @param string[] $urls
     *
     * @return bool
     */
    public static function invalidateUrls(array $urls): bool
    {
        $urls = array_values(array_unique($urls));
        if (!$urls) {
            return false;
        }

        try {
            if (!static::hasProvider()) {
                return false;
            }

            $responses = Hook::exec(
                static::HOOK_INVALIDATE_CACHE,
                ['urls' => $urls],
                null,
                true,
                false
            );

            return is_array($responses)
                && !empty($responses)
                && !in_array(false, $responses, true);
        } catch (Throwable $e) {
            return false;
        }
    }
}
