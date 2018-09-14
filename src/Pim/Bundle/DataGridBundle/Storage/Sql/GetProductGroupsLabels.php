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
 * @todo Find a better name and namespace
 */
class GetProductGroupsLabels implements GetColumnsFromIds
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

    public function fetch(array $productIds, Context $context): array
    {
        if (! in_array('groups', $context->propertiesToDisplay())) {
            return [];
        }

        $sql = <<<SQL
          SELECT gp.product_id,
            GROUP_CONCAT(COALESCE(gt.label, CONCAT('[', g.code, ']')) SEPARATOR ', ') AS labels
          FROM pim_catalog_group_product gp 
          JOIN akeneo_pim.pim_catalog_group g ON g.id = gp.group_id
          LEFT JOIN pim_catalog_group_translation gt ON g.id = gt.foreign_key AND gt.locale = :locale
          WHERE gp.product_id IN (:productIds)
          GROUP BY gp.product_id;
SQL;

        $results = $this->connection->executeQuery(
            $sql,
            [
                'productIds' => $productIds,
                'locale' => $context->locale(),
            ],
            [
                'productIds' => Connection::PARAM_INT_ARRAY,
                'locale' => \PDO::PARAM_STR
            ]
        )->fetchAll();

        $groupLabels = [];
        foreach ($results as $productGroup) {
            $groupLabels[(int) $productGroup['product_id']] = $productGroup['labels'];
        }

        return $groupLabels;
    }
}
