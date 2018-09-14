<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Query;

use Akeneo\Pim\Enrichment\Component\Product\Grid\Context;

interface GetRowsFromIdentifiers
{
    public function fetch(array $identifiers, Context $context): array;
}
