<?php

namespace CommonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Doctrine\ORM\EntityManager;

use Gaufrette\Filesystem;
use Gaufrette\Adapter\AwsS3;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\Credentials;

/**
 * Class UploadController
 * @package CommonBundle\Controller
 */
class UploadController extends Controller
{

    protected $container;
    private $s3;

    protected $aws_access_key_id;
    protected $aws_secret_access_key;
    protected $aws_s3_region;

    // /**
    //  * @param $aws_access_key_id
    //  * @param $aws_secret_access_key
    //  */
    // public function __construct($aws_access_key_id, $aws_secret_access_key, $aws_s3_region)
    // {
    //     $credentials = new Credentials(
    //         $aws_access_key_id,
    //         $aws_secret_access_key
    //     );

    //     // Instantiate the S3 client with your AWS credentials
    //     $s3 = S3Client::factory(array(
    //         'credentials' => $credentials,
    //         'version' => 'latest', //@TODO: not this in production
    //         'region' => $aws_s3_region
    //     ));

    //     $this->s3 = $s3;
    // }

     /**
     * @Rest\Post("/uploadImage", name="upload_image")
     * 
     * @SWG\Tag(name="UploadImage")
     *
     * @SWG\Parameter(
     *     name="file",
     *     in="formData",
     *     required=true,
     *     type="file"
     * )
     * @SWG\Response(
     *     response=200,
     *     @SWG\Schema(
     *          type="string"
     *     )
     * )
     *
     * @param Request $request
     * @return Response
     */
    // public function uploadImage(Request $request, AwsS3 $adapter) {
    public function uploadImage(Request $request) {

        $content = $request->getContent();
        $file = $request->files->get('file');
        $path = $file->getPathname();
        dump($file);
        dump($path);

        // try {

        //     $filename = sprintf('%s.%s', uniqid(), $file->getClientOriginalExtension());
        //     $adapter->setMetadata('Content-Type', $file->getMimeType());
        //     $response = $adapter->write($filename, file_get_contents($file->getPathname()));
        //     return $filename;

        // }
        // catch(S3Exception $e) {
        //     throw $e;
        // }
        // catch(\Exception $e) {
        //     throw $e;
        // }

        return new Response();
    }

}
