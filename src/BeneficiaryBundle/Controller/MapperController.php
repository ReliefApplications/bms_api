<?php


namespace BeneficiaryBundle\Controller;


use BeneficiaryBundle\Utils\HouseholdCSVService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


class MapperController extends Controller
{

    /**
     * @Rest\Post("/import/map", name="map_file")
     *
     * @param Request $request
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function mapExcelFile(Request $request)
    {
        /** @var HouseholdCSVService $householdCsvService */
        $householdCsvService = $this->get('beneficiary.household_csv_service');
        $filename = $householdCsvService->mapfile($request->request->get('__country'), $request->files->get('file'));

        // Create binary file to send
        $response = new BinaryFileResponse(getcwd() . '/' . $filename);

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $mimeTypeGuesser = new FileinfoMimeTypeGuesser();
        if ($mimeTypeGuesser->isSupported()) {
            $response->headers->set('Content-Type', $mimeTypeGuesser->guess(getcwd() . '/' . $filename));
        } else {
            $response->headers->set('Content-Type', 'text/plain');
        }
        $response->deleteFileAfterSend(true);

        return $response;
    }

}