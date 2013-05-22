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
 * @category Util
 * @package  Default
 * @author   Leander Damme <leander@wesrc.com>
 * @license  http://www.easybib.com/company/terms Terms of Service
 * @version  GIT: <git_id>
 * @link     http://www.easybib.com
 */
namespace EasyBib\Composer;

use Aws\S3\S3Client;
use Aws\Common\Aws;
use Aws\S3\Enum\CannedAcl;
use Aws\S3\Exception\S3Exception;
use Guzzle\Http\EntityBody;

/**
 * S3-Syncer
 *
 * @category Util
 * @package  Default
 * @author   Leander Damme <leander@wesrc.com>
 * @license  http://www.easybib.com/company/terms Terms of Service
 * @version  Release: @package_version@
 * @link     http://www.easybib.com
 */
class S3Syncer
{
    /**
     * @var S3Client $s3
     */
    private $s3;

    /**
     * @var string
     */
    private $bucketName = '';

    /**
     * @var string
     */
    private $directory = '';
    private $userBuckets = array();
    private $fileList = array();
    private $fileHashList = array();

    private $startTime;
    private $filesUploaded = 0;
    private $filesAlreadyUploaded = 0;
    private $uploadErrors = 0;
    private $s3Objects = array();
    private $errorMessages = array();
    private $isDryRun = FALSE;

    const VERSION = 0.1;
    const MAX_FILE_SIZE_IN_BYTES = 4294967296;

    /**
     * construct
     *
     * @param string $bucketName
     * @param string $directory
     * @param bool $dryRun
     *
     * @return void
     */
    public function __construct($bucketName, $directory, $dryRun = false)
    {
        //get the $s3 object
        $config = array();
        $this->s3 = S3Client::factory($config);

        $this->bucketName = $bucketName;
        $this->directory = $directory;
        $this->isDryRun = $dryRun;

        if (empty($bucketName) || empty($directory)) {
            throw new \Exception('Missing bucket or directory');
        } else {
            echo "\n\nInitiated sync service with bucket {$this->bucketName}\n";
        }

        $this->loadUsersBuckets();
        $this->checkBucketIsValid();

        if ($this->isDryRun) {
            echo "WARNING: YOU ARE RUNNING IN DRY RUN MODE, NO FILES WILL BE UPLOADED TO S3.\n\n";
        }

        //Retreive a list of files to be processed
        $this->fileList = $this->getFileListFromDirectory($this->directory);
        $totalFileCount = count($this->fileList);
        if ($this->fileList === FALSE) {
            throw new \Exception("Unable to get file list from directory.");
        } else {
            echo "\n\nTotal number of files found to process $totalFileCount \n";
        }
    }

    /**
     * sync
     *
     * @return void
     */
    public function sync()
    {
        $this->loadObjectsFromS3Bucket();

        echo "Begining to upload....\n\n";
        foreach ($this->fileList as $fileHash => $fileMeta) {
            if (!isset($this->s3Objects[$fileMeta['file']])) {
                $this->uploadFile($fileMeta);
            } else {
                echo "-";
                $this->filesAlreadyUploaded++;
            }
        }
    }

    /**
     * upload file
     *
     * @param $fileMeta
     */
    public function uploadFile($fileMeta)
    {
        echo ".";

        $fullPath = $fileMeta['path'];
        $fileHash = $fileMeta['hash'];
        $fileName = $fileMeta['file'];
        if ($this->filesUploaded % 100 == 0 && $this->filesUploaded != 0) {
            echo "({$this->filesUploaded} / " . count($this->fileList) . ") \n";
        }

        if (!$this->isDryRun) {
            try {
                $this->s3->putObject(array(
                    'Bucket' => $this->bucketName,
                    'Key' => $fileName,
                    'Body' => fopen($fullPath, 'r'),
                    'ACL' => CannedAcl::AUTHENTICATED_READ
                ));
                $this->filesUploaded++;

            } catch (S3Exception $e) {
                $this->uploadErrors++;
                echo "There was an error uploading the file.\n";
            }
        }
    }

