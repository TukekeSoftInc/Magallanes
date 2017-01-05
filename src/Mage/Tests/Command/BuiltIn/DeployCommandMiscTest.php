<?php
/*
 * This file is part of the Magallanes package.
 *
 * (c) Andrés Montañez <andres@andresmontanez.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mage\Tests\Command\BuiltIn;

use Mage\Command\BuiltIn\DeployCommand;
use Mage\Tests\MageApplicationMockup;
use Mage\Command\AbstractCommand;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit_Framework_TestCase as TestCase;

class DeployCommandMiscTest extends TestCase
{
    public function testDeploymentWithSudo()
    {
        $application = new MageApplicationMockup();
        $application->configure(__DIR__ . '/../../Resources/testhost-sudo.yml');

        /** @var AbstractCommand $command */
        $command = $application->find('deploy');
        $this->assertTrue($command instanceof DeployCommand);

        $tester = new CommandTester($command);
        $tester->execute(['command' => $command->getName(), 'environment' => 'test']);

        $ranCommands = $application->getRuntime()->getRanCommands();

        $testCase = array(
            0 => 'git branch | grep "*"',
            1 => 'git checkout test',
            2 => 'git pull',
            3 => 'composer install',
            4 => 'composer dumpautoload --optimize',
            5 => 'rsync -e "ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no" -avz --exclude=.git --exclude=./var/cache/* --exclude=./var/log/* --exclude=./web/app_dev.php ./ tester@testhost:/var/www/test',
            6 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& sudo bin/console cache:clear --env=dev \\"',
            7 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& sudo bin/console cache:warmup --env=dev \\"',
            8 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& sudo bin/console assets:install --env=dev --symlink --relative web\\"',
            9 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& sudo bin/console assetic:dump --env=dev \\"',
            10 => 'git checkout master',
        );

        // Check total of Executed Commands
        $this->assertEquals(count($testCase), count($ranCommands));

        // Check Generated Commands
        foreach ($testCase as $index => $command) {
            $this->assertEquals($command, $ranCommands[$index]);
        }

        $this->assertEquals(0, $tester->getStatusCode());
    }

    public function testDeploymentWithBranchOverwrite()
    {
        $application = new MageApplicationMockup();
        $application->configure(__DIR__ . '/../../Resources/testhost-without-releases.yml');

        /** @var AbstractCommand $command */
        $command = $application->find('deploy');
        $this->assertTrue($command instanceof DeployCommand);

        $tester = new CommandTester($command);
        $tester->execute(['command' => $command->getName(), 'environment' => 'test', '--branch' => 'maintenance']);

        $ranCommands = $application->getRuntime()->getRanCommands();

        $testCase = array(
            0 => 'git branch | grep "*"',
            1 => 'git checkout maintenance',
            2 => 'git pull',
            3 => 'composer install',
            4 => 'composer dumpautoload --optimize',
            5 => 'rsync -e "ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no" -avz --exclude=.git --exclude=./var/cache/* --exclude=./var/log/* --exclude=./web/app_dev.php ./ tester@testhost:/var/www/test',
            6 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& bin/console cache:warmup --env=dev \\"',
            7 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& bin/console assets:install --env=dev --symlink --relative web\\"',
            8 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& bin/console assetic:dump --env=dev \\"',
            9 => 'git checkout master',
        );

        // Check total of Executed Commands
        $this->assertEquals(count($testCase), count($ranCommands));

        // Check Generated Commands
        foreach ($testCase as $index => $command) {
            $this->assertEquals($command, $ranCommands[$index]);
        }

        $this->assertEquals(0, $tester->getStatusCode());
    }

    public function testDeploymentWithCustomTask()
    {
        $application = new MageApplicationMockup();
        $application->configure(__DIR__ . '/../../Resources/testhost-custom-task.yml');


        /** @var AbstractCommand $command */
        $command = $application->find('deploy');
        $this->assertTrue($command instanceof DeployCommand);

        $tester = new CommandTester($command);
        $tester->execute(['command' => $command->getName(), 'environment' => 'test']);

        $ranCommands = $application->getRuntime()->getRanCommands();

        $testCase = array(
            0 => 'composer install',
            1 => 'composer dumpautoload --optimize',
            2 => 'rsync -e "ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no" -avz --exclude=.git --exclude=./var/cache/* --exclude=./var/log/* --exclude=./web/app_dev.php ./ tester@testhost:/var/www/test',
        );

        // Check total of Executed Commands
        $this->assertEquals(count($testCase), count($ranCommands));

        // Check Generated Commands
        foreach ($testCase as $index => $command) {
            $this->assertEquals($command, $ranCommands[$index]);
        }

        $this->assertTrue(strpos($tester->getDisplay(), '[Custom] Dummy Task') !== false);

        $this->assertEquals(0, $tester->getStatusCode());
    }

    public function testDeploymentWithErrorTaskCommands()
    {
        $application = new MageApplicationMockup();
        $application->configure(__DIR__ . '/../../Resources/testhost-with-error.yml');

        $application->getRuntime()->setReleaseId('20170101015120');

        /** @var AbstractCommand $command */
        $command = $application->find('deploy');
        $this->assertTrue($command instanceof DeployCommand);

        $tester = new CommandTester($command);
        $tester->execute(['command' => $command->getName(), 'environment' => 'test']);

        $ranCommands = $application->getRuntime()->getRanCommands();

        $testCase = array(
            0 => 'git branch | grep "*"',
            1 => 'git checkout test',
            2 => 'git pull',
            3 => 'composer install',
            4 => 'composer dumpautoload --optimize',
            5 => 'tar cfz /tmp/mageXYZ --exclude=".git" --exclude="./var/cache/*" --exclude="./var/log/*" --exclude="./web/app_dev.php" ./',
            6 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"mkdir -p /var/www/test/releases/1234567890\\"',
            7 => 'scp -P 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no /tmp/mageXYZ tester@testhost:/var/www/test/releases/1234567890/mageXYZ',
            8 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test/releases/1234567890 \\&\\& tar xfz mageXYZ\\"',
            9 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"rm /var/www/test/releases/1234567890/mageXYZ\\"',
        );

        // Check total of Executed Commands
        $this->assertEquals(count($testCase), count($ranCommands));

        // Check Generated Commands
        foreach ($testCase as $index => $command) {
            $this->assertEquals($command, $ranCommands[$index]);
        }

        $this->assertTrue(strpos($tester->getDisplay(), 'ERROR') !== false);

        $this->assertNotEquals(0, $tester->getStatusCode());
    }

    public function testDeploymentWithFailingPostDeployTaskCommands()
    {
        $application = new MageApplicationMockup();
        $application->configure(__DIR__ . '/../../Resources/testhost-with-postdeploy-error.yml');

        /** @var AbstractCommand $command */
        $command = $application->find('deploy');
        $this->assertTrue($command instanceof DeployCommand);

        $tester = new CommandTester($command);
        $tester->execute(['command' => $command->getName(), 'environment' => 'test']);

        $ranCommands = $application->getRuntime()->getRanCommands();

        $testCase = array(
            0 => 'git branch | grep "*"',
            1 => 'git checkout test',
            2 => 'git pull',
            3 => 'composer install',
            4 => 'composer dumpautoload --optimize',
            5 => 'rsync -e "ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no" -avz --exclude=.git --exclude=./var/cache/* --exclude=./var/log/* --exclude=./web/app_dev.php ./ tester@testhost:/var/www/test',
            6 => 'git checkout master',
        );

        // Check total of Executed Commands
        $this->assertEquals(count($testCase), count($ranCommands));

        // Check Generated Commands
        foreach ($testCase as $index => $command) {
            $this->assertEquals($command, $ranCommands[$index]);
        }

        $this->assertTrue(strpos($tester->getDisplay(), 'ERROR') !== false);

        $this->assertNotEquals(0, $tester->getStatusCode());
    }

    public function testDeploymentWithSkippingTask()
    {
        $application = new MageApplicationMockup();
        $application->configure(__DIR__ . '/../../Resources/testhost-skipping.yml');

        /** @var AbstractCommand $command */
        $command = $application->find('deploy');
        $this->assertTrue($command instanceof DeployCommand);

        $tester = new CommandTester($command);
        $tester->execute(['command' => $command->getName(), 'environment' => 'test']);

        $ranCommands = $application->getRuntime()->getRanCommands();

        $testCase = array(
            0 => 'git branch | grep "*"',
            1 => 'git pull',
            2 => 'composer install',
            3 => 'composer dumpautoload --optimize',
            4 => 'rsync -e "ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no" -avz --exclude=.git --exclude=./var/cache/* --exclude=./var/log/* --exclude=./web/app_dev.php ./ tester@testhost:/var/www/test',
            5 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& bin/console cache:warmup --env=dev \\"',
            6 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& bin/console assets:install --env=dev --symlink --relative web\\"',
            7 => 'ssh -p 22 -q -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no tester@testhost sh -c \\"cd /var/www/test \\&\\& bin/console assetic:dump --env=dev \\"',
            8 => 'git branch | grep "*"',
        );

        // Check total of Executed Commands
        $this->assertEquals(count($testCase), count($ranCommands));

        // Check Generated Commands
        foreach ($testCase as $index => $command) {
            $this->assertEquals($command, $ranCommands[$index]);
        }

        $this->assertTrue(strpos($tester->getDisplay(), 'SKIPPED') !== false);

        $this->assertEquals(0, $tester->getStatusCode());
    }
}
