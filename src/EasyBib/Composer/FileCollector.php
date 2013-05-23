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

/**
 * FileCollector
 *
 * @category Util
 * @package  Default
 * @author   Leander Damme <leander@wesrc.com>
 * @license  http://www.easybib.com/company/terms Terms of Service
 * @version  Release: @package_version@
 * @link     http://www.easybib.com
 */
class FileCollector
{
    private $fileList = array();

    /**
     * get file list from local directory
     *
     * @param string $workingDir
     * @param string $archiveDir
     * @param string $extension
     *
     * @throws \Exception
     *
     * @return array|bool
     */
    public function collectFrom($workingDir, $archiveDir, $extension = 'tar')
    {
        $path = $workingDir . '/' . $archiveDir;
        $realPath = realpath($path);

        if (empty($realPath)) {
            throw new \Exception('path does not exist:' . $path);
        }

        $canCd = chdir($realPath);
        if (!$canCd) {
            throw new \Exception('Cannot change into directory:' . $path);
        }

        foreach (glob('*.' . $extension) as $filename) {
            $this->fileList[$filename]['filename'] = $filename;
            $this->fileList[$filename]['path'] = $realPath;
            $this->fileList[$filename]['key'] = $archiveDir . '/' . $filename;
        }

        return $this->fileList;
    }
}