<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\tests\Integration\Storage\ElasticsearchAndSql\ProductGrid;

use Akeneo\Pim\Enrichment\Bundle\tests\Integration\Storage\Sql\ProductGrid\AssertRows;
use Akeneo\Pim\Enrichment\Bundle\tests\Integration\Storage\Sql\ProductGrid\ProductGridFixturesLoader;
use Akeneo\Pim\Enrichment\Component\Product\Grid\Query\FetchProductAndProductModelRowsParameters;
use Akeneo\Pim\Enrichment\Component\Product\Grid\ReadModel\Row;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueCollection;
use Akeneo\Pim\Enrichment\Component\Product\Value\MediaValue;
use Akeneo\Pim\Enrichment\Component\Product\Value\ScalarValue;
use Akeneo\Pim\Structure\Component\Model\Attribute;
use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\Integration\TestCase;
use PHPUnit\Framework\Assert;

class FetchProductAndProductModelRowsIntegration extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
    }

    public function test_fetch_product_models_from_codes()
    {
        $userId = $this
            ->get('database_connection')
            ->fetchColumn('SELECT id from oro_user where username = "admin"', [], 0);

        $fixturesLoader = new ProductGridFixturesLoader(
            static::$kernel->getContainer(),
            $this->getFixturePath('akeneo.jpg')
        );

        $fixtures = $fixturesLoader->createProductAndProductModels();
        [$rootProductModel, $subProductModel] = $fixtures['product_models'];
        [$product1, $product2] = $fixtures['products'];

        $pqb = $this
            ->get('akeneo.pim.enrichment.query.product_and_product_model_query_builder_from_size_factory.with_product_identifier_cursor')
            ->create(['limit' => 10]);
        $query = $this->get('akeneo.pim.enrichment.product.grid.query.fetch_product_and_product_model_rows');
        $queryParameters = new FetchProductAndProductModelRowsParameters(
            $pqb,
            ['sku', 'a_localizable_image', 'a_scopable_image'],
            'ecommerce', 'en_US',
            (int) $userId
        );

        $rows = $query($queryParameters);

        $anImage = new Attribute();
        $anImage->setCode('an_image');

        $aLocalizableImage = new Attribute();
        $aLocalizableImage->setCode('a_localizable_image');
        $aLocalizableImage->setLocalizable(true);

        $aScopableImage = new Attribute();
        $aScopableImage->setCode('a_scopable_image');
        $aScopableImage->setScopable(true);

        $sku = new Attribute();
        $sku->setCode('sku');

        $akeneoImage = current($this
            ->get('akeneo_file_storage.repository.file_info')
            ->findAll($this->getFixturePath('akeneo.jpg')));

        $expectedRows = [
            Row::fromProduct(
                'baz',
                null,
                [],
                true,
                $product2->getCreated(),
                $product2->getUpdated(),
                null,
                null,
                null,
                $product2->getId(),
                null,
                new ValueCollection([
                    new ScalarValue($sku, null, null, 'baz'),
                    new MediaValue($aLocalizableImage, null, 'en_US', $akeneoImage),
                    new MediaValue($aScopableImage, 'ecommerce', null, $akeneoImage),
                ])
            ),
            Row::fromProductModel(
                'root_product_model',
                'A family A',
                $rootProductModel->getCreated(),
                $rootProductModel->getUpdated(),
                null,
                new MediaValue($anImage, null, null, $akeneoImage),
                $rootProductModel->getId(),
                ['total' => 1, 'complete' => 0],
                null,
                new ValueCollection([
                    new MediaValue($anImage, null, null, $akeneoImage)
                ])
            ),
        ];

        Assert::assertSame(2, $rows->totalCount());
        AssertRows::same($expectedRows, $rows->rows());
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfiguration(): Configuration
    {
        return $this->catalog->useTechnicalCatalog();
    }
}

