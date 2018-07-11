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
use Composer\Script\Event;
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
            ScriptEvents::POST_INSTALL_CMD => [
                ['importBundleMigrationsAll', 0]
            ],
        ];
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function importBundleMigrationsAll(Event $event)
    {
    }
    
    public function importBundleMigrations(PackageEvent $event)
    {
        $operation = $event->getOperation();
        /* @var UpdateOperation|InstallOperation $operation */

        $package = $operation instanceof InstallOperation ? $operation->getPackage() : $operation->getTargetPackage();
        /* @var $package CompletePackage */

        $projectMigrationsDirectory = 'app/DoctrineMigrations';

        $packageDir = 'vendor/' . $package->getName() . '/' . $package->getTargetDir();

        $packageMigrationsDir = $packageDir . 'DoctrineMigrations';

        if (file_exists($packageMigrationsDir) && is_dir($packageMigrationsDir)) {
            $targetDir = $projectMigrationsDirectory . '/package-' . str_replace('/', '-', $package->getName());

            if (!file_exists($targetDir)) {
                if (mkdir($targetDir)) {
                    $this->io->write(sprintf('Created directory %s for package %s', $targetDir, $package->getName()));
                } else {
                    $this->io->writeError(sprintf('Could not create directory %s for package %s', $targetDir, $package->getName()));
                    throw new \RuntimeException;
                }
            }

            $cwd = getcwd();
            chdir($targetDir);
            $finder = new Finder();
            $finder->files()->in('../../../' . $packageMigrationsDir)->sortByName()->name('*.php');
            $this->io->write(sprintf('Found %s migration files in package %s', count($finder), $package->getName()));

            foreach ($finder as $file) {
                $targetFileName = $targetDir . '/' . $file->getBasename();

                if (!file_exists($file->getBasename())) {
                    if (symlink($file->getPathname(), $file->getBasename())) {
                        $this->io->write(sprintf('Created migration symlink %s (%s) for package %s', $targetFileName, $file->getRelativePath(), $package->getName()));
                    } else {
                        $this->io->writeError(sprintf('Could not create migration symlink %s (%s) for package %s', $targetFileName, $file->getRelativePath(), $package->getName()));
                        break;
                    }
                } else {
                    $this->io->write(sprintf('Migration symlink %s (%s) already exists for package %s', $targetFileName, $file->getRelativePath(), $package->getName()));
                }
            }

            chdir($cwd);
        }
    }
}
