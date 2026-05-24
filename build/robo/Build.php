<?php

/**
 * @package    JoRobo
 *
 * @copyright   (C) 2007 - 2026 Flygcert FZE. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Flygcert\Robo\Tasks;

use Joomla\Jorobo\Tasks\Build as BaseBuild;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\Result;

class Build extends BaseBuild
{
    /**
     * Build split package artifacts only (component/module/plugin/template/library zips).
     *
     * @param   array  $params  Build params
     *
     * @return  \Robo\Collection\CollectionBuilder
     */
    protected function deployPackageparts($params = [])
    {
        return $this->task(\Flygcert\Robo\Tasks\PackageParts::class, $params);
    }

    /**
     * Build the package with vendor-aware exclusion pruning.
     *
     * @return Result
     */
    public function run()
    {
        $this->printTaskInfo('Building ' . $this->getJConfig()->extension . ' ' . $this->getJConfig()->version);

        if (!$this->checkFolders()) {
            return Result::error($this, 'checkFolders failed');
        }

        $this->prepareDistDirectoryLocal();

        $this->buildExtension($this->params)
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->run();

        $this->applyVendorExclusionsFromIni();

        if ($this->isWindowsLocal()) {
            if (is_dir($this->params['base'] . '\\dist\\current')) {
                rmdir($this->params['base'] . '\\dist\\current');
            }

            $this->taskExec('mklink /J "' . $this->params['base'] . '\\dist\\current" "' . $this->getWindowsPathLocal($this->getBuildFolder()) . '"')
                ->run();
        } else {
            if (is_dir($this->params['base'] . '/dist/current')) {
                unlink($this->params['base'] . '/dist/current');
            }

            $this->taskFilesystemStack()
                ->symlink($this->getBuildFolder(), $this->params['base'] . '/dist/current')
                ->run();
        }

        $deploy = explode(' ', $this->getJConfig()->target);

        if (count($deploy)) {
            foreach ($deploy as $d) {
                $task = 'deploy' . ucfirst($d);
                $this->{$task}($this->params)->run();
            }
        }

        return Result::success($this, 'Build successful');
    }

    /**
     * Clean and prepare dist directory.
     */
    private function prepareDistDirectoryLocal(): void
    {
        $build = $this->getBuildFolder();

        if (!file_exists($build)) {
            $this->_mkdir($build);
        }

        $this->_cleanDir($build);
    }

    /**
     * If src/vendor exists, remove files from build output using exclusions in jorobo.ini.
     */
    private function applyVendorExclusionsFromIni(): void
    {
        if (!$this->hasVendorDirectoryInSource()) {
            return;
        }

        $patterns = $this->readVendorExclusionPatterns($this->params['base'] . '/jorobo.ini');

        if (!$patterns) {
            return;
        }

        $buildFolder = realpath($this->getBuildFolder());

        if ($buildFolder === false) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($buildFolder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $fullPath = $item->getPathname();
            $relativePath = str_replace('\\', '/', substr($fullPath, strlen($buildFolder) + 1));
            $relativePathWithSlash = '/' . ltrim($relativePath, '/');

            foreach ($patterns as $pattern) {
                if ($this->matchesPattern($relativePathWithSlash, $pattern, $item->isDir())) {
                    if ($item->isDir()) {
                        $this->_deleteDir($fullPath);
                    } else {
                        @unlink($fullPath);
                    }

                    $this->printTaskInfo('Excluded from build: ' . $relativePath);
                    break;
                }
            }
        }
    }

    /**
     * Check if any vendor directory exists under source.
     *
     * @return bool
     */
    private function hasVendorDirectoryInSource(): bool
    {
        $source = $this->getSourceFolder();

        if (!is_dir($source)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && strcasecmp($item->getFilename(), 'vendor') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read vendor exclusion patterns from jorobo.ini [build] vendor_exclude.
     *
     * @param   string  $iniPath  Path to jorobo.ini
     *
     * @return  array<string>
     */
    private function readVendorExclusionPatterns(string $iniPath): array
    {
        return $this->readIniListValue($iniPath, 'build', 'vendor_exclude');
    }

    /**
     * Read list values from an INI section/key.
     * Accepts comma-separated and newline-separated entries.
     *
     * @param   string  $iniPath   Path to ini file
     * @param   string  $section   Section name
     * @param   string  $key       Key name
     *
     * @return  array<string>
     */
    private function readIniListValue(string $iniPath, string $section, string $key): array
    {
        if (!is_file($iniPath)) {
            return [];
        }

        $lines = file($iniPath);

        if ($lines === false) {
            return [];
        }

        $inSection = false;
        $collecting = false;
        $values = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*\[[^\]]+\]\s*$/', $line)) {
                $inSection = (bool) preg_match('/^\s*\[' . preg_quote($section, '/') . '\]\s*$/', $line);
                $collecting = false;
                continue;
            }

            if (!$inSection) {
                continue;
            }

            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)$/', $line, $matches)) {
                $collecting = true;
                $value = trim($matches[1]);

                if ($value !== '') {
                    $values[] = $value;
                }

                continue;
            }

            if ($collecting) {
                if (preg_match('/^\s*[a-zA-Z0-9_.-]+\s*=/', $line)) {
                    $collecting = false;
                    continue;
                }

                $value = trim($line);

                if ($value === '' || strpos($value, ';') === 0) {
                    continue;
                }

                $values[] = $value;
            }
        }

        $list = [];

        foreach ($values as $value) {
            foreach (preg_split('/\s*,\s*/', $value) as $part) {
                $part = trim($part, " \t\n\r\0\x0B\"'");

                if ($part !== '') {
                    $list[] = $part;
                }
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * Simple glob-style matcher with support for **.
     *
     * @param   string   $path    Relative path from build root with leading slash
     * @param   string   $pattern Pattern from ini
     * @param   boolean  $isDir   Current item is directory
     *
     * @return  boolean
     */
    private function matchesPattern(string $path, string $pattern, bool $isDir): bool
    {
        $normalizedPattern = '/' . ltrim(str_replace('\\', '/', trim($pattern)), '/');
        $isDirectoryPattern = substr($normalizedPattern, -1) === '/';

        if ($isDirectoryPattern) {
            $normalizedPattern = rtrim($normalizedPattern, '/');
        }

        $quoted = preg_quote($normalizedPattern, '/');
        $quoted = str_replace('\\*\\*', '.*', $quoted);
        $quoted = str_replace('\\*', '[^\\/]*', $quoted);
        $quoted = str_replace('\\?', '[^\\/]', $quoted);

        if ($isDirectoryPattern) {
            // Directory patterns (ending with /) should match the directory itself and all descendants.
            $regex = '/^' . $quoted . '(?:$|\/.*)/i';

            return (bool) preg_match($regex, $path);
        }

        $regex = '/^' . $quoted . '$/i';

        return (bool) preg_match($regex, $path);
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
