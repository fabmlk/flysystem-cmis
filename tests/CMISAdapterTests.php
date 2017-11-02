<?php

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Tms\Cmis\Flysystem\CMISAdapter;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;

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

    public function testHas()
    {
        $sessionMock = $this->getSession();

        $docMock = $this->createMock('Dkd\PhpCmis\DataObjects\Document');

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($docMock);

        $docMock->expects($this->once())
            ->method('getProperties')
            ->willReturn([]);

        $adapter = new Filesystem(new CMISAdapter($sessionMock));
        $this->assertTrue($adapter->has('something'));
    }


    /**
     * @param Exception $exceptionClass
     *
     * @dataProvider provideExceptionsForHasFail
     */
    public function testHasFail(Exception $exceptionClass)
    {
        $sessionMock = $this->getSession();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willThrowException($exceptionClass);

        $adapter = new CMISAdapter($sessionMock);
        $this->assertFalse($adapter->has('something'));
    }

    public function provideExceptionsForHasFail()
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


    public function testWriteFail()
    {
        $sessionMock = $this->getSession();

        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('createDocument')
            ->willThrowException(new Exception);

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
        $result = $adapter->write('something', 'something', new Config());

        $this->assertFalse($result);
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



    public function testRename() {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getCmisObject();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($cmisObject);

        $cmisObject->expects($this->once())
            ->method('updateProperties')
            ->willReturn($this->anything());

        $adapter = new CMISAdapter($sessionMock, 'bucketname');
        $result = $adapter->rename('old', 'new');
        $this->assertTrue($result);
    }


    public function testRenameFail()
    {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getCmisObject();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($cmisObject);

        $cmisObject->expects($this->once())
            ->method('updateProperties')
            ->willReturn(null);

        $adapter = new CMISAdapter($sessionMock, 'bucketname');
        $result = $adapter->rename('old', 'new');
        $this->assertFalse($result);
    }


    public function testRenameFailException()
    {
        $sessionMock = $this->getSession();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willThrowException(new Exception);

        $adapter = new CMISAdapter($sessionMock, 'bucketname');
        $result = $adapter->rename('old', 'new');
        $this->assertFalse($result);
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


    public function testDeleteDirFail()
    {
        $sessionMock = $this->getSession();
        $cmisObject = $this->getFolder();

        $sessionMock->expects($this->once())
            ->method('getObjectByPath')
            ->willReturn($cmisObject);

        $cmisObject->expects($this->once())
            ->method('deleteTree')
            ->willThrowException(new Exception);

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->deleteDir('some/dirname');
        $this->assertFalse($result);
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


    public function testCreateDirFail()
    {
        $sessionMock = $this->getSession();
        $folderObjectMock = $this->getFolder();
        $objectIdMock = $this->getObjectId();

        $sessionMock->expects($this->once())
            ->method('getRootFolder')
            ->willReturn($folderObjectMock);

        $sessionMock->expects($this->once())
            ->method('createFolder')
            ->willThrowException(new Exception);

        $sessionMock->expects($this->once())
            ->method('createObjectId')
            ->willReturn($objectIdMock);

        $adapter = new CMISAdapter($sessionMock);
        $result = $adapter->createDir('dirname', new Config());
        $this->assertFalse($result);
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
