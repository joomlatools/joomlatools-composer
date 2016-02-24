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

        parent::uninstall($repo, $package);
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
        return false;


        $installPath = $this->getInstallPath($package);
        $manifest    = $this->_getManifest($installPath);

        if($manifest)
        {
            $type    = (string) $manifest->attributes()->type;
            $element = $this->_getElementFromManifest($manifest);

            if (empty($element)) {
                return false;
            }

            $extension = $this->getApplication()->getExtension($element, $type);

            return $extension !== false;
        }

        return false;
    }

    /**
     * Find the xml manifest of the package
     *
     * @param string Install path of package
     *
     * @return object  Manifest object
     */
    protected function _getManifest($installPath)
    {
        if (!is_dir($installPath)) {
            return false;
        }

        $installer = $this->getApplication()->getInstaller();
        $installer->setPath('source', $installPath);

        return $installer->getManifest();
    }

    /**
     * Get the element's name from the XML manifest
     *
     * @param object  Manifest object
     *
     * @return string
     */
    protected function _getElementFromManifest($manifest)
    {
        $element    = '';
        $type       = (string) $manifest->attributes()->type;

        switch($type)
        {
            case 'module':
                if(count($manifest->files->children()))
                {
                    foreach($manifest->files->children() as $file)
                    {
                        if((string) $file->attributes()->module)
                        {
                            $element = (string) $file->attributes()->module;
                            break;
                        }
                    }
                }
                break;
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
            case 'component':
                $element = strtolower((string) $manifest->name);
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $element);

                if(substr($element, 0, 4) != 'com_') {
                    $element = 'com_'.$element;
                }
                break;
            default:
                $element = strtolower((string) $manifest->name);
                $element = preg_replace('/[^A-Z0-9_\.-]/i', '', $element);
                break;
        }

        return $element;
    }
}
