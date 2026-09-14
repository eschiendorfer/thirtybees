<?php

/** A service case owns its work status; messages only provide the editor. */
interface CustomerServiceStatusSourceInterfaceCore
{
    public function getCustomerServiceStatusField(): string;

    /** @return array<string, string> Stored values and translatable labels. */
    public function getCustomerServiceStatusLabels(): array;
}
