<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Grid;

/**
 *
 *
 * @author    Laurent Petard <laurent.petard@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @todo Rename. It describes the product-grid structure status
 */
class Context
{
    /** @var string[] */
    private $attributesToDisplay;

    /** @var string[] */
    private $propertiesToDisplay;

    /** @var string */
    private $locale;

    /** @var string */
    private $channel;

    /**
     * @param string   $locale
     * @param string   $channel
     * @param string[] $attributesToDisplay
     * @param string[] $propertiesToDisplay
     */
    public function __construct(string $locale, string $channel, array $attributesToDisplay, array $propertiesToDisplay)
    {
        $this->locale = $locale;
        $this->channel = $channel;
        $this->attributesToDisplay = $attributesToDisplay;
        $this->propertiesToDisplay = $propertiesToDisplay;
    }

    /**
     * @return string
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * @return string[]
     */
    public function attributesToDisplay(): array
    {
        return $this->attributesToDisplay;
    }

    /**
     * @return string[]
     */
    public function propertiesToDisplay(): array
    {
        return $this->propertiesToDisplay;
    }
}
