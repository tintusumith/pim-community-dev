<?php

declare(strict_types=1);

namespace Pim\Bundle\DataGridBundle\Storage\Sql;

use Akeneo\Pim\Enrichment\Component\Product\Grid\Context;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueCollection;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetColumnsFromIds;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetFilteredProductValues;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetRowsFromIdentifiers;
use Akeneo\Pim\Enrichment\Component\Product\Grid\ReadModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Oro\Bundle\PimDataGridBundle\Normalizer\IdEncoder;

/**
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class GetProductRowsFromIdentifiers implements GetRowsFromIdentifiers
{
    /** @var Connection */
    private $connection;

    /** @var GetColumnsFromIds */
    private $getGroupsLabelsQuery;

    /** @var GetFilteredProductValues */
    private $getProductValues;

    /** @var GetColumnsFromIds */
    private $getCompletenessRatios;

    /**
     * @param Connection               $connection
     * @param GetColumnsFromIds        $getGroupsLabelsQuery
     * @param GetFilteredProductValues $getProductValues
     * @param GetColumnsFromIds        $getCompletenessRatios
     */
    public function __construct(
        Connection $connection,
        GetColumnsFromIds $getGroupsLabelsQuery,
        GetFilteredProductValues $getProductValues,
        GetColumnsFromIds $getCompletenessRatios
    ) {
        $this->connection = $connection;
        $this->getGroupsLabelsQuery = $getGroupsLabelsQuery;
        $this->getProductValues = $getProductValues;
        $this->getCompletenessRatios = $getCompletenessRatios;
    }

    /**
     * @inheritDoc
     */
    public function fetch(array $identifiers, Context $context): array
    {
        $sql = <<<SQL
            SELECT 
                p.id,
                p.identifier,
                p.is_enabled,
                p.created,
                p.updated,
                pm.code as product_model_code,
                fa.code AS attribute_as_label,
                COALESCE(ft.label, CONCAT('[', f.code, ']')) AS family_label
            FROM
                pim_catalog_product p
                LEFT JOIN pim_catalog_product_model pm ON p.product_model_id = pm.id 
                LEFT JOIN pim_catalog_family f ON p.family_id = f.id
                LEFT JOIN pim_catalog_attribute fa ON fa.id = f.label_attribute_id
                LEFT JOIN pim_catalog_family_translation ft ON f.id = ft.foreign_key AND ft.locale = :locale
            WHERE identifier IN (:identifiers)
SQL;

        $products = $this->connection->executeQuery(
            $sql,
            [
                'identifiers' => $identifiers,
                'locale' => $context->locale(),
            ],
            [
                'identifiers' => Connection::PARAM_STR_ARRAY,
                'locale' => \PDO::PARAM_STR
            ]
        )->fetchAll();

        $productIds = [];
        $attributesAsLabel = [];
        foreach ($products as $product) {
            $productIds[] = (int) $product['id'];
            $attributesAsLabel[] = $product['attribute_as_label'];
        }
        $attributesAsLabel = array_unique($attributesAsLabel);

        $groups = [];
        if (isset($context->propertiesToDisplay()['groups'])) {
            $groups = $this->getGroupsLabelsQuery->fetch($productIds, $context);
        }

        $attributesToFetch = array_unique(array_merge($context->attributesToDisplay(), $attributesAsLabel));
        $productsValues = $this->getProductValues->fetch($productIds, $attributesToFetch, $context->channel(), $context->locale());
        $completenessRatios = $this->getCompletenessRatios->fetch($productIds, $context);

        $platform = $this->connection->getDatabasePlatform();

        $rows = [];
        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $values = $productsValues[$productId] ?? [];

            $rows[] = new ReadModel\Row(
                $product['identifier'],
                $product['family_label'],
                $groups[$productId] ?? '',
                Type::getType(Type::BOOLEAN)->convertToPHPValue($product['is_enabled'], $platform),
                Type::getType(Type::DATETIME)->convertToPhpValue($product['created'], $platform),
                Type::getType(Type::DATETIME)->convertToPhpValue($product['updated'], $platform),
                isset($values[$product['attribute_as_label']]) ? (string) $values[$product['attribute_as_label']] : $product['identifier'],
                $values['image'] ?? null,
                $completenessRatios[$productId] ?? 0,
                [],
                IdEncoder::PRODUCT_TYPE,
                $productId,
                IdEncoder::encode(IdEncoder::PRODUCT_TYPE, $productId),
                $product['product_model_code'],
                new ValueCollection($values)
            );
        }

        return $rows;
    }
}
