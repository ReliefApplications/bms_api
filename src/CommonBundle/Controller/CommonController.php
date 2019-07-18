<?php

namespace CommonBundle\Controller;

use DistributionBundle\Entity\DistributionData;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CommonController
 * @package CommonBundle\Controller
 */
class CommonController extends Controller
{

    /**
     * @Rest\Get("/summary", name="get_summary")
     *
     * @SWG\Tag(name="Common")
     *
     * @SWG\Response(
     *     response=200,
     *     description="OK"
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="HTTP_BAD_REQUEST"
     * )
     * @param Request $request
     * @return Response
     */
    public function getSummaryAction(Request $request)
    {
        $country = $request->request->get('__country');
        
        try {
            $totalBeneficiaries = $this->get('beneficiary.beneficiary_service')->countAll($country);
            $activeProjects = $this->get('project.project_service')->countAll($country);
            $enrolledBeneficiaries = $this->get('distribution.distribution_service')->countAllBeneficiaries($country);

            $totalBeneficiaryServed = $this->get('beneficiary.beneficiary_service')->countAllServed($country);

            $totalCompletedDistributions = $this->get('distribution.distribution_service')->countCompleted($country);
            
            $result = array($totalBeneficiaries, $activeProjects, $enrolledBeneficiaries, $totalBeneficiaryServed, $totalCompletedDistributions);
        } catch (\Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        
        $json = $this->get('jms_serializer')->serialize($result, 'json', null);
        
        return new Response($json);
    }
}
