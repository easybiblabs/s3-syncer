<?php
/**
 * EasyBib Copyright 2013
 * Modifying, copying, of code contained herein that is not specifically
 * authorized by Imagine Easy Solutions LLC ("Company") is strictly prohibited.
 * Violators will be prosecuted.
 *
 * This restriction applies to proprietary code developed by EasyBib. Code from
 * third-parties or open source projects may be subject to other licensing
 * restrictions by their respective owners.
 *
 * Additional terms can be found at http://www.easybib.com/company/terms
 *
 * PHP Version 5
 *
 * @category Service
 * @package  Default
 * @author   Leander Damme <leander@wesrc.com>
 * @license  http://www.easybib.com/company/terms Terms of Service
 * @version  GIT: <git_id>
 * @link     http://www.easybib.com
 */
namespace EasyBib\S3Syncer\Service;

use Aws\S3\S3Client;
use Aws\S3\Enum\CannedAcl;
use Aws\S3\Exception\S3Exception;

/**
 * S3 Uploader
 *
 * @category Service
 * @package  Default
 * @author   Leander Damme <leander@wesrc.com>
 * @license  http://www.easybib.com/company/terms Terms of Service
 * @version  Release: @package_version@
 * @link     http://www.easybib.com
 */
class Uploader
{
    /**
     * @var S3Client $s3
     */
    private $s3;

    private $bucket;
    private $isDryRun;

    public $filesUploaded = 0;
    public $uploadErrors = 0;


    /**
     * constructor
     *
     * @param S3Client $s3
     * @param string   $bucket
     * @param bool     $dryRun
     *
     */
    public function __construct($s3, $bucket, $dryRun = false)
    {
        $this->s3 = $s3;
        $this->bucket = $bucket;
        $this->isDryRun = $dryRun;
    }


    /**
     * upload file
     *
     * @param array $fileMeta
     * @return bool
     */
    public function uploadFile($fileMeta)
    {
        $fullPath = $fileMeta['path'];
        $fileName = $fileMeta['filename'];
        $bucketKey = $fileMeta['key'];

        if ($this->isDryRun) {
            return false;
        }

        if ($this->s3->doesObjectExist($this->bucket, $bucketKey)) {
            return false;
        }

        try {
            $this->s3->putObject(array(
                'Bucket' => $this->bucket,
                'Key' => $bucketKey,
                'Body' => fopen($fullPath . '/' . $fileName, 'r'),
                'ACL' => CannedAcl::AUTHENTICATED_READ
            ));
            $this->filesUploaded++;

        } catch (S3Exception $e) {
            $this->uploadErrors++;
            return false;
        }

        return true;
    }
}
