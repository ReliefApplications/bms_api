<?php

namespace CommonBundle\Utils;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Doctrine\ORM\EntityManager;

use Gaufrette\Filesystem;
use Gaufrette\Adapter\AwsS3;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\Credentials;

class UploadService implements ContainerAwareInterface
{
    private $container;
    private $s3;
 
    protected $awsAccessKeyId;
    protected $awsSecretAccessKey;
    protected $awsS3Region;


    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
 
    /**
     * @param $awsAccessKeyId
     * @param $awsSecretAccessKey
     */
    public function __construct($awsAccessKeyId, $awsSecretAccessKey, $awsS3Region, ContainerInterface $container)
    {
        $this->container = $container;

        $credentials = new Credentials(
            $awsAccessKeyId,
            $awsSecretAccessKey
        );
 
        // Instantiate the S3 client with your AWS credentials
        $s3 = S3Client::factory(array(
            'credentials' => $credentials,
            'version' => 'latest', //@TODO: not this in production
            'region' => $awsS3Region
        ));
 
        $this->s3 = $s3;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @param \Gaufrette\Adapter\AwsS3 $adapter
     *
     * @return mixed
     * @throws \Exception
     */
    public function uploadImage(UploadedFile $file, AwsS3 $adapter)
    {
        try {
            $filename = sprintf('%s.%s', uniqid(), $file->getClientOriginalExtension());
            $adapter->setMetadata('Content-Type', $file->getMimeType());
            $response = $adapter->write($filename, file_get_contents($file->getPathname()));
            return $filename;
        } catch (S3Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
