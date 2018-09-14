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
 */
class GetProductModelValues implements GetFilteredProductValues
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
    public function fetch(array $productModelIds, array $attributesCodes, string $channel, string $locale): array
    {
        if (empty($productModelIds) || empty($attributesCodes)) {
            return [];
        }

        $attributes = $this->getAttributes($attributesCodes);
        $rawValues = $this->fetchRawValues($productModelIds, $attributes, $channel, $locale);

        $productModelValues = [];
        foreach ($rawValues as $rawValue) {
            $productId = (int) $rawValue['id'];
            $productModelValues[$productId] = [];

            foreach ($attributes as $attributeCode => $attribute) {
                $productModelValues[$productId][$attributeCode] = $this->valueFactory->create(
                    $attribute,
                    $attribute->isScopable() ? $$channel : null,
                    $attribute->isLocalizable() ? $locale : null,
                    isset($rawValue[$attributeCode]) ? json_decode($rawValue[$attributeCode], true) : null
                );
            }
        }

        return $productModelValues;
    }

    /**
     * @param array  $productModelIds
     * @param array  $attributes
     * @param string $channel
     * @param string $locale
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function fetchRawValues(array $productModelIds, array $attributes, string $channel, string $locale): array
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
                SELECT pm.id,
                  JSON_MERGE(pm.raw_values, COALESCE(parent.raw_values, '{}')) AS raw_values
                FROM pim_catalog_product_model pm
                LEFT JOIN pim_catalog_product_model parent on pm.parent_id = parent.id
                WHERE pm.id IN (:productModelIds)
            ) all_raw_values
SQL;

        $rawValues = $this->connection->executeQuery(
            $sql,
            ['productModelIds' => $productModelIds],
            ['productModelIds' => Connection::PARAM_INT_ARRAY]
        )->fetchAll();

        return $rawValues;
    }

    /**
     * @param array $attributesCodes
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
