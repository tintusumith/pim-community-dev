<?php

declare(strict_types=1);

namespace Pim\Bundle\DataGridBundle\Storage\Sql;

use Akeneo\Pim\Enrichment\Component\Product\Factory\ValueFactory;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetFilteredProductValues;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Doctrine\DBAL\Connection;

/**
 * @author    Laurent Petard <laurent.petard@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @todo rename
 */
class GetProductValues implements GetFilteredProductValues
{
    /** @var Connection */
    private $connection;

    /** @var AttributeRepositoryInterface */
    private $attributeRepository;

    /** @var ValueFactory */
    private $valueFactory;

    /**
     * @param Connection                   $connection
     * @param AttributeRepositoryInterface $attributeRepository
     *
     * @todo Do not depend on external components
     */
    public function __construct(
        Connection $connection,
        AttributeRepositoryInterface $attributeRepository,
        ValueFactory $valueFactory
    ) {
        $this->connection = $connection;
        $this->attributeRepository = $attributeRepository;
        $this->valueFactory = $valueFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(array $productIds, array $attributesCodes, string $channel, string $locale): array
    {
        if (empty($productIds) || empty($attributesCodes)) {
            return [];
        }

        $attributes = $this->getAttributes($attributesCodes);
        $rawValues = $this->fetchRawValues($productIds, $attributes, $channel, $locale);

        $productValues = [];
        foreach ($rawValues as $rawValue) {
            $productId = (int) $rawValue['id'];
            $productValues[$productId] = [];

            foreach ($attributes as $attributeCode => $attribute) {
                $productValues[$productId][$attributeCode] = $this->valueFactory->create(
                    $attribute,
                    $attribute->isScopable() ? $$channel : null,
                    $attribute->isLocalizable() ? $locale : null,
                    isset($rawValue[$attributeCode]) ? json_decode($rawValue[$attributeCode], true) : null
                );
            }
        }

        return $productValues;
    }

    /**
     * @param array  $productIds
     * @param array  $attributes
     * @param string $channel
     * @param string $locale
     *
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return array
     */
    private function fetchRawValues(array $productIds, array $attributes, string $channel, string $locale): array
    {
        $sqlValues = [];
        foreach ($attributes as $attribute) {
            $sqlValues[] = sprintf(
                'JSON_EXTRACT(raw_values, \'$."%1$s"."%2$s"."%3$s"\') AS `%1$s`',
                $attribute->getCode(),
                $attribute->isScopable() ? $channel : '<all_channels>',
                $attribute->isLocalizable() ? $locale : '<all_locales>'
            );
        }
        $sqlValues = implode(', ', $sqlValues);

        $sql = <<<SQL
            SELECT id, $sqlValues FROM (
                SELECT p.id,
                  JSON_MERGE(p.raw_values, COALESCE(parent.raw_values, '{}'), COALESCE(grand_parent.raw_values, '{}')) AS raw_values
                FROM pim_catalog_product p
                LEFT JOIN pim_catalog_product_model parent on p.product_model_id = parent.id
                LEFT JOIN pim_catalog_product_model grand_parent on parent.parent_id = grand_parent.id
                WHERE p.id IN (:productIds)
            ) all_raw_values
SQL;

        $rawValues = $this->connection->executeQuery(
            $sql,
            ['productIds' => $productIds],
            ['productIds' => Connection::PARAM_INT_ARRAY]
        )->fetchAll();

        return $rawValues;
    }

    /**
     * @param array $attributesCodes
     *
     * @return array
     */
    private function getAttributes(array $attributesCodes): array
    {
        $attributes = [];
        foreach ($attributesCodes as $attributeCode) {
            $attributes[$attributeCode] = $this->attributeRepository->findOneByIdentifier($attributeCode);
        }

        return $attributes;
    }
}
