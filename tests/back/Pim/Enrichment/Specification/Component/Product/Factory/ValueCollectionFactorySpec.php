<?php

namespace Specification\Akeneo\Pim\Enrichment\Component\Product\Factory;

use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Akeneo\Tool\Component\StorageUtils\Repository\CachedObjectRepositoryInterface;
use PhpSpec\ObjectBehavior;
use Akeneo\Pim\Enrichment\Component\Product\Exception\InvalidAttributeException;
use Akeneo\Pim\Enrichment\Component\Product\Exception\InvalidOptionException;
use Akeneo\Pim\Enrichment\Component\Product\Exception\InvalidOptionsException;
use Akeneo\Pim\Enrichment\Component\Product\Factory\ValueCollectionFactory;
use Akeneo\Pim\Enrichment\Component\Product\Factory\ValueFactory;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueCollection;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;

class ValueCollectionFactorySpec extends ObjectBehavior
{
    function let(
        ValueFactory $valueFactory,
        CachedObjectRepositoryInterface $attributeRepository,
        LoggerInterface $logger
    ) {
        $this->beConstructedWith($valueFactory, $attributeRepository, $logger);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ValueCollectionFactory::class);
    }

    function it_creates_a_values_collection_from_the_storage_format(
        $valueFactory,
        $attributeRepository,
        AttributeInterface $sku,
        AttributeInterface $description,
        ValueInterface $value1,
        ValueInterface $value2,
        ValueInterface $value3,
        ValueInterface $value4
    ) {
        $sku->getCode()->willReturn('sku');
        $sku->isUnique()->willReturn(false);
        $description->getCode()->willReturn('description');
        $description->isUnique()->willReturn(false);

        $value1->getLocaleCode()->willReturn(null);
        $value1->getScopeCode()->willReturn(null);
        $value1->getAttributeCode()->willReturn('sku');

        $value2->getScopeCode()->willReturn('ecommerce');
        $value2->getLocaleCode()->willReturn('en_US');
        $value2->getAttributeCode()->willReturn('description');
        $value3->getScopeCode()->willReturn('tablet');
        $value3->getLocaleCode()->willReturn('en_US');
        $value3->getAttributeCode()->willReturn('description');
        $value4->getScopeCode()->willReturn('tablet');
        $value4->getLocaleCode()->willReturn('fr_FR');
        $value4->getAttributeCode()->willReturn('description');

        $attributeRepository->findOneByIdentifier('sku')->willReturn($sku);
        $attributeRepository->findOneByIdentifier('description')->willReturn($description);

        $valueFactory->create($sku, null, null, 'foo', true)->willReturn($value1);
        $valueFactory
            ->create($description, 'ecommerce', 'en_US', 'a text area for ecommerce in English', true)
            ->willReturn($value2);
        $valueFactory
            ->create($description, 'tablet', 'en_US', 'a text area for tablets in English', true)
            ->willReturn($value3);
        $valueFactory
            ->create($description, 'tablet', 'fr_FR', 'une zone de texte pour les tablettes en français', true)
            ->willReturn($value4);

        $actualValues = $this->createFromStorageFormat([
            'sku' => [
                '<all_channels>' => [
                    '<all_locales>' => 'foo'
                ],
            ],
            'description' => [
                'ecommerce' => [
                    'en_US' => 'a text area for ecommerce in English',
                ],
                'tablet' => [
                    'en_US' => 'a text area for tablets in English',
                    'fr_FR' => 'une zone de texte pour les tablettes en français',

                ],
            ],
        ]);

        $actualValues->shouldReturnAnInstanceOf(ValueCollection::class);
        $actualValues->shouldHaveCount(4);

        $actualIterator = $actualValues->getIterator();
        $actualIterator->shouldHaveKeyWithValue('sku-<all_channels>-<all_locales>', $value1);
        $actualIterator->shouldHaveKeyWithValue('description-ecommerce-en_US', $value2);
        $actualIterator->shouldHaveKeyWithValue('description-tablet-en_US', $value3);
        $actualIterator->shouldHaveKeyWithValue('description-tablet-fr_FR', $value4);
    }

    function it_skips_unknown_attributes_when_creating_a_values_collection_from_the_storage_format(
        $valueFactory,
        $attributeRepository,
        $logger
    ) {
        $attributeRepository->findOneByIdentifier('attribute_that_does_not_exists')->willReturn(null);

        $valueFactory->create(Argument::cetera())->shouldNotBeCalled();
        $logger->warning('Tried to load a product value with the attribute "attribute_that_does_not_exists" that does not exist.');

        $actualValues = $this->createFromStorageFormat([
            'attribute_that_does_not_exists' => [
                '<all_channels>' => [
                    '<all_locales>' => 'bar'
                ]
            ]
        ]);

        $actualValues->shouldReturnAnInstanceOf(ValueCollection::class);
        $actualValues->shouldHaveCount(0);
    }

    function it_skips_unknown_option_when_creating_a_values_collection_from_the_storage_format(
        $valueFactory,
        $attributeRepository,
        $logger,
        AttributeInterface $color
    ) {
        $attributeRepository->findOneByIdentifier('color')->willReturn($color);
        $valueFactory->create($color, null, null, 'red', true)->willThrow(
            InvalidOptionException::validEntityCodeExpected(
                'color',
                'code',
                'The option does not exist',
                static::class,
                'red'
            )
        );

        $logger->warning('Tried to load a product value with the option "color.red" that does not exist.');

        $actualValues = $this->createFromStorageFormat([
            'color' => [
                '<all_channels>' => [
                    '<all_locales>' => 'red'
                ],
            ],
        ]);

        $actualValues->shouldReturnAnInstanceOf(ValueCollection::class);
        $actualValues->shouldHaveCount(0);
    }

    function it_skips_unknown_options_when_creating_a_values_collection_from_the_storage_format(
        $valueFactory,
        $attributeRepository,
        $logger,
        AttributeInterface $color,
        ValueInterface $purpleColor
    ) {
        $color->getCode()->willReturn('code');
        $color->isUnique()->willReturn(false);
        $attributeRepository->findOneByIdentifier('color')->willReturn($color);
        $valueFactory->create($color, null, null, ['red', 'purple', 'yellow'], true)->willThrow(
            InvalidOptionsException::validEntityListCodesExpected(
                'color',
                'codes',
                'The options do not exist',
                static::class,
                ['red', 'yellow']
            )
        );

        $purpleColor->getAttributeCode()->willReturn('color');
        $purpleColor->getLocaleCode()->willReturn(null);
        $purpleColor->getScopeCode()->willReturn(null);
        $purpleColor->getData()->willReturn('purple');
        $valueFactory->create($color, null, null, [1 => 'purple'])->willReturn($purpleColor);
        $logger->warning('Tried to load a product value with the options "red, yellow" that do not exist.')->shouldBeCalled();

        $actualValues = $this->createFromStorageFormat([
            'color' => [
                '<all_channels>' => [
                    '<all_locales>' => ['red', 'purple', 'yellow']
                ],
            ],
        ]);

        $actualValues->shouldReturnAnInstanceOf(ValueCollection::class);
        $actualValues->shouldHaveCount(1);
    }

    function it_skips_invalid_attributes_when_creating_a_values_collection_from_the_storage_format(
        $valueFactory,
        $attributeRepository,
        $logger,
        AttributeInterface $color
    ) {
        $attributeRepository->findOneByIdentifier('color')->willReturn($color);
        $valueFactory->create($color, null, null, 'red', true)->willThrow(
            new InvalidAttributeException('attribute', 'color', static::class)
        );

        $logger->warning(Argument::containingString('Tried to load a product value with an invalid attribute "color".'));

        $actualValues = $this->createFromStorageFormat([
            'color' => [
                '<all_channels>' => [
                    '<all_locales>' => 'red'
                ],
            ],
        ]);

        $actualValues->shouldReturnAnInstanceOf(ValueCollection::class);
        $actualValues->shouldHaveCount(0);
    }

    function it_skips_unknown_property_when_creating_a_values_collection_from_the_storage_format(
        $valueFactory,
        $attributeRepository,
        $logger,
        AttributeInterface $referenceData
    ) {
        $attributeRepository->findOneByIdentifier('image')->willReturn($referenceData);
        $valueFactory->create($referenceData, null, null, 'my_image', true)->willThrow(
            new InvalidPropertyException('attribute', 'image', static::class)
        );

        $logger->warning(
            Argument::containingString('Tried to load a product value with the property "image" that does not exist.')
        );

        $actualValues = $this->createFromStorageFormat([
            'image' => [
                '<all_channels>' => [
                    '<all_locales>' => 'my_image'
                ],
            ],
        ]);

        $actualValues->shouldReturnAnInstanceOf(ValueCollection::class);
        $actualValues->shouldHaveCount(0);
    }

    function it_create_empty_value_is_wrong_format_when_creating_a_values_collection_from_the_storage_format(
        $valueFactory,
        $attributeRepository,
        $logger,
        AttributeInterface $image,
        ValueInterface $value1,
        AttributeInterface $referenceData
    ) {
        $image->getCode()->willReturn('image');
        $image->isUnique()->willReturn(false);
        $value1->getLocaleCode()->willReturn(null);
        $value1->getScopeCode()->willReturn(null);
        $value1->getAttributeCode()->willReturn('image');

        $attributeRepository->findOneByIdentifier('image')->willReturn($referenceData);
        $valueFactory->create($referenceData, null, null, 'my_image', true)->willThrow(
            new InvalidPropertyTypeException('attribute', 'image', static::class)
        );
        $valueFactory->create($referenceData, null, null, 'empty_image', true)->willReturn($value1);

        $logger->warning(
            Argument::containingString('Tried to load a product value for attribute "image" that does not have the good type.')
        );

        $actualValues = $this->createFromStorageFormat([
            'image' => [
                '<all_channels>' => [
                    '<all_locales>' => 'empty_image'
                ],
            ],
        ]);

        $actualValues->shouldReturnAnInstanceOf(ValueCollection::class);
        $actualValues->shouldHaveCount(1);
    }
}
