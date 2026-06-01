<?php

namespace Shekel\ShekelLib\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class LogAdminActivity
{
    public function __construct(
        public string $action,
        public string $subjectType,
        public string $description,
        public string $subjectParam = 'id',
    ) {}
}
