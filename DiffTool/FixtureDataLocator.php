<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 04.01.19
 * Time: 16:57
 */

namespace Playwing\DiffToolBundle\DiffTool;


use Symfony\Component\Filesystem\Filesystem;

class FixtureDataLocator
{
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var array
     */
    private $paths;


    /**
     * FixtureDataLocator constructor.
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem, $paths = [])
    {

        if (!is_array($paths)) {
            $paths = [$paths];
        }

        $this->paths      = $paths;
        $this->filesystem = $filesystem;
    }

    public function locateFile(string $fileName): string
    {
        foreach ($this->paths as $path) {
            $path = $path . $fileName;
            if ($this->filesystem->exists($path)) {
                return $path;
            };
        }

        throw new \Exception(sprintf('File `%s` is not found. Scanned paths: %s', $fileName, json_encode($this->paths)));

    }

    public function getDefaultPathToWrite()
    {
        return $this->paths[0] ?? null;
    }
}