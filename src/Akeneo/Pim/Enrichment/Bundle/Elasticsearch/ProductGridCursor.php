<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Elasticsearch;

use Akeneo\Pim\Enrichment\Component\Product\Grid\Context;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetRowsFromIdentifiers;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Akeneo\Tool\Component\StorageUtils\Cursor\CursorInterface;

/**
 * @todo: find a better name
 */
class ProductGridCursor implements CursorInterface
{
    /** @var array */
    protected $items;

    /** @var int */
    protected $count;

    /** @var array */
    protected $esQuery;

    /** @var string */
    protected $indexType;

    /** @var int */
    protected $pageSize;

    /** @var int */
    protected $initialFrom;

    /** @var int */
    protected $from;

    /** @var int */
    protected $limit;

    /** @var int */
    protected $to;

    /** @var GetRowsFromIdentifiers */
    private $getProductRowsFromIdentifiers;

    /** @var GetRowsFromIdentifiers */
    private $getProductModelRowsFromIdentifiers;

    /** @var Context */
    private $context;

    public function __construct(
        Client $esClient,
        GetRowsFromIdentifiers $getProductRowsFromIdentifiers,
        GetRowsFromIdentifiers $getProductModelRowsFromIdentifiers,
        array $esQuery,
        Context $context,
        $indexType,
        $pageSize,
        $limit,
        $from = 0
    ) {
        $this->esClient = $esClient;
        $this->esQuery = $esQuery;
        $this->indexType = $indexType;
        $this->pageSize = $pageSize;
        $this->limit = $limit;
        $this->from = $from;
        $this->initialFrom = $from;
        $this->to = $this->from + $this->limit;
        $this->getProductRowsFromIdentifiers = $getProductRowsFromIdentifiers;
        $this->getProductModelRowsFromIdentifiers = $getProductModelRowsFromIdentifiers;
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        if (null === $this->items) {
            $this->rewind();
        }

        return current($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        if (null === $this->items) {
            $this->rewind();
        }

        return key($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        if (null === $this->items) {
            $this->rewind();
        }

        return !empty($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if (null === $this->items) {
            $this->rewind();
        }

        return $this->count;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        if (false === next($this->items)) {
            $this->from += count($this->items);
            $this->items = $this->getNextItems($this->esQuery);
            reset($this->items);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->from = $this->initialFrom;
        $this->to = $this->from + $this->limit;
        $this->items = $this->getNextItems($this->esQuery);
        reset($this->items);
    }

    /**
     * Get the next items.
     *
     * @param array $esQuery
     *
     * @return array
     */
    protected function getNextItems(array $esQuery): array
    {
        $identifierResults = $this->getNextIdentifiers($esQuery);
        if ($identifierResults->isEmpty()) {
            return [];
        }

        $productRows = $this->getProductRowsFromIdentifiers->fetch($identifierResults->getProductIdentifiers(), $this->context);
        $productModelRows = $this->getProductModelRowsFromIdentifiers->fetch($identifierResults->getProductModelIdentifiers(), $this->context);
        $rows = array_merge($productRows, $productModelRows);

        $sortedRows = [];
        foreach ($identifierResults->all() as $identifierResult) {
            foreach ($rows as $row) {
                if ($identifierResult->getIdentifier() === $row->identifier()) {
                    $sortedRows[] = $row;
                }
            }
        }

        return $sortedRows;
    }


    private function getNextIdentifiers(array $esQuery): IdentifierResults
    {
        $size = ($this->to - $this->from) > $this->pageSize ? $this->pageSize : ($this->to - $this->from);
        $esQuery['size'] = $size;
        $identifiers = new IdentifierResults();

        if (0 === $esQuery['size']) {
            return $identifiers;
        }

        $sort = ['_uid' => 'asc'];

        if (isset($esQuery['sort'])) {
            $sort = array_merge($esQuery['sort'], $sort);
        }

        $esQuery['sort'] = $sort;
        $esQuery['from'] = $this->from;

        $response = $this->esClient->search($this->indexType, $esQuery);
        $this->count = $response['hits']['total'];

        foreach ($response['hits']['hits'] as $hit) {
            $identifiers->add($hit['_source']['identifier'], $hit['_source']['document_type']);
        }

        return $identifiers;
    }
}
