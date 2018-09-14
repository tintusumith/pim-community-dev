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
class GetProductModelRowsFromIdentifiers implements GetRowsFromIdentifiers
{
    /** @var Connection */
    private $connection;

    /** @var GetColumnsFromIds */
    private $getChildrenCompletenessQuery;

    /** @var GetFilteredProductValues */
    private $getProductValues;

    /**
     * @param Connection               $connection
     * @param GetColumnsFromIds        $getChildrenCompletenessQuery
     * @param GetFilteredProductValues $getProductValues
     */
    public function __construct(
        Connection $connection,
        GetColumnsFromIds $getChildrenCompletenessQuery,
        GetFilteredProductValues $getProductValues
    ) {
        $this->connection = $connection;
        $this->getChildrenCompletenessQuery = $getChildrenCompletenessQuery;
        $this->getProductValues = $getProductValues;
    }

    /**
     * @inheritDoc
     */
    public function fetch(array $identifiers, Context $context): array
    {
        $sql = <<<SQL
            SELECT 
                pm.id,
                pm.code,
                pm.created,
                pm.updated,
                parent.code as parent_code,
                fa.code AS attribute_as_label,
                COALESCE(ft.label, CONCAT('[', f.code, ']')) AS family_label
            FROM
                pim_catalog_product_model pm
                LEFT JOIN pim_catalog_product_model parent ON pm.parent_id = parent.id 
                LEFT JOIN pim_catalog_family_variant fv ON fv.id = pm.family_variant_id 
                LEFT JOIN pim_catalog_family f ON f.id = fv.family_id
                LEFT JOIN pim_catalog_attribute fa ON fa.id = f.label_attribute_id
                LEFT JOIN pim_catalog_family_translation ft ON f.id = ft.foreign_key AND ft.locale = :locale
            WHERE pm.code IN (:identifiers)
SQL;

        $productModels = $this->connection->executeQuery(
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

        $productModelIds = [];
        $attributesAsLabel = [];
        foreach ($productModels as $productModel) {
            $productModelIds[] = (int) $productModel['id'];
            $attributesAsLabel[] = $productModel['attribute_as_label'];
        }
        $attributesAsLabel = array_unique($attributesAsLabel);

        $platform = $this->connection->getDatabasePlatform();
        $childrenCompleteness = $this->getChildrenCompletenessQuery->fetch($productModelIds, $context);

        $attributesToFetch = array_unique(array_merge($context->attributesToDisplay(), $attributesAsLabel));
        $productsValues = $this->getProductValues->fetch($productModelIds, $attributesToFetch, $context->channel(), $context->locale());

        $rows = [];
        foreach ($productModels as $productModel) {
            $productModelId = (int) $productModel['id'];
            $values = $productsValues[$productModelId] ?? [];
            $label = isset($values[$productModel['attribute_as_label']]) ? $values[$productModel['attribute_as_label']]->getData(): null;

            $rows[] = new ReadModel\Row(
                $productModel['code'],
                $productModel['family_label'],
                '',
                null,
                Type::getType(Type::DATETIME)->convertToPhpValue($productModel['created'], $platform),
                Type::getType(Type::DATETIME)->convertToPhpValue($productModel['updated'], $platform),
                $label ?? $productModel['code'],
                $values['image'] ?? null,
                null,
                $childrenCompleteness[$productModelId] ?? [],
                IdEncoder::PRODUCT_MODEL_TYPE,
                $productModelId,
                IdEncoder::encode(IdEncoder::PRODUCT_MODEL_TYPE, $productModelId),
                $productModel['parent_code'],
                new ValueCollection($values)
            );
        }

        return $rows;
    }
}
