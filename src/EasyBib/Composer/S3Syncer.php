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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressHelper;
use EasyBib\S3Syncer\Service\Uploader;
use EasyBib\S3Syncer\Service\FileCollector;
use EasyBib\S3Syncer\Service\Credentials;
use EasyBib\S3Syncer\Service\ConfigHandler;
use Composer\Json\JsonFile;

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
     * @var Uploader
     */
    private $uploader;

    /**
     * @var ProgressHelper
     */
    private $progress;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var S3Client $s3
     */
    private $s3;

    private $startTime;
    private $bucketName = '';
    private $fileFormat;
    private $workingDirectory = '';
    private $archiveDirectory = '';
    private $userBuckets = array();
    private $fileList = array();
    private $s3Objects = array();
    private $filesAlreadyUploaded = 0;
    private $errorMessages = array();
    private $isDryRun = false;

    const VERSION = 0.1;

    /**
     * construct
     *
     * @param OutputInterface $output
     * @param ProgressHelper $progress
     *
     * @return \EasyBib\Composer\S3Syncer
     */
    public function __construct(OutputInterface $output, ProgressHelper $progress)
    {
        $this->startTime = microtime(true);
        $this->output = $output;
        $this->progress = $progress;
    }

    /**
     * setup
     *
     * @param string $satisJson        'path to json file'
     * @param string $workingDirectory 'satis output directory'
     * @param bool $dryRun             'run without uploading to s3'
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setup($satisJson, $workingDirectory, $dryRun = false)
    {
        if (empty($workingDirectory)) {
            throw new \InvalidArgumentException('Missing working directory.');
        }

        $configuration = new ConfigHandler(
            new JsonFile($satisJson)
        );

        $configuration->parse();

        $this->bucketName = $configuration->getBucketName();
        $this->archiveDirectory = $configuration->getDistDirectory();
        $this->workingDirectory = $workingDirectory;
        $this->fileFormat = $configuration->getFormat();

        $this->isDryRun = $dryRun;
        if ($this->isDryRun) {
            $this->output->writeln(
                '<info>WARNING: YOU ARE RUNNING IN DRY RUN MODE, NO FILES WILL BE UPLOADED TO S3.</info>'
            );
        }

        $credentials = new Credentials(
            \Composer\Factory::createConfig()
        );

        $config = array();
        if (true === $credentials->determine()->hasCredentials()) {
            $config['key'] = $credentials->getAccessKey();
            $config['secret'] = $credentials->getSecretKey();
        }

        //get s3 client
        $this->s3 = S3Client::factory($config);

        $this->loadUsersBuckets();
        $this->checkBucketIsValid();

        $this->uploader = new Uploader(
            $this->s3,
            $this->bucketName,
            $this->isDryRun
        );

        return $this;
    }

    /**
     * load buckets of user
     *
     * @throws \RuntimeException
     */
    public function loadUsersBuckets()
    {
        $results = array();
        $result = $this->s3->listBuckets();

        // Success?
        if (!$result) {
            throw new \RuntimeException("Unable to retrieve users buckets");
        }

        $buckets = $result['Buckets'];

        foreach ($buckets as $bucket) {
            $tmpName = (string)$bucket['Name'];
            $results[$tmpName] = $tmpName;
        }

        $this->userBuckets = $results;
    }

    /**
     * check if we have a valid bucket
     * @throws \InvalidArgumentException
     */
    private function checkBucketIsValid()
    {
        //Make sure user specified a valid bucket.
        if (isset($this->userBuckets[$this->bucketName])) {
            return;
        }
        $this->output->writeln("<error>Unable to find the bucket specified in your bucket list.</error>");
        $this->output->writeln("<question>Did you mean one of the following?</question>");

        foreach ($this->userBuckets as $name) {
            $this->output->writeln("<info>$name</info>");
        }
        throw new \InvalidArgumentException('The bucketname was derrived from your satis.json.');
    }

    /**
     * collect local files
     *
     * @return array|bool
     */
    public function collectFiles()
    {
        $fileCollector = new FileCollector();
        $this->output->writeln("<info>Collecting local files...</info>");
        $this->fileList = $fileCollector->collectFrom(
            $this->workingDirectory,
            $this->archiveDirectory,
            $this->fileFormat
        );
        return $this->fileList;
    }

    /**
     * load object from bucket
     *
     * @return array
     */
    public function loadObjectsFromS3Bucket()
    {
        $iterator = $this->s3->getIterator('ListObjects', array(
            'Bucket' => $this->bucketName
        ));
        $results = array();
        foreach ($iterator as $fileName) {
            $results[$fileName['Key']] = $fileName['Key'];
        }
        $this->s3Objects = $results;
        return $this->s3Objects;
    }

    /**
     * sync
     *
     * @return void
     */
    public function sync()
    {
        $this->collectFiles();
        $this->loadObjectsFromS3Bucket();
        $this->output->writeln("<info>Initiated sync service with bucket {$this->bucketName}</info>");

        $this->progress->start($this->output, count($this->fileList));
        foreach ($this->fileList as $filename => $fileMeta) {
            if (!isset($this->s3Objects[$fileMeta['key']])) {
                $this->uploader->uploadFile($fileMeta);
            } else {
                $this->filesAlreadyUploaded++;
            }
            $this->progress->advance();
        }
        $this->progress->finish();
        $this->report();
    }

    /**
     * report stats
     *
     * @return void
     */
    public function report()
    {
        $endTime = microtime(true);
        $totalTime = round($endTime - $this->startTime, 2);

        $this->output->writeln("<comment>************************* RESULTS ***********************</comment>");
        $this->output->writeln("<info>Total time: $totalTime (s)</info>");
        $this->output->writeln("<info>Total files examined: </info>" . count($this->fileList));
        $this->output->writeln("<info>Total files uploaded to S3: </info>{$this->uploader->filesUploaded}");
        $this->output->writeln("<info>Total files ignored (cached in s3): </info>{$this->filesAlreadyUploaded}");

        if (count($this->errorMessages) > 0) {
            $this->output->writeln("<info>Total upload errors: </info>{$this->uploader->uploadErrors}");
            foreach ($this->errorMessages as $message) {
                $this->output->writeln("<error>{$message}</error>");
            }
        }
        $this->output->writeln("<comment>*********************************************************</comment>");
    }
}
