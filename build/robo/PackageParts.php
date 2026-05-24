<?php

/**
 * @package    JoRobo
 *
 * @copyright   (C) 2007 - 2026 Flygcert FZE. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Flygcert\Robo\Tasks;

use Joomla\Jorobo\Tasks\Deploy\Package as BasePackage;
use Robo\Result;

/**
 * Deploy package parts only (no final pkg-*.zip wrapper).
 */
class PackageParts extends BasePackage
{
    /**
     * Build split install archives only.
     *
     * @return Result
     */
    public function run()
    {
        $this->printTaskInfo(
            'Creating split package archives for '
            . $this->getJConfig()->extension
            . ' '
            . $this->getJConfig()->version
        );

        $zipDir = $this->params['base'] . '/dist/zips';

        if (file_exists($zipDir)) {
            $this->_deleteDir($zipDir);
        }

        $this->_mkdir($zipDir);

        if ($this->hasComponents()) {
            $this->createComponentZips();
        }

        if ($this->hasModules()) {
            $this->createModuleZips();
        }

        if ($this->hasPlugins()) {
            $this->createPluginZips();
        }

        if ($this->hasTemplates()) {
            $this->createTemplateZips();
        }

        if ($this->hasLibraries()) {
            $this->createLibraryZips();
        }

        $this->versionAndLinkSplitArchives();

        return Result::success($this, 'Split package archives created in dist/zips');
    }

    /**
     * Rename split archives to include version and create *-current.zip links.
     *
     * @return void
     */
    private function versionAndLinkSplitArchives(): void
    {
        $zipDir = $this->params['base'] . '/dist/zips';
        $distDir = $this->params['base'] . '/dist';
        $version = $this->getJConfig()->version;
        $zips = glob($zipDir . '/*.zip');

        if (!is_array($zips) || count($zips) === 0) {
            return;
        }

        foreach ($zips as $zipPath) {
            $name = basename($zipPath, '.zip');
            $versioned = $distDir . '/' . $name . '-' . $version . '.zip';
            $current = $distDir . '/' . $name . '-current.zip';

            if (is_file($versioned)) {
                unlink($versioned);
            }

            rename($zipPath, $versioned);

            if (is_link($current) || is_file($current)) {
                unlink($current);
            }

            if ($this->isWindowsLocal()) {
                $this->taskExec(
                    'mklink /H "'
                    . $this->getWindowsPathLocal($current)
                    . '" "'
                    . $this->getWindowsPathLocal($versioned)
                    . '"'
                )->run();
            } else {
                $this->taskFilesystemStack()
                    ->symlink($versioned, $current)
                    ->run();
            }
        }
    }

    private function hasComponents(): bool
    {
        $folders = glob($this->getSourceFolder() . '/administrator/components/com_*', GLOB_ONLYDIR);

        return is_array($folders) && count($folders) > 0;
    }

    private function hasModules(): bool
    {
        return file_exists($this->params['base'] . '/dist/current/modules');
    }

    private function hasPlugins(): bool
    {
        return file_exists($this->params['base'] . '/dist/current/plugins');
    }

    private function hasTemplates(): bool
    {
        return file_exists($this->params['base'] . '/dist/current/templates');
    }

    private function hasLibraries(): bool
    {
        return file_exists($this->params['base'] . '/dist/current/libraries');
    }

    private function isWindowsLocal(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    private function getWindowsPathLocal(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
