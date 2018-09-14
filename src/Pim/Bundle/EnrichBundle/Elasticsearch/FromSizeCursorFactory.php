<?php

namespace Pim\Bundle\EnrichBundle\Elasticsearch;

use Akeneo\Pim\Enrichment\Bundle\Elasticsearch\ProductGridCursor;
use Akeneo\Pim\Enrichment\Component\Product\Grid\Context;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetRowsFromIdentifiers;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Akeneo\Tool\Component\StorageUtils\Cursor\CursorFactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author    Julien Janvier <j.janvier@gmail.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FromSizeCursorFactory implements CursorFactoryInterface
{
    /** @var Client */
    private $searchEngine;

    /** @var int */
    private $pageSize;

    /** @var string */
    private $indexType;

    /** @var GetRowsFromIdentifiers */
    private $getProductRowsFromIdentifiers;

    /** @var GetRowsFromIdentifiers */
    private $getProductModelRowsFromIdentifiers;

    /**
     * @param Client                        $searchEngine
     * @param int                           $pageSize
     * @param string                        $indexType
     */
    public function __construct(
        Client $searchEngine,
        GetRowsFromIdentifiers $getProductRowsFromIdentifiers,
        GetRowsFromIdentifiers $getProductModelRowsFromIdentifiers,
        $pageSize,
        $indexType
    ) {
        $this->searchEngine = $searchEngine;
        $this->pageSize = $pageSize;
        $this->indexType = $indexType;
        $this->getProductRowsFromIdentifiers = $getProductRowsFromIdentifiers;
        $this->getProductModelRowsFromIdentifiers = $getProductModelRowsFromIdentifiers;
    }

    /**
     * {@inheritdoc}
     */
    public function createCursor($queryBuilder, array $options = [])
    {
        $options = $this->resolveOptions($options);

        $queryBuilder['_source'] = array_merge($queryBuilder['_source'], ['document_type']);

        $context = new Context($options['locale'], $options['scope'], $options['attributes_to_display'], $options['properties_to_display']);

        return new ProductGridCursor(
            $this->searchEngine,
            $this->getProductRowsFromIdentifiers,
            $this->getProductModelRowsFromIdentifiers,
            $queryBuilder,
            $context,
            $this->indexType,
            $options['page_size'],
            $options['limit'],
            $options['from']
        );
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined(
            [
                'locale',
                'scope',
                'page_size',
                'limit',
                'from',
                'attributes_to_display',
                'properties_to_display',
            ]
        );
        $resolver->setDefaults(
            [
                'page_size' => $this->pageSize,
                'from' => 0,
                'attributes_to_display' => [],
                'properties_to_display' => [],
            ]
        );
        $resolver->setAllowedTypes('locale', 'string');
        $resolver->setAllowedTypes('scope', 'string');
        $resolver->setAllowedTypes('page_size', 'int');
        $resolver->setAllowedTypes('limit', 'int');
        $resolver->setAllowedTypes('from', 'int');
        $resolver->setAllowedTypes('attributes_to_display', 'array');
        $resolver->setAllowedTypes('properties_to_display', 'array');

        $options = $resolver->resolve($options);

        return $options;
    }
}
