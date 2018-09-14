<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Query;

/**
 * @author    Laurent Petard <laurent.petard@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @todo Find a better name
 */
interface GetFilteredProductValues
{
    public function fetch(array $productIds, array $attributesCodes, string $channel, string $locale): array;
}
