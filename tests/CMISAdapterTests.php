<?php

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Tms\Cmis\Flysystem\CMISAdapter;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\Exception\CmisBaseException;

class CMISAdapterTests extends PHPUnit\Framework\TestCase
{

    protected function getSession()
    {
        return $this->createMock('Dkd\PhpCmis\Session');
    }

    protected function getCmisObject()
    {
        return $this->createMock('Dkd\PhpCmis\CmisObject\CmisObjectInterface');
    }

    protected function getObjectId()
    {
        return $this->createMock('Dkd\PhpCmis\DataObjects\ObjectId');
    }

    protected function getFolder()
    {
        return $this->createMock('Dkd\PhpCmis\DataObjects\Folder');
    }

    protected function getProperty()
    {
        return $this->createMock('Dkd\PhpCmis\Data\PropertyInterface');
    }

    public function testHas()
    {
        $sessionMock = $this->getSession();

        $docMock = $this->createMock('Dkd\PhpCmis\DataObjects\Document');
        $propertyMock = $this->getProperty();

        $propertyMock->expects($this->once())
            ->method('getId')
            ->willReturn('cmis:name'); // getMetadata() will return a 'path' that needs a valid cmis:name property

        $propertyMock->expects($this->once())
            ->method('getValues')
            ->willReturn(['foo']);

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($docMock);

        $docMock->expects($this->once())
            ->method('getProperties')
            ->willReturn([$propertyMock]);

        $adapter = new Filesystem(new CMISAdapter($sessionMock));
        $this->assertTrue($adapter->has('something'));
    }


    public function provideCmisExceptions()
    {
        return [
            [$this->createMock('Dkd\PhpCmis\Exception\CmisObjectNotFoundException')],
            [$this->createMock('Dkd\PhpCmis\Exception\CmisInvalidArgumentException')]
        ];
    }


    public function testWrite()
    {
        $sessionMock = $this->getSession();

        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('createDocument');

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->once())
            ->method('getRootFolder')
            ->willReturn($folderObjectMock);

        $sessionMock->expects($this->never())
            ->method('getObjectByPath');

        $sessionMock->expects($this->never())
            ->method('createFolder')
            ->willReturn($objectIdMock);

        $adapter = new CMISAdapter($sessionMock);

