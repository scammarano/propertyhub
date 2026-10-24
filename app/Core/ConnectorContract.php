<?php
declare(strict_types=1);

interface ConnectorContract
{
    public function code(): string;
    public function name(): string;
    public function capabilities(): array;
    public function health(): array;
}
