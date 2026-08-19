<?php

/**
 * Provides view-independent context for a customer service thread.
 */
interface CustomerThreadContextSourceInterfaceCore
{
    /**
     * @return array<string, mixed>
     */
    public function getCustomerThreadContextData(): array;
}
