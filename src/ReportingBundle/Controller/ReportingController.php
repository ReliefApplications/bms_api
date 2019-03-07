<?php

namespace ReportingBundle\Controller;

use BeneficiaryBundle\Utils\ExportCSVService;
use CommonBundle\Utils\ExportService;
use phpDocumentor\Reflection\TypeResolver;
use ReportingBundle\Utils\Formatters\Formatter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\Annotations as Rest;

use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;

use ReportingBundle\Entity\ReportingIndicator;

/**
 * Class ReportingController
 * @package ReportingBundle\Controller
 */
class ReportingController extends Controller
{

     /**
     * Send data formatted corresponding to code to display it in front
     * @Rest\Post("/indicators/serve/{id}")
     * 
     * @SWG\Tag(name="Reporting")
     * 
     * @SWG\Parameter(
     *     name="Project",
     *     in="body",
     *     required=true,
     *     @Model(type=ReportingIndicator::class)
     * )
     * 
     * @SWG\Response(
     *      response=200,
     *          description="Get data reporting",
     *          @SWG\Schema(
     *              type="array",
     *              @SWG\Items(ref=@Model(type=ReportingIndicator::class)) 
     *          )   
     * )
     * 
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     * 
     * @param ReportingIndicator $indicator
     * @param Request $request
     * @return Response
     */
    public function serveAction(Request $request, ReportingIndicator $indicator)
    {
        $filters = $request->request->get('filters');
        $contentJson = $request->request->all();
        $filters['country'] = $contentJson['__country'];

        try {   
            $dataComputed = $this->get('reporting.computer')->compute($indicator, $filters);
            $dataFormatted = $this->get('reporting.formatter')->format(Formatter::DefaultFormat, $dataComputed, $indicator->getGraph());
        }
        catch (\Exception $e)
        {
            return new Response($e->getMessage(), $e->getCode() > 200 ? $e->getCode() : Response::HTTP_BAD_REQUEST);
        }
        return new JsonResponse($dataFormatted, Response::HTTP_OK);
    }


    /**
     * Send list of all indicators to display in front
     * @Rest\Post("/indicators")
     *
     * @SWG\Tag(name="Reporting")
     *
     * @SWG\Response(
     *      response=200,
     *          description="Get code reporting",
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @return Response
     */
    public function getAction()
    {
        $indicatorFinded = [];
        $indicatorFinded = $this->get('reporting.finder')->findIndicator();
        $json = json_encode($indicatorFinded);
        return new Response($json, Response::HTTP_OK);
        
    }


    /**
     * Send data formatted corresponding to code to display it in front
     * @Rest\Post("/indicators/export/{id}")
     *
     * @SWG\Tag(name="Reporting")
     *
     * @SWG\Parameter(
     *     name="Project",
     *     in="body",
     *     required=true,
     *     @Model(type=ReportingIndicator::class)
     * )
     *
     * @SWG\Response(
     *      response=200,
     *          description="Get data reporting",
     *          @SWG\Schema(
     *              type="array",
     *              @SWG\Items(ref=@Model(type=ReportingIndicator::class))
     *          )
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="BAD_REQUEST"
     * )
     *
     * @param ReportingIndicator $indicator
     * @param Request $request
     * @return Response
     * @throws \Exception
     */

    public function exportToCsv(Request $request, ReportingIndicator $indicator) {

        $filters = $request->request->get('filters'); //on utilisera les filtres dans le csv

        $contentJson = $request->request->all();
        $filters['country'] = $contentJson['__country'];

        try {
            $dataComputed = $this->get('reporting.computer')->compute($indicator, $filters);
            $dataFormatted = $this->get('reporting.formatter')->format(Formatter::CsvFormat, $dataComputed, $indicator->getGraph());
        }
        catch (\Exception $e)
        {
            return new Response($e->getMessage(), $e->getCode() > 200 ? $e->getCode() : Response::HTTP_BAD_REQUEST);
        }

        $extraData = [
            ["Frequency", $filters['frequency']],
            ["Country", $contentJson['__country']],
            ["Indicator name", $indicator->getCode()],
            ["Indicator Reference", $indicator->getReference()],
            ["Graph type", $indicator->getGraph()],
        ];

        /** @var ExportService $exporter */
        $exporter = $this->get('export_csv_service');

        $toExport = $exporter
            ->setHeaders($extraData)
            ->export($dataFormatted, $indicator->getReference());

        return new JsonResponse($toExport);
    }


}
