<?php

namespace Specification\Akeneo\Pim\Enrichment\Component\Product\ValuesFiller;

use PhpSpec\ObjectBehavior;
use Akeneo\Pim\Enrichment\Component\Product\Builder\EntityWithValuesBuilderInterface;
use Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant\EntityWithFamilyVariantAttributesProvider;
use Akeneo\Pim\Enrichment\Component\Product\Manager\AttributeValuesResolverInterface;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\CachedObjectRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Akeneo\Channel\Component\Repository\CurrencyRepositoryInterface;
use Prophecy\Argument;

class EntityWithFamilyVariantValuesFillerSpec extends ObjectBehavior
{
    function let(
        EntityWithValuesBuilderInterface $entityWithValuesBuilder,
        AttributeValuesResolverInterface $valuesResolver,
        CurrencyRepositoryInterface $currencyRepository,
        EntityWithFamilyVariantAttributesProvider $attributesProvider,
        CachedObjectRepositoryInterface $attributeRepository
    ) {
        $this->beConstructedWith($entityWithValuesBuilder, $valuesResolver, $currencyRepository, $attributesProvider, $attributeRepository);
    }

    function it_throws_an_exception_if_this_is_not_an_entity_with_a_family_variant(
        EntityWithFamilyInterface $foo
    ) {
        $this->shouldThrow(\InvalidArgumentException::class)->during('fillMissingValues', [$foo]);
    }

    function it_fills_missing_product_values_from_attribute_set_on_new_entity_with_family_variant(
        $attributesProvider,
        $valuesResolver,
        $entityWithValuesBuilder,
        ProductModelInterface $entity,
        ProductModelInterface $parentEntity,
        AttributeInterface $name,
        AttributeInterface $color,
        AttributeInterface $sku,
        ValueInterface $skuValue,
        ValueInterface $colorValue,
        $attributeRepository
    ) {
        $sku->getCode()->willReturn('sku');
        $sku->getType()->willReturn('pim_catalog_identifier');
        $sku->isLocalizable()->willReturn(false);
        $sku->isScopable()->willReturn(false);

        $attributeRepository->findOneByIdentifier('sku')->willReturn($sku);

        $name->getCode()->willReturn('name');
        $name->getType()->willReturn('pim_catalog_text');
        $name->isLocalizable()->willReturn(true);
        $name->isScopable()->willReturn(false);

        $attributeRepository->findOneByIdentifier('name')->willReturn($name);

        $color->getCode()->willReturn('color');
        $color->getType()->willReturn('pim_catalog_simpleselect');
        $color->isLocalizable()->willReturn(false);
        $color->isScopable()->willReturn(false);

        $attributeRepository->findOneByIdentifier('color')->willReturn($color);

        $expectedParentAttributes = [$name];
        $expectedAttributes = [$sku, $color];

        $parentEntity->getParent()->willReturn(null);
        $entity->getParent()->willReturn($parentEntity);

        $attributesProvider->getAttributes($parentEntity)->willReturn($expectedParentAttributes);
        $attributesProvider->getAttributes($entity)->willReturn($expectedAttributes);

        $valuesResolver->resolveEligibleValues(['sku' => $sku, 'name' => $name, 'color' => $color])
            ->willReturn([
                [
                    'attribute' => 'sku',
                    'type' => 'pim_catalog_identifier',
                    'locale' => null,
                    'scope' => null
                ],
                [
                    'attribute' => 'name',
                    'type' => 'pim_catalog_text',
                    'locale' => 'fr_FR',
                    'scope' => null
                ],
                [
                    'attribute' => 'name',
                    'type' => 'pim_catalog_text',
                    'locale' => 'en_US',
                    'scope' => null
                ],
                [
                    'attribute' => 'color',
                    'type' => 'pim_catalog_simpleselect',
                    'locale' => null,
                    'scope' => null
                ]
            ]);

        // get existing values
        $skuValue->getAttributeCode()->willReturn('sku');
        $skuValue->getLocaleCode()->willReturn(null);
        $skuValue->getScopeCode()->willReturn(null);

        $colorValue->getAttributeCode()->willReturn('color');
        $colorValue->getLocaleCode()->willReturn(null);
        $colorValue->getScopeCode()->willReturn(null);

        $entity->getValues()->willReturn([$skuValue, $colorValue]);

        $entityWithValuesBuilder->addOrReplaceValue(Argument::cetera())->shouldBeCalledTimes(2);

        $this->fillMissingValues($entity);
    }

