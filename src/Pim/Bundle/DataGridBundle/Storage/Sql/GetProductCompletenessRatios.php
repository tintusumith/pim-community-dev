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
 * @todo do a specific interface ?
 */
class GetProductCompletenessRatios implements GetColumnsFromIds
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
        $sql = <<<SQL
          SELECT completeness.product_id, completeness.ratio
          FROM pim_catalog_completeness completeness
          JOIN pim_catalog_channel channel ON channel.id = completeness.channel_id
          JOIN pim_catalog_locale locale ON locale.id = completeness.locale_id
          WHERE completeness.product_id IN (:productIds)
          AND channel.code = :channel
          AND locale.code = :locale
SQL;

        $results = $this->connection->executeQuery(
            $sql,
            [
                'productIds' => $productIds,
                'channel' => $context->channel(),
                'locale' => $context->locale(),
            ],
            [
                'productIds' => Connection::PARAM_INT_ARRAY,
                'channel' => \PDO::PARAM_STR,
                'locale' => \PDO::PARAM_STR,
            ]
        )->fetchAll();

        $completenessRatios = [];
        foreach ($results as $resultRow) {
            $completenessRatios[(int) $resultRow['product_id']] = (int) $resultRow['ratio'];
        }

        return $completenessRatios;
    }
}
