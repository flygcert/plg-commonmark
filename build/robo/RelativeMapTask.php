<?php

/**
 * @package    JoRobo
 *
 * @copyright   (C) 2007 - 2026 Flygcert FZE. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Flygcert\Robo\Tasks;

use Joomla\Jorobo\Tasks\JTask;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\Result;

class RelativeMapTask extends JTask
{
    use \Robo\Task\Development\Tasks;

    /**
     * @var null|string
     */
    protected $target = null;

    public function __construct($target, $params = [])
    {
        parent::__construct($params);

        $this->target = $target;
    }

    public function run()
    {
        $this->printTaskInfo('Mapping ' . $this->getJConfig()->extension . ' to ' . $this->target);
        $this->printTaskInfo('OS: ' . $this->getOs() . ' | Basedir: ' . $this->getSourceFolder());

        if (!$this->checkFolders()) {
            return Result::error($this, 'checkFolders failed');
        }

        $dirHandle = opendir($this->getSourceFolder());

        while (false !== ($element = readdir($dirHandle))) {
            if (substr($element, 0, 1) === '.') {
                continue;
            }

            $method = 'process' . ucfirst($element);

            if (method_exists($this, $method)) {
                $this->$method($this->getSourceFolder() . '/' . $element, $this->target);
            } else {
                $this->printTaskInfo('Missing method: ' . $method);
            }
        }

        closedir($dirHandle);

        return Result::success($this, 'Finished symlinking into Joomla!');
    }

    private function processAdministrator()
    {
        $sourceFolder = $this->getSourceFolder();
        $this->processComponents($sourceFolder . '/administrator/components', $this->target . '/administrator');
        $this->processLanguage($sourceFolder . '/administrator/language', $this->target . '/administrator');
        $this->processModules($sourceFolder . '/administrator/modules', $this->target . '/administrator/modules');
    }

    private function processComponents($src, $to)
    {
        if (!is_dir($src)) {
            return;
        }

        $dirHandle = opendir($src);

        while (false !== ($element = readdir($dirHandle))) {
            if (strpos($element, 'com_') !== false) {
                $this->symlink($src . '/' . $element, $to . '/components/' . $element);
            }
        }
    }

    private function processLanguage($src, $toDir)
    {
        if (!is_dir($src)) {
            return;
        }

        $dirHandle = opendir($src);

        while (false !== ($element = readdir($dirHandle))) {
            if (substr($element, 0, 1) === '.') {
                continue;
            }

            if (!is_dir($src . '/' . $element)) {
                continue;
            }

            $langDirHandle = opendir($src . '/' . $element);

            while (false !== ($file = readdir($langDirHandle))) {
                if (is_file($src . '/' . $element . '/' . $file)) {
                    $this->printTaskInfo($file);
                    $this->symlink($src . '/' . $element . '/' . $file, $toDir . '/language/' . $element . '/' . $file);
                }
            }
        }
    }

    private function processLibraries($src, $toDir)
    {
        $this->linkSubdirectories($src, $toDir . '/libraries');
    }

    private function processMedia($src, $toDir)
    {
        $this->linkSubdirectories($src, $toDir . '/media');
    }

    private function processApi($src, $to)
    {
        if (!is_dir($src)) {
            return;
        }

        $src .= '/components/';

        $dirHandle = opendir($src);

        while (false !== ($element = readdir($dirHandle))) {
            if (strpos($element, 'com_') !== false) {
                $this->symlink($src . '/' . $element, $to . '/api/components/' . $element);
            }
        }
    }

    private function linkSubdirectories($src, $to)
    {
        if (!is_dir($src)) {
            return;
        }

        $dirHandle = opendir($src);

        while (false !== ($element = readdir($dirHandle))) {
            if (substr($element, 0, 1) === '.') {
                continue;
            }

            if (is_dir($src . '/' . $element)) {
                $this->symlink($src . '/' . $element, $to . '/' . $element);
            }
        }
    }

    private function processModules($src, $toDir)
    {
        $this->linkSubdirectories($src, $toDir . '/modules');
    }

    private function processPlugins($src, $toDir)
    {
        if (!is_dir($src)) {
            return;
        }

        $dirHandle = opendir($src);

        while (false !== ($type = readdir($dirHandle))) {
            if (substr($type, 0, 1) === '.') {
                continue;
            }

            if (is_dir($src . '/' . $type)) {
                $this->linkSubdirectories($src . '/' . $type, $toDir . '/plugins/' . $type);
            }
        }
    }

    private function symlink($source, $target)
    {
        if (file_exists($target)) {
            if (is_dir($target) && !is_link($target)) {
                $this->_deleteDir($target);
            } else {
                unlink($target);
                $this->printTaskInfo("Unlink {dir}...", ['dir' => $target]);
            }
        }

        $linkTarget = $this->relativePath($target, $source);

        try {
            $this->taskFileSystemStack()
                ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
                ->symlink($linkTarget, $target)
                ->run();
        } catch (\Exception $e) {
            $this->printTaskError('Error symlinking: ' . $e->getMessage());
        }
    }

    /**
     * Return relative path between two sources
     * @param $from
     * @param $to
     * @param string $separator
     * @return string
     */
    private function relativePath($from, $to, $separator = DIRECTORY_SEPARATOR)
    {
        $from = str_replace(['/', '\\'], $separator, $from);
        $to   = str_replace(['/', '\\'], $separator, $to);

        $arFrom = explode($separator, rtrim($from, $separator));
        $arTo   = explode($separator, rtrim($to, $separator));

        while (count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
            array_shift($arFrom);
            array_shift($arTo);
        }

        return str_pad("", (count($arFrom) - 1) * 3, '..' . $separator) . implode($separator, $arTo);
    }
}
