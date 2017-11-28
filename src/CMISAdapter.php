<?php
/**
 * CMIS adapter for the Flysystem library.
 */

namespace Tms\Cmis\Flysystem;

use Dkd\PhpCmis\CmisObject\CmisObjectInterface;
use Dkd\PhpCmis\Data\FileableCmisObjectInterface;
use Dkd\PhpCmis\DataObjects\Document;
use Dkd\PhpCmis\DataObjects\Folder;
use Dkd\PhpCmis\DataObjects\PropertyDateTimeDefinition;
use Dkd\PhpCmis\Enum\UnfileObject;
use Dkd\PhpCmis\Exception\CmisBaseException;
use Dkd\PhpCmis\Exception\CmisInvalidArgumentException;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\Exception\CmisRuntimeException;
use Dkd\PhpCmis\Session;
use GuzzleHttp\Stream\Stream as GuzzleStream;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\Config;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Util;

/**
 * Makes use of built-in AbstractAdapter class.
 *
 * {@link https://flysystem.thephpleague.com/}
 */
class CMISAdapter extends AbstractAdapter
{
    use StreamedTrait;
    use StreamedCopyTrait;
    use NotSupportingVisibilityTrait;

    /**
     * Key option for file name and dir name encoding.
     *
     * @var string
     */
    const OPTION_ENCODING = 'cmis_encoding';

    /**
     * Key option for CMIS properties.
     *
     * @var string
     */
    const OPTION_PROPERTIES = 'cmis_properties';

    /**
     * Key option for auto creation of missing directories in path.
     *
     * @var string
     */
    const OPTION_AUTO_CREATE_DIRECTORIES = 'cmis_auto_create_directories';

    /**
     * Map CMIS metadata names to flysystem metadata names.
     *
     * @var array
     */
    protected static $resultMap = [
        'cmis:contentStreamLength' => 'size',
        'cmis:contentStreamMimeType' => 'mimetype',
        'cmis:lastModificationDate' => 'timestamp',
    ];

    /**
     * A dkd/php-cmis session.
     *
     * @var Session
     */
    protected $session;

