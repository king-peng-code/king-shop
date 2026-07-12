<?php

namespace App\Domain\Storage\Services;

interface StorageDriverResolverInterface
{
    public function resolve(): StorageDriverInterface;
}
