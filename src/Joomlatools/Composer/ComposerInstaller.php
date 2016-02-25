<?php
/**
 * Joomlatools Composer plugin - https://github.com/joomlatools/joomlatools-composer
 *
 * @copyright	Copyright (C) 2011 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/joomlatools-composer for the canonical source repository
 */

namespace Joomlatools\Composer;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;

use Joomlatools\Joomla\Bootstrapper;
use Joomlatools\Joomla\Util;

/**
 * Composer installer class
 *
 * @author  Steven Rombauts <https://github.com/stevenrombauts>
 * @package Joomlatools\Composer
 */
class ComposerInstaller extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->io->write(sprintf("  Queuing <comment>%s</comment> for installation", $package->getName()), true, IOInterface::VERBOSE);

        TaskQueue::getInstance()->enqueue(array('install', $package, $this->getInstallPath($package)));
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $this->io->write(sprintf("  - Queuing <comment>%s</comment> for upgrading", $target->getName()), true, IOInterface::VERBOSE);

        TaskQueue::getInstance()->enqueue(array('update', $target, $this->getInstallPath($target)));
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!$repo->hasPackage($package)) {
            throw new \InvalidArgumentException('Package is not installed: '.$package);
        }

        $this->io->write(sprintf("  - Queuing <comment>%s</comment> for removal", $package->getName()), true, IOInterface::VERBOSE);

        TaskQueue::getInstance()->enqueue(array('uninstall', $package, $this->getInstallPath($package)));

        // Find the manifest and set it aside so we can query it when actually uninstalling the extension
        $installPath = $this->getInstallPath($package);
        $manifest    = Util::getPackageManifest($installPath);
        $prefix      = str_replace(DIRECTORY_SEPARATOR, '-', $package->getName());
        $tmpFile     = tempnam(sys_get_temp_dir(), $prefix);

        if (copy($manifest, $tmpFile))
        {
            Util::setPackageManifest($installPath, $tmpFile);

            parent::uninstall($repo, $package);
        }
        else $this->io->write(sprintf("    [<error>ERROR</error>] Could not copy manifest %s to %s. Skipping uninstall of <info>%s</info>.", $manifest, $tmpFile, $package->getName()), true, IOInterface::VERBOSE);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return in_array($packageType, array('joomlatools-composer', 'joomlatools-installer', 'joomla-installer'));
    }

    /**
     * {@inheritDoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $application = Bootstrapper::getInstance()->getApplication();

        if ($application === false)
        {
            $this->io->write(sprintf("<comment>Warning:</comment> Can not instantiate application to check if %s is installed", $package->getName()), true, IOInterface::VERBOSE);

            return false;
        }

        $installPath = $this->getInstallPath($package);
        $manifest    = Util::getPackageManifest($installPath);

        if ($manifest === false) {
            return false;
        }

        $xml = simplexml_load_file($manifest);

        if($xml instanceof \SimpleXMLElement)
        {
            $type    = (string) $xml->attributes()->type;
            $element = Util::getNameFromManifest($installPath);

            if (empty($element)) {
                return false;
            }

            $extension = $application->getExtension($element, $type);

            return $extension !== false;
        }

        return false;
    }
}
