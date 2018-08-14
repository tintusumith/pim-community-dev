<?php

namespace Akeneo\Pim\Enrichment\Bundle\Command;

use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Cleanup reference to a deleted multiselect option
 *
 * @author    Benoit Jacquemont <nicolas@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CleanupDeletedMultiselectOptionCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pim:structure:cleanup:deleted-multiselect-_option')
            ->setDescription('Cleanup reference to a deleted multiselect option')
            ->addArgument(
                'attribute_code',
                InputArgument::REQUIRED,
                'The attribute code'
            )
            ->addArgument(
                'option_code',
                InputArgument::REQUIRED,
                'The option code'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $attributeCode = $input->getArgument('attribute_code');
        $optionCode = $this->getArgument('option_code');

        // Find products from ES with the option code
        // Loop with all product ids
        // Remove the option from the attribute using 1 SQL query
        // JSON_REMOVE JSON_SEARCH
        // Cleanup the empty attribute if necessary
        // JSON_REMOVE with WHERE on JSON_LENGTH=0

        // Update product versioning

        // Recompute and reindex completeness if needed (attribute part of the completeness requirements and only option of products)

        // Cleanup product index

        /* Question:
             After remove, during this command execution
                 - What if somebody adds back the same option code (from UI or import), and somebody adds it to a product ?
                 => At the start of the command, check for the option code existence, exits if it still exists,
                    and just after that get all products ids that have the code. It will reduce the chance to change a product
                    that has been edited with the new option
        */
    }
}
