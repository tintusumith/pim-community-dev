<?php

namespace Oro\Bundle\PimDataGridBundle\Controller;

use Oro\Bundle\DataGridBundle\Datagrid\Manager;
use Oro\Bundle\DataGridBundle\Datagrid\RequestParameters;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Datagrid controller
 *
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DatagridController
{
    const DATAGRID_ROUTE = 'oro_datagrid_index';

    /** @var Manager */
    private $manager;

    /** @var RequestParameters */
    private $requestParams;

    /** @var RouterInterface */
    private $router;

    /**
     * @param Manager           $manager
     * @param RequestParameters $requestParams
     * @param RouterInterface   $router
     */
    public function __construct(
        Manager $manager,
        RequestParameters $requestParams,
        RouterInterface $router
    ) {
        $this->manager = $manager;
        $this->requestParams = $requestParams;
        $this->router = $router;
    }

    /**
     * Load a datagrid
     *
     * @param Request $request
     * @param string  $alias
     *
     * @return JsonResponse
     */
    public function loadAction(Request $request, $alias)
    {
        $params = $request->get('params', []);
        $datagrid = $this->manager->getDatagrid($alias);
        $metaData = $datagrid->getMetadata();

        $metaData->offsetAddToArray('options', ['url' => $this->generateDatagridUrl($alias, $params)]);

        return new JsonResponse([
            'metadata' => $metaData->toArray(),
            'data' => json_encode($datagrid->getData()->toArray()), // @todo: fix front to not json encode
        ]);
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @return string
     */
    protected function generateDatagridUrl(string $name, array $params): string
    {
        $additional = $this->requestParams->getRootParameterValue();

        $params = [
            $name      => array_merge($params, $additional),
            'gridName' => $name
        ];

        return $this->router->generate(self::DATAGRID_ROUTE, $params);
    }
}