        $this->assertInternalType('array', $adapter->write('something', 'something', new Config()));
    }

    /**
     * @param CmisBaseException $exceptionClass
     *
     * @dataProvider provideCmisExceptions
     * @expectedException \Exception
     */
    public function testWriteFailException(CmisBaseException $exceptionClass)
    {
        $sessionMock = $this->getSession();

        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('createDocument')
            ->willThrowException($exceptionClass);

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->once())
            ->method('getRootFolder')
            ->willReturn($folderObjectMock);

        $sessionMock->expects($this->never())
            ->method('getObjectByPath');

        $sessionMock->expects($this->never())
            ->method('createFolder')
            ->willReturn($objectIdMock);


        $adapter = new CMISAdapter($sessionMock);
        $adapter->write('something', 'something', new Config());
    }

    /**
     * @expectedException LogicException
     */
    public function testWriteVisibility()
    {
        $sessionMock = $this->getSession();

        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('createDocument');

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->once())
            ->method('getRootFolder')
            ->willReturn($folderObjectMock);

        $sessionMock->expects($this->never())
            ->method('getObjectByPath');

        $sessionMock->expects($this->never())
            ->method('createFolder')
            ->willReturn($objectIdMock);

        $adapter = new CMISAdapter($sessionMock);
        $this->assertInternalType('array', $adapter->write('something', 'something', new Config([
            'visibility' => 'private',
        ])));
    }

    public function testUpdate()
    {
        $sessionMock = $this->getSession();

        $docMock = $this->createMock('Dkd\PhpCmis\DataObjects\Document');
        $docMock->expects($this->once())
            ->method('setContentStream');

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($docMock);

        $adapter = new CMISAdapter($sessionMock);
        $this->assertInternalType('array', $adapter->update('something', 'something', new Config()));
    }


    public function testRename()
    {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getCmisObject();

        $sessionMock->expects($this->exactly(2))
            ->method('getObjectByPath')
            ->willReturnCallback(function ($path) use ($cmisObject) {
                if ($path === 'bucketname/old') { // on first call we return a valid object to rename
                    return $cmisObject;
                } else { // on 2nd call, we will simulate destination does not exist
                    throw new CmisObjectNotFoundException();
                }
            });

        $cmisObject->expects($this->once())
            ->method('updateProperties')
            ->willReturn($this->anything());

        $adapter = new CMISAdapter($sessionMock, 'bucketname');
        $result = $adapter->rename('old', 'new');
        $this->assertTrue($result);
    }


    public function testRenameMove()
    {
        $sessionMock = $this->getSession();
        $folderObjectMock1 = $this->getFolder();
        $folderObjectMock2 = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->exactly(2))
            ->method('getObjectByPath')
            ->willReturnMap([
                ['bucketname/old', null, $folderObjectMock1],
                ['bucketname/new', null, $folderObjectMock2]
            ]);

        $sessionMock->expects($this->exactly(2))
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $folderObjectMock1->expects($this->never())
            ->method('updateProperties');

        $folderObjectMock1->expects($this->once())
            ->method('move')
            ->willReturn(null);

        $adapter = new CMISAdapter($sessionMock, 'bucketname');
        $result = $adapter->rename('old', 'new');
        $this->assertTrue($result);
    }


    public function testRenameFail()
    {
        $sessionMock = $this->getSession();

        $sessionMock->expects($this->exactly(2))
            ->method('getObjectByPath')
            ->willReturn(new StdClass);

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->rename('/foo/old', '/bar/new'); // different parents
        $this->assertFalse($result);
    }


    /**
     * @param CmisBaseException $exceptionClass
     *
     * @dataProvider provideCmisExceptions
     */
    public function testRenameFailException(CmisBaseException $exceptionClass)
    {
        $sessionMock = $this->getSession();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willThrowException($exceptionClass);

        if ($exceptionClass instanceof CmisObjectNotFoundException) {
            $this->expectException('League\Flysystem\FileNotFoundException');
            $this->expectExceptionMessage('File not found at path: old');
        } else {
            $this->expectException('\Exception');
        }

        $adapter = new CMISAdapter($sessionMock, 'bucketname');
        $adapter->rename('old', 'new');
    }


    public function testDeleteDir()
    {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getFolder();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($cmisObject);

        $cmisObject->expects($this->once())
            ->method('deleteTree');

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->deleteDir('some/dirname');
        $this->assertTrue($result);
    }


    /**
     * @param CmisBaseException $exceptionClass
     *
     * @dataProvider provideCmisExceptions
     * @expectedException \Exception
     */
    public function testDeleteDirFailException(CmisBaseException $exceptionClass)
    {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getFolder();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($cmisObject);

        $cmisObject->expects($this->once())
            ->method('deleteTree')
            ->willThrowException($exceptionClass);

        $adapter = new CMISAdapter($sessionMock);
        $adapter->deleteDir('some/dirname');
    }


    public function testCreateDir()
    {
        $sessionMock = $this->getSession();
        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('getRootFolder')
            ->willReturn($folderObjectMock);

        $sessionMock->expects($this->once())
            ->method('createFolder')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->createDir('dirname', new Config());
        $this->assertInternalType('array', $result);
    }


    public function testCreateDirRecursive()
    {

        $sessionMock = $this->getSession();
        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('getRootFolder')
            ->willReturn($folderObjectMock);

        $sessionMock->expects($this->exactly(2))
            ->method('createFolder')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->exactly(2))
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willThrowException(new CmisObjectNotFoundException);

        $sessionMock->expects($this->exactly(2))
            ->method('getObject')
            ->willReturn($folderObjectMock);

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->createDir('dirname2/subdirname', new Config());
        $this->assertInternalType('array', $result);
    }

    /**
     * @param CmisBaseException $exceptionClass
     *
     * @dataProvider provideCmisExceptions
     * @expectedException \Exception
     */
    public function testCreateDirFailException(CmisBaseException $exceptionClass)
    {
        $sessionMock = $this->getSession();
        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('getRootFolder')
            ->willReturn($folderObjectMock);

        $sessionMock->expects($this->once())
            ->method('createFolder')
            ->willThrowException($exceptionClass);

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $adapter = new CMISAdapter($sessionMock);
        $adapter->createDir('dirname', new Config());
    }


    public function testRead()
    {
        $sessionMock = $this->getSession();
        $docMock = $this->createMock('Dkd\PhpCmis\DataObjects\Document');
        $objectIdMock = $this->getObjectId();
        $streamMock = $this->createMock('GuzzleHttp\Stream\StreamInterface');

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($docMock);

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->once())
            ->method('getContentStream')
            ->willReturn($streamMock);

        $docMock->expects($this->once())
            ->method('getId')
            ->willReturn('foo-id');

        $docMock->expects($this->atLeastOnce())
            ->method('getPropertyValue')
            ->will($this->returnValueMap([
                    ['cmis:lastModificationDate', new DateTimeImmutable()]
                ]
            ));

        $streamMock->expects($this->once())
            ->method('getContents')
            ->willReturn('whatever');

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->read('file.txt');
        $this->assertInternalType('array', $result);
    }


    public function testReadFail()
    {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getCmisObject();
        $objectIdMock = $this->getObjectId();

        $cmisObject->expects($this->atLeastOnce())
            ->method('getId')
            ->willReturn('foo-id');

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($cmisObject);

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $sessionMock->expects($this->once())
            ->method('getContentStream')
            ->willReturn(null);

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->read('file.txt');
        $this->assertFalse($result);
    }

    /**
     * @param CmisBaseException $exceptionClass
     *
     * @dataProvider provideCmisExceptions
     */
    public function testReadFailException(CmisBaseException $exceptionClass)
    {
        $sessionMock = $this->getSession();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willThrowException($exceptionClass);

        if ($exceptionClass instanceof CmisObjectNotFoundException) {
            $this->expectException('League\Flysystem\FileNotFoundException');
        } else {
            $this->expectException('\Exception');
        }

        $adapter = new CMISAdapter($sessionMock);
        $adapter->read('file.txt');
    }


    public function testUpdateMetadata()
    {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getCmisObject();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($cmisObject);

        $cmisObject->expects($this->once())
            ->method('updateProperties');

        $adapter = new CMISAdapter($sessionMock);
        $adapter->updateMetadata('/some/path', ['cmis:secondaryObjectTypeId']);
    }

}
