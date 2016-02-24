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

        $this->io->write(sprintf("    Queuing <fg=cyan>%s</fg=cyan> for installation", $package->getName()), true, IOInterface::VERBOSE);

        TaskQueue::getInstance()->enqueue(array('install', $package, $this->getInstallPath($package)));
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $this->io->write(sprintf("    Queuing <fg=cyan>%s</fg=cyan> for upgrading", $target->getName()), true, IOInterface::VERBOSE);

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

        $this->io->write(sprintf("    Queuing <fg=cyan>%s</fg=cyan> for removal", $package->getName()), true, IOInterface::VERBOSE);

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
        else $this->io->write(sprintf("    [<error>ERROR</error>] Could not copy %s to %s. Skipping uninstall of <info>%s</info>.", $manifest, $tmpFile, $package->getName()), true, IOInterface::VERBOSE);
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

        if ($application === false) {
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

    /**
     * Get the element name from the XML manifest
     *
     * @param string $path Path to the installation package
     *
     * @return string
     */
    protected function _getPackageName($path)
    {
        $element = '';

        $manifest = Util::getPackageManifest($path);
        $type    = (string) $manifest->attributes()->type;

        switch($type)
        {
            case 'component':
                $name    = strtolower((string) $manifest->name);
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $name);

                if (substr($element, 0, 4) != 'com_') {
                    $element = 'com_'.$element;
                }
                break;
            case 'module':
            case 'plugin':
                if(count($manifest->files->children()))
                {
                    foreach($manifest->files->children() as $file)
                    {
                        if ((string) $file->attributes()->$type)
                        {
                            $element = (string) $file->attributes()->$type;
                            break;
                        }
                    }
                }
                break;
            case 'file':
            case 'library':
                $element = substr($path, 0, -strlen('.xml'));
                break;
            case 'package':
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $manifest->packagename);

                if (substr($element, 0, 4) != 'pkg_') {
                    $element = 'pkg_'.$element;
                }
                break;
            case 'language':
                $element = $manifest->get('tag');
                break;
            case 'template':
                $name    = preg_replace('/[^A-Z0-9_ \.-]/i', '', $manifest->name);
                $element = strtolower(str_replace(' ', '_', $name));
                break;
            default:
                break;
        }

        return $element;
    }
}
