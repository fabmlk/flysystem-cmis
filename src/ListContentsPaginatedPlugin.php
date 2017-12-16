<?php
/**
 * Plugin for CMIS adapter.
 */

namespace Tms\Cmis\Flysystem;

use Dkd\PhpCmis\DataObjects\Folder;
use Dkd\PhpCmis\Exception\CmisBaseException;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\Session;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

/**
 * Class ListContentsPaginatedPlugin.
 */
class ListContentsPaginatedPlugin implements PluginInterface
{
    use CommonsTrait;

    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @var Session
     */
    protected $session;

    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'listContentsPaginated';
    }

    /**
     * Set the Filesystem object.
     *
     * @param FilesystemInterface $filesystem
     */
    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->session = $this->filesystem->getAdapter()->getSession();
    }

    /**
     * List contents of a directory with pagination.
     *
     * @param string $directory
     * @param bool   $recursive
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     *
     * @throws FileNotFoundException
     * @throws \Exception
     */
    public function handle($directory = '', $recursive = false, $offset = 0, $limit = 100)
    {
        $location = $this->filesystem->getAdapter()->applyPathPrefix($directory);

        try {
            $object = empty($location) ? $this->session->getRootFolder()
                : $this->session->getObjectByPath($location);
            $results = [];

            if ($object instanceof Folder) {
                $childrenList = $object->getChildren()->skipTo($offset)->getPage($limit);
                foreach ($childrenList as $childObject) {
                    $result = $this->getObjectMetadata($childObject, $location);
                    $results[] = $result;

                    if ($recursive && 'dir' === $result['type']) {
                        $results = array_merge($results, $this->handle($result['path'], true, $offset, $limit));
                    }
                }
            }
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($directory);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return $results;
    }
}