    /**
     * Constructor.
     *
     * @param Session $session a dkd/php-cmis active session
     * @param string  $prefix  a prefix for all subsequent paths
     */
    public function __construct(Session $session, $prefix = null)
    {
        $this->session = $session;
        $this->setPathPrefix(null === $prefix ? '/' : $prefix);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     * @param string $contents
     * @param Config $config
     *
     * @return bool|string[]
     *
     * @throws \LogicException
     */
    public function write($path, $contents, Config $config)
    {
        $location = $this->applyPathPrefix($path);
        list($parentPath, $filename) = array_values(Util::pathinfo($location));

        $properties = $config->get(self::OPTION_PROPERTIES) ?: [];
        $encoding = $config->get(self::OPTION_ENCODING) ?: 'UTF-8';

        try {
            $parentFolder = $this->ensureDirectory($parentPath, $properties, $config);

            $properties['cmis:name'] = $this->convertToLatin1($filename, $encoding);
            if (!array_key_exists('cmis:objectTypeId', $properties)) {
                $properties['cmis:objectTypeId'] = 'cmis:document';
            }

            $this->session->createDocument(
                $properties,
                $this->session->createObjectId($parentFolder->getId()),
                GuzzleStream::factory($contents)
            );
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($parentPath);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        $result = compact('path', 'contents');

        if ($config->get('visibility')) {
            throw new \LogicException(sprintf('%s does not support visibility settings.', __CLASS__));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return array
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @throws \Exception
     *
     * @return bool|string[]
     */
    public function getMetadata($path)
    {
        $location = $this->applyPathPrefix($path);

        try {
            $object = $this->session->getObjectByPath($location);

            return $this->getObjectMetadata($object, $location);
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($path);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     * @param string $contents
     * @param Config $config
     *
     * @throws \LogicException on trying to use non-implemented 'visibility' config option
     *
     * @return bool|string[]
     */
    public function update($path, $contents, Config $config)
    {
        $location = $this->applyPathPrefix($path);

        try {
            $docObject = $this->session->getObjectByPath($location);
            if ($docObject instanceof Document) {
                $docObject->setContentStream(
                    GuzzleStream::factory($contents),
                    true,
                    false
                );
            }
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        $result = compact('path', 'contents');

        if ($config->get('visibility')) {
            throw new \LogicException(sprintf('%s does not support visibility settings.', __CLASS__));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $location = $this->applyPathPrefix($path);
        $newlocation = $this->applyPathPrefix($newpath);

        $parentOriginal = Util::dirname($location);
        list($parentNew, $nameNew) = array_values(Util::pathinfo($newlocation));

        try {
            $objectToRename = $this->session->getObjectByPath($location);

            /* not just a rename ? move the object to another destination */
            if ($parentOriginal !== $parentNew) {
                if (!($objectToRename instanceof FileableCmisObjectInterface)) {
                    return false;
                }

                $objectToRenameParentId = $this->session->createObjectId($objectToRename->getParentId());
                $destinationFolderObject = $this->session->getObjectByPath($parentNew);
                $destinationFolderId = $this->session->createObjectId($destinationFolderObject->getId());

                $objectToRename = $objectToRename->move($objectToRenameParentId, $destinationFolderId);
            }

            /* now we can rename directly */
            // 23/10/2017 dkd/php-cmis master branch:
            // DO NOT USE AbstractCmisObject::rename(),
            // it maps properties incorrectly !
            $ret = $objectToRename->updateProperties(['cmis:name' => $nameNew]);

            return null !== $ret;
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($path);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $location = $this->applyPathPrefix($path);

        try {
            $object = $this->session->getObjectByPath($location);
            $this->session->delete($object, true);

            return true;
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($path);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $location = $this->applyPathPrefix($dirname);

        try {
            $object = $this->session->getObjectByPath($location);
            $object->deleteTree(true, new UnfileObject(UnfileObject::DELETE), true);

            return true;
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     * @param Config $config
     *
     * @return bool|string[]
     */
    public function createDir($path, Config $config)
    {
        $location = $this->applyPathPrefix($path);
        list($parentPath, $foldername) = array_values(Util::pathinfo($location));

        $properties = $config->get(self::OPTION_PROPERTIES) ?: [];

        try {
            $parentFolder = $this->ensureDirectory($parentPath, $properties, $config);
            $this->createFolder($parentFolder, $foldername, $properties);
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($parentPath);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return compact('path') + ['type' => 'dir'];
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return bool|string[]
     */
    public function read($path)
    {
        $location = $this->applyPathPrefix($path);

        try {
            $docObject = $this->session->getObjectByPath($location);
            $contentStream = $this->session->getContentStream(
                $this->session->createObjectId($docObject->getId())
            );

            if (null === $contentStream) {
                return false;
            }

            return array_merge(
                [
                    'contents' => $contentStream->getContents(),
                    'path' => $path,
                ],
                Util::map(
                    [
                        'cmis:contentStreamLength' => $docObject->getPropertyValue('cmis:contentStreamLength'),
                        'cmis:contentStreamMimeType' => $docObject->getPropertyValue('cmis:contentStreamMimeType'),
                        'cmis:lastModificationDate' => $docObject->getPropertyValue('cmis:lastModificationDate')
                            ->getTimestamp(),
                    ],
                    static::$resultMap
                )
            );
        } catch (CmisObjectNotFoundException $e) {
            throw new FileNotFoundException($path);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $directory path to the directory to list
     * @param bool   $recursive enables recursion
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $location = $this->applyPathPrefix($directory);

        try {
            $object = empty($location) ? $this->session->getRootFolder()
                : $this->session->getObjectByPath($location);
            $results = [];

            if ($object instanceof Folder) {
                $childrenList = $object->getChildren();
                foreach ($childrenList as $childObject) {
                    $result = $this->getObjectMetadata($childObject, $location);
                    $results[] = $result;

                    if ($recursive && 'dir' === $result['type']) {
                        $results = array_merge($results, $this->listContents($result['path'], true));
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

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return array
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return array
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return array
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Update metadata on the object pointed by its path.
     *
     * @param string $path     the object path
     * @param array  $metadata the metadata to set
     *
     * @throws \Exception
     */
    public function updateMetadata($path, array $metadata)
    {
        $location = $this->applyPathPrefix($path);

        try {
            $object = $this->session->getObjectByPath($location);
            $object->updateProperties($metadata);
        } catch (CmisBaseException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Convert a string with specified encoding to ISO-8859-1.
     *
     * Why do we need this ?
     * Parts of type "text" in the Multipart/form-data posted do not contain a Content-Type telling the charset in-use.
     * The standard seems to default to ISO-8859-1.
     *
     * @param string $string   the string to convert
     * @param string $encoding the encoding name of the source string
     *
     * @return string the ISO-8859-1 string
     */
    protected function convertToLatin1($string, $encoding = 'UTF-8')
    {
        return iconv($encoding, 'ISO-8859-1//TRANSLIT', $string);
    }

    /**
     * Get all metadata from an object at a specific $path.
     *
     * @param CmisObjectInterface $object the CMIS object to inspect
     * @param string              $path   the path to the object
     *
     * @return array
     */
    protected function getObjectMetadata(CmisObjectInterface $object, $path)
    {
        $properties = $object->getProperties();
        $rawProperties = [];

        foreach ($properties as $property) {
            $key = $property->getId();
            $values = $property->getValues();

            if ($property->getDefinition() instanceof
                PropertyDateTimeDefinition) {
                $values = array_map(
                    function (\DateTime $dateTime) {
                        return $dateTime->format(\DateTime::ATOM);
                    },
                    $values
                ); // convert to string
            }

            $value = implode(', ', $values);
            $rawProperties[$key] = $value;
        }

        return $this->normalizeObject($rawProperties, $path);
    }

    /**
     * Normalise a CMIS response.
     *
     * @param array  $object the array of CMIS properties of a CMIS object
     * @param string $path   the path to the CMIS object
     *
     * @return array
     */
    protected function normalizeObject(array $object, $path)
    {
        $result = Util::map($object, static::$resultMap);

        if (array_key_exists('cmis:lastModificationDate', $object)) {
            $result['timestamp'] = strtotime($object['cmis:lastModificationDate']);
        }

        $result['type'] = false;
        if (array_key_exists('cmis:path', $object)) {
            $result['type'] = 'dir';
        } elseif (array_key_exists('cmis:baseTypeId', $object)
            && 'cmis:document' === $object['cmis:baseTypeId']
        ) {
            $result['type'] = 'file';
        }

        $result['path'] = trim($path.'/'.$object['cmis:name'], '/');

        return array_merge($result, $object);
    }

    /**
     * Ensure the parent directory exists unless config cmis_auto_create_directories is false.
     *
     * @param string $path       root directory path
     * @param array  $properties cmis properties
     * @param Config $config     config, see doc
     *
     * @throws CmisInvalidArgumentException
     * @throws CmisRuntimeException
     * @throws CmisObjectNotFoundException
     *
     * @return Folder|CmisObjectInterface the parent Folder
     */
    protected function ensureDirectory($path, array $properties, Config $config)
    {
        if (false === $config->get(self::OPTION_AUTO_CREATE_DIRECTORIES)) {
            return $this->session->getObjectByPath($path);
        }

        $parts = array_filter(explode('/', ltrim($path, '/')));
        $folder = $this->session->getRootFolder(); // starting from root

        $path = '';
        for ($i = 0, $count = count($parts); $i < $count; ++$i) {
            $path = $path.'/'.$parts[$i];
            try {
                $folder = $this->session->getObjectByPath($path);
            } catch (CmisObjectNotFoundException $e) { // thrown every time folder does not exist
                $folder = $this->createFolder($folder, $parts[$i], $properties);
            }
        }

        return $folder;
    }

    /**
     * Creates a new folder as child of a parent folder node.
     *
     * @param Folder $parentFolder the parent folder
     * @param string $folderName   the folder name
     * @param array  $properties   the property options
     *
     * @throws CmisInvalidArgumentException
     * @throws CmisObjectNotFoundException
     *
     * @return Folder|CmisObjectInterface the newly created folder
     */
    protected function createFolder(Folder $parentFolder, $folderName, array $properties)
    {
        $encoding = 'UTF-8';
        if (array_key_exists(self::OPTION_ENCODING, $properties)) {
            $encoding = $properties[self::OPTION_ENCODING];
        }

        $properties['cmis:name'] = $this->convertToLatin1($folderName, $encoding);

        if (!array_key_exists('cmis:objectTypeId', $properties)) {
            $properties['cmis:objectTypeId'] = 'cmis:folder';
        }

        $folderId = $this->session->createFolder(
            $properties,
            $this->session->createObjectId($parentFolder->getId())
        );

        return $this->session->getObject($folderId);
    }
}
