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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;

$dispatcher = new EventDispatcher();
$dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
    if (true === extension_loaded('newrelic')) {
        newrelic_set_appname($event->getCommand()->getApplication()->getName());
        newrelic_name_transaction($event->getCommand()->getName());
    }
});

$console = new Application('S3-Syncer', S3Syncer::VERSION);
$console->setDispatcher($dispatcher);

$app = array();

$console->register('sync')
    ->setDefinition(array(
        new InputArgument('satis-json', InputArgument::REQUIRED, 'path to satis.json file'),
        new InputArgument('directory', InputArgument::REQUIRED, 'satis output directory'),
        new InputOption('dry', null, null, 'dry run')
    ))
    ->setDescription('Sync contents of local directory to S3 bucket')
    ->setHelp("Help here?")
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app, $console) {
        try {
            $S3Syncer = new S3Syncer($output, $console->getHelperSet()->get('progress'));
            $app['syncer'] = $S3Syncer->setup(
                $input->getArgument('satis-json'),
                $input->getArgument('directory'),
                $input->getOption('dry')
            )->sync();

            $output->writeln('');
        } catch (\Exception $e) {
            $output->writeln("\n" . sprintf('<error>%s</error>', $e->getMessage()));
            return 1;
        }

    });

return $console;
