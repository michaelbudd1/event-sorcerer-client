<?php

declare (strict_types=1);

namespace PearTreeWeb\MicroManager\Client\Domain\Service;

final class FindEventClassFilepath
{
    public function for(string $name): string
    {
        // scan configured events folder and call static method name() on each
        // throw if no event found
    }
}
