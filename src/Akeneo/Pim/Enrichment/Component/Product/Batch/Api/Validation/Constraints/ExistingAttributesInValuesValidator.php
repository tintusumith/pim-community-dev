<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Batch\Api\Validation\Constraints;

use Akeneo\Pim\Enrichment\Component\Product\Batch\Api\Product\Product;
use Doctrine\DBAL\Connection;
use PDO;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ExistingAttributesInValuesValidator extends ConstraintValidator
{
    /** @var Connection */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($product, Constraint $constraint)
    {
        if (!$product instanceof Product) {
            throw new UnexpectedTypeException($constraint, Product::class);
        }

        if (!$constraint instanceof ExistingAttributesInValues) {
            throw new UnexpectedTypeException($constraint, ExistingAttributesInValues::class);
        }

        if (null === $product->values()) {
            return;
        }

        $sql = <<<SQL
            SELECT 
                code
            FROM 
                pim_catalog_attribute a
            WHERE 
                a.code IN (:attribute_codes)
SQL;

        $attributeCodes = $this->getAttributeCodes($product);
        $existingAttributes = $this->connection->executeQuery(
            $sql,
            ['attribute_codes' => $attributeCodes],
            ['attribute_codes' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
        )->fetchAll(PDO::FETCH_COLUMN, 0);

        $notExistingCode = array_diff($attributeCodes, $existingAttributes);

        if (!empty($notExistingCode)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }

    private function getAttributeCodes(Product $product): array
    {
        return array_keys($product->values()->indexedByAttribute());
    }
}
