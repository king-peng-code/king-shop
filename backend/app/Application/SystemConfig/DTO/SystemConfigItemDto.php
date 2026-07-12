<?php

namespace App\Application\SystemConfig\DTO;

readonly class SystemConfigItemDto
{
    public function __construct(
        public string $group,
        public string $key,
        public string $value,
    ) {}
}
