<?php

declare(strict_types=1);

namespace Pim\Bundle\DataGridBundle\Storage\Sql;

use Akeneo\Pim\Enrichment\Component\Product\Grid\Context;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetColumnsFromIds;
use Doctrine\DBAL\Connection;

/**
 * @author    Laurent Petard <laurent.petard@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @todo rename ?
 */
class GetProductModelsChildrenCompleteness implements GetColumnsFromIds
{
    /** @var Connection */
    private $connection;

    /**
     * @param $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(array $productModelIds, Context $context): array
    {
        $sql = <<<SQL
            SELECT pm.id,
              COUNT(p_child.id) AS nb_children,
              SUM(IF(completeness.ratio = 100, 1, 0)) AS nb_children_complete
            FROM pim_catalog_product_model pm
                LEFT JOIN pim_catalog_product_model pm_child ON pm_child.parent_id = pm.id
                LEFT JOIN pim_catalog_product p_child ON p_child.product_model_id = COALESCE(pm_child.id, pm.id)
                LEFT JOIN pim_catalog_completeness completeness ON completeness.product_id = p_child.id
                LEFT JOIN pim_catalog_channel channel ON channel.id = completeness.channel_id
                LEFT JOIN pim_catalog_locale locale ON locale.id = completeness.locale_id
            WHERE pm.id IN (:productModelIds)
            AND channel.code = :channel
            AND locale.code = :locale
            GROUP BY pm.id
SQL;

        $results = $this->connection->executeQuery(
            $sql,
            [
                'productModelIds' => $productModelIds,
                'channel' => $context->channel(),
                'locale' => $context->locale(),
            ],
            [
                'productModelIds' => Connection::PARAM_INT_ARRAY,
                'channel' => \PDO::PARAM_STR,
                'locale' => \PDO::PARAM_STR,
            ]
        )->fetchAll();

        $childrenCompleteness = [];
        foreach ($results as $resultRow) {
            $childrenCompleteness[(int) $resultRow['id']] = [
                'total' => (int) $resultRow['nb_children'],
                'complete' => (int) $resultRow['nb_children_complete'],
            ];
        }

        return $childrenCompleteness;
    }
}
