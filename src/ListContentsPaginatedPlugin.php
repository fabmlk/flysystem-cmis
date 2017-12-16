<?php
/**
 * Plugin for CMIS adapter.
 */

namespace Tms\Cmis\Flysystem;

use Dkd\PhpCmis\DataObjects\Folder;
use Dkd\PhpCmis\Exception\CmisBaseException;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\OperationContextInterface;
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
     * @var OperationContextInterface
     */
    protected $context;

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
        $this->context = $this->session->getDefaultContext();
    }

    /**
     * List contents of a directory with pagination.
     *
     * @param string      $directory
     * @param bool        $recursive
     * @param object|null $config    a dummy object that could have keys 'offset' and 'limit'.
     *                               Default to 0 and repository-determined limit respectively.
     *                               A new 'total' key will be injected containing the total of elements as returned by the repository.
     *                               Note: we use an object as container instead of a reference to an array due to the way Flysystem
     *                               calls the plugin method (using magic __call() which does not support references).
     *
     * @return array
     *
     * @throws FileNotFoundException
     * @throws \Exception
     */
    public function handle($directory = '', $recursive = false, $config = null)
    {
        $config = $config ?: (object) [];
        $offset = \property_exists($config, 'offset') ? (int) $config->offset : 0;
        $limit = \property_exists($config, 'limit') ? (int) $config->limit : 0;

        if (\property_exists($config, 'orderByName') && true === (bool) $config->orderByName) {
            $this->context->setOrderBy('cmis:name');
        }

        return $this->listContentsPaginated($directory, $recursive, $offset, $limit, $config->total);
    }

    /**
     * Perform the actual paginated listing.
     *
     * @param string $directory
     * @param bool   $recursive
     * @param int    $offset
     * @param int    $limit
     * @param int    $total
     *
     * @return array
     *
     * @throws FileNotFoundException
     * @throws \Exception
     */
    protected function listContentsPaginated($directory, $recursive, $offset, $limit, &$total)
    {
        $location = $this->filesystem->getAdapter()->applyPathPrefix($directory);

        try {
            $object = empty($location) ? $this->session->getRootFolder()
                : $this->session->getObjectByPath($location);
            $results = [];

            if ($object instanceof Folder) {
                $childrenList = $object->getChildren($this->context)->skipTo($offset)->getPage($limit);
                foreach ($childrenList as $childObject) {
                    $result = $this->getObjectMetadata($childObject, $location);
                    $results[] = $result;

                    if ($recursive && 'dir' === $result['type']) {
                        $results = array_merge($results, $this->listContentsPaginated($result['path'], true, $offset, $limit, $total));
                    }
                }
                $total = $childrenList->getTotalNumItems();
            }
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($directory);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return $results;
    }
}
