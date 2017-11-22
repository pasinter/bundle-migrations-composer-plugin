<?php
namespace Pasinter\Composer\Migrations;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Script\ScriptEvents;
use Symfony\Component\Finder\Finder;

class PackageMigrationsPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_PACKAGE_INSTALL => [
                ['importBundleMigrations', 0]
            ],
            ScriptEvents::POST_PACKAGE_UPDATE => [
                ['importBundleMigrations', 0]
            ],
        ];
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function importBundleMigrations(PackageEvent $event)
    {
        $operation = $event->getOperation();
        /* @var UpdateOperation|InstallOperation $operation */

        $package = $operation instanceof InstallOperation ? $operation->getPackage() : $operation->getTargetPackage();
        /* @var $package CompletePackage */

        $projectDir = realpath(dirname(Factory::getComposerFile()));

        $projectMigrationsDirectory = $projectDir . '/app/DoctrineMigrations';

        $packageDir = $projectDir . 'vendor/' . $package->getName() . '/' . $package->getTargetDir();

        $packageMigrationsDir = $packageDir . 'DoctrineMigrations';

        if (file_exists($packageMigrationsDir) && is_dir($packageMigrationsDir)) {
            $finder = new Finder();
            $finder->files()->in($packageMigrationsDir)->sortByName()->name('*.php');

            $this->io->write(sprintf('Found %s migration files in package %s', count($finder), $package->getName()));

            $targetDir = $projectMigrationsDirectory . '/' . str_replace('/', '-', $package->getName());

            if (!file_exists($targetDir)) {
                if (symlink($packageMigrationsDir, $targetDir)) {
                    $this->io->write(sprintf('Created symlink %s (%s) for package %s', $targetDir, $packageMigrationsDir, $package->getName()));
                } else {
                    throw new \Exception;
                    $this->io->writeError(sprintf('Could not Create symlink %s (%s) for package %s', $targetDir, $packageMigrationsDir, $package->getName()));
                }
            }
        }
    }
}
