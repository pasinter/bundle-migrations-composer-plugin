<?php
namespace Pasinter\Composer\Migrations;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
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

    protected $migrationsDirectory = 'app/DoctrineMigrations';

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

        $packageDir = 'vendor/' . $package->getName() . '/' . $package->getTargetDir();

        $migrationsDir = $packageDir . 'DoctrineMigrations';

        if (file_exists($migrationsDir) && is_dir($migrationsDir)) {
            $finder = new Finder();
            $finder->files()->in($migrationsDir)->sortByName()->name('*.php');

            $this->io->write(sprintf('Found %s migrations files in package %s', count($finder), $package->getName()));

            foreach ($finder as $file) {
                $this->io->write(sprintf('Processing migration %s', $migrationsDir . '/' . $file->getBasename()));

                $destinationPath = sprintf(
                    '%s/%s.%s.package.%s',
                    $this->migrationsDirectory,
                    $file->getBasename('.' . $file->getExtension()),
                    str_replace('/', '.', $package->getName()),
                    $file->getExtension()
                );

                if (!file_exists($destinationPath)) {
                    symlink($file->getRealPath(), $destinationPath);
                    $this->io->write(sprintf('Created symlink %s', $destinationPath));
                } else {
                    $this->io->write(sprintf('Symlink already exists: %s', $destinationPath));
                }
            }
        }
    }
}
