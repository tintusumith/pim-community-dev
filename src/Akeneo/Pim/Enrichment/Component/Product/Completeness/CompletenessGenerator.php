<?php

namespace Akeneo\Pim\Enrichment\Component\Product\Completeness;

use Akeneo\Channel\Component\Model\ChannelInterface;
use Akeneo\Channel\Component\Model\LocaleInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\CompletenessInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderInterface;
use Doctrine\Common\Collections\Collection;

/**
 * Simple object version of the completeness generator.
 *
 * In this implementation, methods that generate missing completenesses do NOT save the products.
 * Complenesses are only added to the products in memory. The save of the products (and of the compltenesses)
 * should be handled by the a Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface service.
 *
 * @author    Julien Janvier (j.janvier@akeneo.com)
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CompletenessGenerator implements CompletenessGeneratorInterface
{
    /** @var ProductQueryBuilderFactoryInterface */
    protected $pqbFactory;

    /** @var CompletenessCalculatorInterface */
    protected $completenessCalculator;

    /**
     * @param ProductQueryBuilderFactoryInterface $pqbFactory
     * @param CompletenessCalculatorInterface     $completenessCalculator
     */
    public function __construct(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        CompletenessCalculatorInterface $completenessCalculator
    ) {
        $this->pqbFactory = $pqbFactory;
        $this->completenessCalculator = $completenessCalculator;
    }

    /**
     * {@inheritdoc}
     */
    public function generateMissingForProduct(ProductInterface $product)
    {
        $this->calculateProductCompletenesses($product);
    }

    /**
     * @param ChannelInterface $channel
     * @param LocaleInterface  $locale
     * @param array            $filters
     *
     * @return ProductQueryBuilderInterface
     */
    protected function createProductQueryBuilderForMissings(
        ChannelInterface $channel = null,
        LocaleInterface $locale = null,
        ?array $filters = null
    ) {
        $defaultFilters = [
            ['field' => 'completeness', 'operator' => Operators::IS_EMPTY, 'value' => null],
            ['field' => 'family', 'operator' => Operators::IS_NOT_EMPTY, 'value' => null],
        ];

        $options = ['filters' => $filters ?? $defaultFilters];

        if (null !== $channel) {
            $options['default_scope'] = $channel->getCode();
        }
        if (null !== $locale) {
            $options['default_locale'] = $locale->getCode();
        }

        return $this->pqbFactory->create($options);
    }

    /**
     * Calculates current product completenesses.
     * Completenesses are updated for the existing ones, others are added/removed.
     *
     * @param ProductInterface $product
     */
    protected function calculateProductCompletenesses(ProductInterface $product)
    {
        $completenessCollection = $product->getCompletenesses();

        $newCompletenesses = $this->completenessCalculator->calculate($product);

        $this->updateExistingCompletenesses($completenessCollection, $newCompletenesses);

        $completenessLocaleAndChannelCodes = [];
        foreach ($completenessCollection as $updatedCompleteness) {
            $completenessLocaleAndChannelCodes[] =
                $updatedCompleteness->getLocale()->getId().'/'.$updatedCompleteness->getChannel()->getId();
        }

        $newLocalesChannels = [];
        foreach ($newCompletenesses as $newCompleteness) {
            $newLocalesChannels[] =
                $newCompleteness->getLocale()->getId().'/'.$newCompleteness->getChannel()->getId();
        }

        $localeAndChannelCodesOfCompletenessesToAdd = array_diff(
            $newLocalesChannels,
            $completenessLocaleAndChannelCodes
        );
        $this->addNewCompletenesses(
            $completenessCollection,
            $newCompletenesses,
            $localeAndChannelCodesOfCompletenessesToAdd
        );

        $localeAndChannelCodesOfCompletenessesToRemove = array_diff(
            $completenessLocaleAndChannelCodes,
            $newLocalesChannels
        );
        $this->removeOutdatedCompletenesses($completenessCollection, $localeAndChannelCodesOfCompletenessesToRemove);
    }

    /**
     * @param Collection              $completenessCollection
     * @param CompletenessInterface[] $newCompletenesses
     */
    private function updateExistingCompletenesses(Collection $completenessCollection, array $newCompletenesses)
    {
        foreach ($completenessCollection as $currentCompleteness) {
            foreach ($newCompletenesses as $newCompleteness) {
                if ($newCompleteness->getLocale()->getId() === $currentCompleteness->getLocale()->getId() &&
                    $newCompleteness->getChannel()->getId() === $currentCompleteness->getChannel()->getId()
                ) {
                    $currentCompleteness->setRatio($newCompleteness->getRatio());
                    $currentCompleteness->setMissingCount($newCompleteness->getMissingCount());
                    $currentCompleteness->setRequiredCount($newCompleteness->getRequiredCount());
                }
            }
        }
    }

    /**
     * @param Collection              $completenessCollection
     * @param CompletenessInterface[] $newCompletenesses
     * @param string[]                $localeAndChannelCodesOfCompletenessesToAdd
     */
    private function addNewCompletenesses(
        Collection $completenessCollection,
        array $newCompletenesses,
        array $localeAndChannelCodesOfCompletenessesToAdd
    ) {
        foreach ($localeAndChannelCodesOfCompletenessesToAdd as $completenessLocaleAndChannel) {
            [$localeCode, $channelCode] = explode('/', $completenessLocaleAndChannel);

            foreach ($newCompletenesses as $newCompleteness) {
                if ($newCompleteness->getLocale()->getId() === (int) $localeCode
                    && $newCompleteness->getChannel()->getId() === (int) $channelCode
                ) {
                    $completenessCollection->add($newCompleteness);
                }
            }
        }
    }

    /**
     * @param Collection              $completenessCollection
     * @param CompletenessInterface[] $localeAndChannelCodesOfCompletenessesToRemove
     */
    private function removeOutdatedCompletenesses(
        Collection $completenessCollection,
        array $localeAndChannelCodesOfCompletenessesToRemove
    ) {
        foreach ($localeAndChannelCodesOfCompletenessesToRemove as $completenessLocaleAndChannel) {
            [$localeCode, $channelCode] = explode('/', $completenessLocaleAndChannel);

            foreach ($completenessCollection as $currentCompleteness) {
                if ($currentCompleteness->getLocale()->getId() === (int) $localeCode
                    && $currentCompleteness->getChannel()->getId() === (int) $channelCode
                ) {
                    $completenessCollection->removeElement($currentCompleteness);
                }
            }
        }
    }
}
