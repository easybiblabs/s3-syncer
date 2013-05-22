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

use EasyBib\Composer\S3Syncer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application('S3-Syncer', S3Syncer::VERSION);
$app = array();

$console
->register('sync')
->setDefinition(array(
        new InputArgument('bucket', InputArgument::REQUIRED, 'bucket name'),
        new InputArgument('directory', InputArgument::REQUIRED, 'directory to sync'),
        //new InputArgument('dryrun', InputArgument::OPTIONAL, 'dry run')
    ))
->setDescription('Sync contents of local directory to S3 bucket')
->setHelp(<<<EOF
Help here
EOF
    )
->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {

        if ($input->getArgument('bucket')) {
            $app['data.bucket'] = $input->getArgument('bucket');
        }

        if ($input->getArgument('directory')) {
            $app['data.directory'] = $input->getArgument('directory');
        }

        $startedOut = false;
        $startedErr = false;
        $callback = null;
        if (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity()) {
            $callback = function ($type, $buffer) use ($output, &$startedOut, &$startedErr) {
                if ('err' === $type) {
                    if (!$startedErr) {
                        $output->write("\nERR| ");
                        $startedErr = true;
                        $startedOut = false;
                    }

                    $output->write(str_replace("\n", "\nERR| ", $buffer));
                } else {
                    if (!$startedOut) {
                        $output->write("\nOUT| ");
                        $startedOut = true;
                        $startedErr = false;
                    }

                    $output->write(str_replace("\n", "\nOUT| ", $buffer));
                }
            };
        }

        try {
            $output->writeln(sprintf('<info>Syncing contents of "%s" (into "%s")</info>', $app['data.directory'], $app['data.bucket']));
            $S3Syncer = new S3Syncer($app['data.bucket'], $app['data.directory']);
            $app['syncer'] = $S3Syncer->sync();
            $output->writeln('');
        } catch (\Exception $e) {
            $output->writeln("\n" . sprintf('<error>%s</error>', $e->getMessage()));
            return 1;
        }

    });

return $console;
