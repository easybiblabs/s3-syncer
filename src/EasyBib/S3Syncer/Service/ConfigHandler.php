<?php
namespace EasyBib\S3Syncer\Service;

use Composer\Json\JsonFile;

class ConfigHandler
{
    /**
     * @var string
     */
    protected $bucketName;

    /**
     * @var string
     */
    protected $distDirectory;

    /**
     * @var \Composer\Json\JsonFile
     */
    protected $file;

    /**
     * @var string
     */
    protected $format = 'zip';

    /**
     * @param \Composer\Json\JsonFile $file
     */
    public function __construct(JsonFile $file)
    {
        $this->file = $file;
    }

    public function getBucketName()
    {
        return $this->bucketName;
    }

    public function getDistDirectory()
    {
        return $this->distDirectory;
    }

    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Validate the satis.json for what we require to run.
     *
     * @throws \DomainException|\RangeException|\RuntimeException
     */
    public function parse()
    {
        if (false === $this->file->exists()) {
            throw new \RuntimeException(sprintf("Configuration '%s' does not exist.", $satisJson));
        }
        $confArray = $this->file->read();
        if (empty($confArray['archive'])) {
            throw new \DomainException('satis.json does not contain an archive configuration');
        }

        foreach (array('prefix-url', 'directory', ) as $key) {
            if (false === isset($confArray['archive'][$key])) {
                throw new \RangeException("Missing '%s' setting in configuration.");
            }
        }

        $this->bucketName = $this->determineBucket($confArray['archive']['prefix-url']);
        $this->distDirectory = $confArray['archive']['directory'];
        if (isset($confArray['archive']['format'])) {
            $this->format = $confArray['archive']['format'];
        }
    }
    /**
     * @param string $url
     *
     * @return array
     */
    protected function determineBucket($url)
    {
        $hostName = parse_url($url, PHP_URL_HOST);
        $path = substr(parse_url($url, PHP_URL_PATH), 1);

        $bucket = array();
        if (!empty($path)) {
            $bucket[] = dirname($path);
        }

        if ('s3.amazonaws.com' !== $hostName) {
            // replace potential aws hostname
            array_unshift($bucket, str_replace('.s3.amazonaws.com', '', $hostName));
        }
        return $bucket[0];
    }
}