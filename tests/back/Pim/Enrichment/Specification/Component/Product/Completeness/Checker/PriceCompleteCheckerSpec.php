<?php

namespace Specification\Akeneo\Pim\Enrichment\Component\Product\Completeness\Checker;

use Akeneo\Pim\Enrichment\Component\Product\Completeness\Checker\ValueCompleteCheckerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Channel\Component\Model\ChannelInterface;
use Akeneo\Channel\Component\Model\CurrencyInterface;
use Akeneo\Channel\Component\Model\LocaleInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductPriceInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Prophecy\Argument;

class PriceCompleteCheckerSpec extends ObjectBehavior
{
    public function it_is_a_completeness_checker()
    {
        $this->shouldImplement(ValueCompleteCheckerInterface::class);
    }

    public function it_suports_price_collection_attribute(
        ValueInterface $value,
        AttributeInterface $attribute,
        ChannelInterface $channel,
        LocaleInterface $locale
    ) {
        $value->getAttribute()->willReturn($attribute);
        $attribute->getType()->willReturn('pim_catalog_price_collection');
        $this->supportsValue($value, $channel, $locale)->shouldReturn(true);

        $attribute->getType()->willReturn('other');
        $this->supportsValue($value, $channel, $locale)->shouldReturn(false);
    }

    public function it_successfully_checks_complete_price_collection(
        ValueInterface $value,
        ChannelInterface $channel,
        LocaleInterface $locale,
        ArrayCollection $arrayCollection,
        CurrencyInterface $currency1,
        CurrencyInterface $currency2,
        ProductPriceInterface $price1,
        ProductPriceInterface $price2
    ) {
        $channel->getCurrencies()->willReturn($arrayCollection);
        $arrayCollection->map(Argument::any())->willReturn(['USD', 'EUR']);

        $arrayCollection->toArray()->willReturn([$currency1, $currency2]);

        $currency1->getCode()->willReturn('USD');
        $currency2->getCode()->willReturn('EUR');

        $price1->getCurrency()->willReturn('USD');
        $price2->getCurrency()->willReturn('EUR');
        $price1->getData()->willReturn(666);
        $price2->getData()->willReturn(777);

        $value->getData()->willReturn([$price1, $price2]);
        $this->isComplete($value, $channel, $locale)->shouldReturn(true);
    }

    public function it_successfully_checks_incomplete_price_collection(
        ValueInterface $value,
        ChannelInterface $channel,
        LocaleInterface $locale,
        ArrayCollection $arrayCollection,
        CurrencyInterface $currency1,
        CurrencyInterface $currency2,
        ProductPriceInterface $price1
    ) {
        $channel->getCurrencies()->willReturn($arrayCollection);
        $arrayCollection->map(Argument::any())->willReturn(['USD', 'EUR']);

        $currency1->getCode()->willReturn('USD');

        $price1->getCurrency()->willReturn('USD');
        $price1->getData()->willReturn(null);

        $value->getData()->willReturn([$price1]);
        $this->isComplete($value, $channel, $locale)->shouldReturn(false);
    }
}
