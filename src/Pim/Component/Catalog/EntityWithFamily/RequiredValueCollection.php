<?php

declare(strict_types=1);

namespace Pim\Component\Catalog\EntityWithFamily;

use Pim\Component\Catalog\Model\ChannelInterface;
use Pim\Component\Catalog\Model\LocaleInterface;

/**
 * A collection of required values depending on the attribute requirements of a family.
 * {@see Pim\Component\Catalog\EntityWithFamily\RequiredValue}
 *
 * This collection is not dependant of a channel and/or locale context. Which means, it's the responsibility of
 * the user to know what the collection holds.
 *
 * @internal
 *
 * @author    Julien Janvier <j.janvier@gmail.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class RequiredValueCollection implements \Countable, \IteratorAggregate
{
    /** @var RequiredValue[] */
    private $values;

    /**
     * @param RequiredValue[] $values
     */
    public function __construct(array $values)
    {
        $this->values = [];

        foreach ($values as $value) {
            if (!$value instanceof RequiredValue) {
                throw new \InvalidArgumentException(
                    'Expected an instance of "Pim\Component\Catalog\EntityWithFamily\RequiredValue".'
                );
            }

            $this->values[] = $value;
        }
    }

    /**
     * Returns all the elements of this collection that satisfy the given channel and locale.
     *
     * @param ChannelInterface $channel
     * @param LocaleInterface  $locale
     *
     * @return RequiredValueCollection
     */
    public function filterByChannelAndLocale(
        ChannelInterface $channel,
        LocaleInterface $locale
    ): RequiredValueCollection {
        $filteredValues = array_filter(
            $this->values,
            function (RequiredValue $requiredValue) use ($channel, $locale) {
                $attribute = $requiredValue->getAttribute();

                if ($attribute->isScopable() && $requiredValue->getScope() !== $channel->getCode()) {
                    return false;
                }

                if ($attribute->isLocalizable() && $requiredValue->getLocale() !== $locale->getCode()) {
                    return false;
                }

                if ($attribute->isLocaleSpecific() &&
                    (!$attribute->hasLocaleSpecific($locale) || $requiredValue->getLocale()!== $locale->getCode())
                ) {
                    return false;
                }

                return true;
            }
        );

        return new static($filteredValues);
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->values);
    }
}
