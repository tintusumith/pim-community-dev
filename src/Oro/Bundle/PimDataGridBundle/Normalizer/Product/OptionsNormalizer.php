<?php

namespace Oro\Bundle\PimDataGridBundle\Normalizer\Product;

use Akeneo\Pim\Enrichment\Component\Product\Value\OptionsValueInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\CachedObjectRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author    Marie Bochu <marie.bochu@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class OptionsNormalizer implements NormalizerInterface
{
    /** @var CachedObjectRepositoryInterface */
    protected $attributeOptionRepository;

    public function __construct(CachedObjectRepositoryInterface $attributeOptionRepository)
    {
        $this->attributeOptionRepository = $attributeOptionRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($optionsValue, $format = null, array $context = [])
    {
        $locale = isset($context['data_locale']) ? $context['data_locale'] : null;
        $attributeCode = $optionsValue->getAttributeCode();

        $labels = [];
        foreach ($optionsValue->getData() as $optionCode) {
            $option = $this->attributeOptionRepository->findOneByIdentifier($attributeCode.'.'.$optionCode);

            $translation = $option->getTranslation($locale);
            $labels[] = null !== $translation->getValue() ? $translation->getValue() : sprintf('[%s]', $option->getCode());
        }

        sort($labels);

        return [
            'locale' => $optionsValue->getLocaleCode(),
            'scope'  => $optionsValue->getScopeCode(),
            'data'   => implode(', ', $labels)
        ];
    }

    /**
     *
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return 'datagrid' === $format && $data instanceof OptionsValueInterface;
    }
}
