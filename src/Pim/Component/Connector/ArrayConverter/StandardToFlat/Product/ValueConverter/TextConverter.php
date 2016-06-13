<?php

namespace Pim\Component\Connector\ArrayConverter\StandardToFlat\Product\ValueConverter;

use Pim\Component\Connector\ArrayConverter\FlatToStandard\Product\AttributeColumnsResolver;

/**
 * Text array converter.
 * Convert a standard text array format to a flat one.
 *
 * @author    Adrien Pétremann <adrien.petremann@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class TextConverter extends AbstractValueConverter
{
    /**
     * @param AttributeColumnsResolver $columnsResolver
     * @param array                    $supportedFieldType
     */
    public function __construct(AttributeColumnsResolver $columnsResolver, array $supportedFieldType)
    {
        parent::__construct($columnsResolver);

        $this->supportedFieldType = $supportedFieldType;
    }

    /**
     * {@inheritdoc}
     *
     * Convert a standard formatted text field to a flat one.
     *
     * Given a 'name' $field with this $data:
     * [
     *     [
     *         'locale' => 'de_DE',
     *         'scope'  => 'ecommerce',
     *         'data'   => 'Wii U'
     *     ],
     * ]
     *
     * It will return:
     * [
     *     'name-de_DE-ecommerce' => 'Wii U',
     * ]
     */
    public function convert($attributeCode, $data)
    {
        $convertedItem = [];

        foreach ($data as $value) {
            $flatName = $this->columnsResolver->resolveFlatAttributeName(
                $attributeCode,
                $value['locale'],
                $value['scope']
            );

            $convertedItem[$flatName] = (string) $value['data'];
        }

        return $convertedItem;
    }
}