    function it_fills_missing_product_values_from_attribute_set_on_new_entity_with_family_variant_without_parent(
        $attributesProvider,
        $valuesResolver,
        $entityWithValuesBuilder,
        ProductModelInterface $entity,
        AttributeInterface $name
    ) {
        $name->getCode()->willReturn('name');
        $name->getType()->willReturn('pim_catalog_text');
        $name->isLocalizable()->willReturn(true);
        $name->isScopable()->willReturn(false);

        $expectedAttributes = [$name];

        $entity->getParent()->willReturn(null);
        $attributesProvider->getAttributes($entity)->willReturn($expectedAttributes);

        $valuesResolver->resolveEligibleValues(['name' => $name])
            ->willReturn([
                [
                    'attribute' => 'name',
                    'type' => 'pim_catalog_text',
                    'locale' => 'fr_FR',
                    'scope' => null
                ],
                [
                    'attribute' => 'name',
                    'type' => 'pim_catalog_text',
                    'locale' => 'en_US',
                    'scope' => null
                ]
            ]);

        $entity->getValues()->willReturn([]);
        $entityWithValuesBuilder->addOrReplaceValue(Argument::cetera())->shouldBeCalledTimes(2);

        $this->fillMissingValues($entity);
    }

    function it_fills_nothing_if_the_entity_with_family_variant_already_has_all_its_values(
        $attributesProvider,
        $valuesResolver,
        $entityWithValuesBuilder,
        ProductModelInterface $entity,
        ProductModelInterface $parentEntity,
        AttributeInterface $name,
        AttributeInterface $color,
        AttributeInterface $sku,
        AttributeInterface $intCodeAttribute,
        ValueInterface $skuValue,
        ValueInterface $nameFRValue,
        ValueInterface $nameENValue,
        ValueInterface $colorValue,
        ValueInterface $intCodeValue,
        $attributeRepository
    ) {
        $sku->getCode()->willReturn('sku');
        $sku->getType()->willReturn('pim_catalog_identifier');
        $sku->isLocalizable()->willReturn(false);
        $sku->isScopable()->willReturn(false);

        $attributeRepository->findOneByIdentifier('sku')->willReturn($sku);

        $name->getCode()->willReturn('name');
        $name->getType()->willReturn('pim_catalog_text');
        $name->isLocalizable()->willReturn(true);
        $name->isScopable()->willReturn(false);

        $attributeRepository->findOneByIdentifier('name')->willReturn($name);

        $color->getCode()->willReturn('color');
        $color->getType()->willReturn('pim_catalog_simpleselect');
        $color->isLocalizable()->willReturn(false);
        $color->isScopable()->willReturn(false);

        $attributeRepository->findOneByIdentifier('color')->willReturn($color);

        $intCodeAttribute->getCode()->willReturn('1');
        $intCodeAttribute->getType()->willReturn('pim_catalog_text');
        $intCodeAttribute->isLocalizable()->willReturn(false);
        $intCodeAttribute->isScopable()->willReturn(false);

        $attributeRepository->findOneByIdentifier('1')->willReturn($intCodeAttribute);

        $expectedParentAttributes = [$name, $intCodeAttribute];

        $expectedAttributes = [$sku, $color];

        $parentEntity->getParent()->willReturn(null);
        $entity->getParent()->willReturn($parentEntity);

        $attributesProvider->getAttributes($parentEntity)->willReturn($expectedParentAttributes);
        $attributesProvider->getAttributes($entity)->willReturn($expectedAttributes);

        $valuesResolver->resolveEligibleValues(['sku' => $sku, 'name' => $name, 'color' => $color, 1 => $intCodeAttribute])
            ->willReturn([
                [
                    'attribute' => 'sku',
                    'type' => 'pim_catalog_identifier',
                    'locale' => null,
                    'scope' => null
                ],
                [
                    'attribute' => 'name',
                    'type' => 'pim_catalog_text',
                    'locale' => 'fr_FR',
                    'scope' => null
                ],
                [
                    'attribute' => 'name',
                    'type' => 'pim_catalog_text',
                    'locale' => 'en_US',
                    'scope' => null
                ],
                [
                    'attribute' => 'color',
                    'type' => 'pim_catalog_simpleselect',
                    'locale' => null,
                    'scope' => null
                ],
                [
                    'attribute' => '1',
                    'type' => 'pim_catalog_text',
                    'locale' => null,
                    'scope' => null
                ]
            ]);

        // get existing values
        $skuValue->getAttributeCode()->willReturn('sku');
        $skuValue->getLocaleCode()->willReturn(null);
        $skuValue->getScopeCode()->willReturn(null);

        $nameFRValue->getAttributeCode()->willReturn('name');
        $nameFRValue->getLocaleCode()->willReturn('fr_FR');
        $nameFRValue->getScopeCode()->willReturn(null);

        $nameENValue->getAttributeCode()->willReturn('name');
        $nameENValue->getLocaleCode()->willReturn('en_US');
        $nameENValue->getScopeCode()->willReturn(null);

        $colorValue->getAttributeCode()->willReturn('color');
        $colorValue->getLocaleCode()->willReturn(null);
        $colorValue->getScopeCode()->willReturn(null);

        $intCodeValue->getAttributeCode()->willReturn('1');
        $intCodeValue->getLocaleCode()->willReturn(null);
        $intCodeValue->getScopeCode()->willReturn(null);

        $entity->getValues()->willReturn([$skuValue, $nameFRValue, $nameENValue, $colorValue, $intCodeValue]);

        $entityWithValuesBuilder->addOrReplaceValue(Argument::cetera())->shouldNotBeCalled();

        $this->fillMissingValues($entity);
    }
}