    /**
     * destruct method
     */
    public function __destruct()
    {
        $endTime = microtime(TRUE);
        $totalTime = $endTime - $this->startTime;

        $out = array();
        $out[] = "************************* RESULTS ***********************\n ";
        $out[] = "Total time: $totalTime (s)\n";
        $out[] = "Total files examined: " . count($this->fileList) . "\n";
        $out[] = "Total files uploaded to S3: {$this->filesUploaded}\n";
        $out[] = "Total files ignored (cached in s3): {$this->filesAlreadyUploaded}\n";
        $out[] = "Total upload errors: {$this->uploadErrors}\n";
        $out[] = "***********************************************************\n ";

        if (count($this->errorMessages) > 0) {
            $out[] = implode("\n", $this->errorMessages);
        }

        $message = implode("\n", $out);
        echo $message;
    }

    /**
     * check if provided bucket exists
     */
    private function checkBucketIsValid()
    {
        //Make sure user specified a valid bucket.
        if (!isset($this->userBuckets[$this->bucketName])) {
            echo "\nUnable to find the bucket specified in your bucket list.  Did you mean one of the following?\n\n";
            foreach ($this->userBuckets as $k => $v) {
                echo "\t" . $v . "\n";
            }
            echo "\n";
            exit;
        }
    }

    /**
     * load object from bucket
     */
    public function loadObjectsFromS3Bucket()
    {
        $iterator = $this->s3->getIterator('ListObjects', array(
            'Bucket' => $this->bucketName
        ));

        foreach ($iterator as $fileName) {
            $results[$fileName['Key']] = $fileName['Key'];
        }
        $this->s3Objects = $results;
    }

    /**
     * load buckets of user
     *
     * @throws \Exception
     */
    public function loadUsersBuckets()
    {
        $results = array();
        $result = $this->s3->listBuckets();

        // Success?
        if (!$result) {
            throw new \Exception("Unable to retrieve users buckets");
        }

        $buckets = $result['Buckets'];

        foreach ($buckets as $bucket) {
            $tmpName = (string)$bucket['Name'];
            $results[$tmpName] = $tmpName;
        }

        $this->userBuckets = $results;
    }

    /**
     * get file list from local directory
     *
     * @param $dir
     *
     * @return array|bool
     */
    public function getFileListFromDirectory($dir)
    {
        // array to hold return value
        $returnValue = array();
        // add trailing slash if missing
        if (substr($dir, -1) != "/") {
            $dir .= "/";
        }
        // open pointer to directory and read list of files
        $d = @dir($dir);

        if ($d === FALSE) {
            return FALSE;
        }

        while (false !== ($entry = $d->read())) {
            // skip hidden files
            if ($entry[0] == ".") {
                continue;
            }

            if (is_dir("$dir$entry")) {
                if (is_readable("$dir$entry/")) {
                    $returnValue = array_merge($returnValue, $this->getFileListFromDirectory("$dir$entry/", true));
                }

            } elseif (is_readable("$dir$entry")) {
                $tFileName = "$dir$entry";
                $hash = md5($tFileName);
                $size = filesize($tFileName);
                if ($size > self::MAX_FILE_SIZE_IN_BYTES) {
                    $this->errorMessages[] = "The following file will not be processed as it exceeds the max file size: $tFileName";
                    continue;
                } else {
                    $returnValue[$hash] = array('path' => $tFileName, 'file' => $entry, 'hash' => $hash);
                    if (isset($this->fileHashList[$hash])) {
                        $this->errorMessages[] = "WARNING: FOUND A HASH COLLISSION, DUPLICATE FILE:$tFileName ";
                    } else {
                        $this->fileHashList[$hash] = 1;
                    }
                }
            }
        }
        $d->close();

        return $returnValue;

    }
}