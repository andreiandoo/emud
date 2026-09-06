<?php

namespace App\Suppliers\Contracts;

interface SupplierFeedArtifactProvider
{
    /** @return array<string, mixed>|null */
    public function lastArtifact(): ?array;
}
