<?php
/**
 * Helper trait for CMIS adapter.
 */

namespace Tms\Cmis\Flysystem;

use Dkd\PhpCmis\CmisObject\CmisObjectInterface;
use Dkd\PhpCmis\DataObjects\PropertyDateTimeDefinition;
use League\Flysystem\Util;

/**
 * Trait CommonsTrait.
 */
trait CommonsTrait
{
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
}
