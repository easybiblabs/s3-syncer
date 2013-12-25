<?php
namespace EasyBib\S3Syncer\Service;

use Composer\Config;

class Credentials
{
    /**
     * @var mixed
     */
    protected $awsAccessKey;

    /**
     * @var mixed
     */
    protected $awsSecretKey;

    /**
     * @var \Composer\Config
     */
    protected $config;

    /**
     * @param \Composer\Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Use the environment or the composer configuration to get the keys.
     *
     * No biggie if none are found though — hopefully we're on AWS EC2 then.
     *
     * @return Credentials
     */
    public function determine()
    {
        $awsAccessKey = getenv('AWS_ACCESS_KEY');
        $awsSecretKey = getenv('AWS_SECRET_KEY');
        if (false !== $awsAccessKey) {
            if (!empty($awsAccessKey)) {
                $this->awsAccessKey = $awsAccessKey;
                $this->awsSecretKey = $awsSecretKey;
                return $this;
            }
        }

        if (false === $this->config->has('amazon-aws')) {
            return $this;
        }

        $awsConfig = $this->config->get('amazon-aws');
        if (isset($awsConfig['key']) && isset($awsConfig['secret'])) {
            $this->awsAccessKey = $awsConfig['key'];
            $this->awsSecretKey = $awsConfig['secret'];
        }
        return $this;
    }

    public function getAccessKey()
    {
        return $this->awsAccessKey;
    }

    public function getSecretKey()
    {
        return $this->awsSecretKey;
    }

    public function hasCredentials()
    {
        if ($this->awsAccessKey !== null && $this->awsSecretKey !== null) {
            return true;
        }
        return false;
    }
}
