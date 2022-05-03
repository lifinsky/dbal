<?php

namespace Test\Ecotone\Dbal\DocumentStore;

use Ecotone\Dbal\DbalReconnectableConnectionFactory;
use Ecotone\Dbal\DocumentStore\DbalDocumentStore;
use Ecotone\Enqueue\CachedConnectionFactory;
use Ecotone\Messaging\Conversion\InMemoryConversionService;
use Ecotone\Messaging\Conversion\MediaType;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\Store\Document\DocumentException;
use Ecotone\Messaging\Store\Document\DocumentStore;
use Test\Ecotone\Dbal\DbalMessagingTest;

final class DbalDocumentStoreTest extends DbalMessagingTest
{
    private CachedConnectionFactory $cachedConnectionFactory;

    public function test_adding_document_to_collection()
    {
        $documentStore = $this->getDocumentStore();

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->addDocument('users', '123', '{"name":"Johny"}');

        $this->assertEquals('{"name":"Johny"}', $documentStore->getDocument('users', '123'));
        $this->assertEquals(1, $documentStore->countDocuments('users'));
    }

    public function test_updating_document()
    {
        $documentStore = $this->getDocumentStore();

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->addDocument('users', '123', '{"name":"Johny"}');
        $documentStore->updateDocument('users', '123', '{"name":"Franco"}');

        $this->assertEquals('{"name":"Franco"}', $documentStore->getDocument('users', '123'));
    }

    public function test_adding_document_as_object_should_return_object()
    {
        $documentStore = new DbalDocumentStore(
            CachedConnectionFactory::createFor(new DbalReconnectableConnectionFactory($this->getConnectionFactory())),
            true,
            InMemoryConversionService::createWithConversion(
                new \stdClass(),
                MediaType::APPLICATION_X_PHP,
                \stdClass::class,
                MediaType::APPLICATION_JSON,
                TypeDescriptor::STRING,
                '{"name":"johny"}'
            )
        );

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->addDocument('users', '123', new \stdClass());

        $this->assertEquals(new \stdClass(), $documentStore->getDocument('users', '123'));
    }

    public function test_adding_document_as_collection_of_objects_should_return_object()
    {
        $document = [new \stdClass(), new \stdClass()];
        $documentStore = new DbalDocumentStore(
            CachedConnectionFactory::createFor(new DbalReconnectableConnectionFactory($this->getConnectionFactory())),
            true,
            InMemoryConversionService::createWithConversion(
                $document,
                MediaType::APPLICATION_X_PHP,
                TypeDescriptor::createCollection(\stdClass::class),
                MediaType::APPLICATION_JSON,
                TypeDescriptor::STRING,
                '[{"name":"johny"},{"name":"franco"}]'
            )
        );

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->addDocument('users', '123', $document);

        $this->assertEquals($document, $documentStore->getDocument('users', '123'));
    }

    public function test_adding_document_as_array_should_return_array()
    {
        $document = [1, 2, 5];
        $documentStore = new DbalDocumentStore(
            CachedConnectionFactory::createFor(new DbalReconnectableConnectionFactory($this->getConnectionFactory())),
            true,
            InMemoryConversionService::createWithConversion(
                $document,
                MediaType::APPLICATION_X_PHP,
                TypeDescriptor::ARRAY,
                MediaType::APPLICATION_JSON,
                TypeDescriptor::STRING,
                '[1,2,5]'
            )
        );

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->addDocument('users', '123', $document);

        $this->assertEquals($document, $documentStore->getDocument('users', '123'));
    }

    public function test_adding_non_json_document_should_fail()
    {
        $documentStore = $this->getDocumentStore();

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $this->expectException(DocumentException::class);

        $documentStore->addDocument('users', '123', '{"name":');
    }

    public function test_deleting_document()
    {
        $documentStore = $this->getDocumentStore();

        $documentStore->addDocument('users', '123', '{"name":"Johny"}');
        $documentStore->deleteDocument('users', '123');

        $this->assertEquals(0, $documentStore->countDocuments('users'));
    }

    public function test_deleting_non_existing_document()
    {
        $documentStore = $this->getDocumentStore();

        $documentStore->deleteDocument('users', '123');

        $this->assertEquals(0, $documentStore->countDocuments('users'));
    }

    public function test_throwing_exception_if_looking_for_non_existing_document()
    {
        $documentStore = $this->getDocumentStore();

        $this->expectException(DocumentException::class);

        $documentStore->getDocument('users', '123');
    }

    public function test_throwing_exception_if_looking_for_previously_existing_document()
    {
        $documentStore = $this->getDocumentStore();

        $documentStore->addDocument('users', '123', '{"name":"Johny"}');
        $documentStore->deleteDocument('users', '123');

        $this->expectException(DocumentException::class);

        $documentStore->getDocument('users', '123');
    }

    public function test_dropping_collection()
    {
        $documentStore = $this->getDocumentStore();
        $documentStore->addDocument('users', '123', '{"name":"Johny"}');
        $documentStore->addDocument('users', '124', '{"name":"Johny"}');

        $documentStore->dropCollection('users');

        $this->assertEquals(0, $documentStore->countDocuments('users'));
    }

    public function test_retrieving_whole_collection()
    {
        $documentStore = $this->getDocumentStore();

        $this->assertEquals([],$documentStore->getAllDocuments('users'));

        $documentStore->addDocument('users', '123', '{"name":"Johny"}');
        $documentStore->addDocument('users', '124', '{"name":"Franco"}');

        $this->assertEquals([
            '{"name":"Johny"}',
            '{"name":"Franco"}'
        ], $documentStore->getAllDocuments('users'));
    }

    public function test_retrieving_whole_collection_of_objects()
    {
        $documentStore = new DbalDocumentStore(
            CachedConnectionFactory::createFor(new DbalReconnectableConnectionFactory($this->getConnectionFactory())),
            true,
            InMemoryConversionService::createWithConversion(
                new \stdClass(),
                MediaType::APPLICATION_X_PHP,
                \stdClass::class,
                MediaType::APPLICATION_JSON,
                TypeDescriptor::STRING,
                '{"name":"johny"}'
            )
        );

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->addDocument('users', '123', new \stdClass());
        $documentStore->addDocument('users', '124', new \stdClass());

        $this->assertEquals([new \stdClass(),new \stdClass()], $documentStore->getAllDocuments('users'));
    }

    public function test_dropping_non_existing_collection()
    {
        $documentStore = $this->getDocumentStore();

        $documentStore->dropCollection('users');

        $this->assertEquals(0, $documentStore->countDocuments('users'));
    }

    public function test_replacing_document()
    {
        $documentStore = $this->getDocumentStore();

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->addDocument('users', '123', '{"name":"Johny"}');
        $documentStore->upsertDocument('users', '123', '{"name":"Johny Mac"}');

        $this->assertEquals('{"name":"Johny Mac"}', $documentStore->getDocument('users', '123'));
    }

    public function test_upserting_new_document()
    {
        $documentStore = $this->getDocumentStore();

        $this->assertEquals(0, $documentStore->countDocuments('users'));

        $documentStore->upsertDocument('users', '123', '{"name":"Johny Mac"}');

        $this->assertEquals('{"name":"Johny Mac"}', $documentStore->getDocument('users', '123'));
    }

    public function test_excepting_if_trying_to_add_document_twice()
    {
        $documentStore = $this->getDocumentStore();

        $this->expectException(DocumentException::class);

        $documentStore->addDocument('users', '123', '{"name":"Johny"}');
        $documentStore->addDocument('users', '123', '{"name":"Johny Mac"}');
    }

    protected function setUp(): void
    {
        $this->cachedConnectionFactory = CachedConnectionFactory::createFor(new DbalReconnectableConnectionFactory($this->getConnectionFactory()));
        $this->cachedConnectionFactory->createContext()->getDbalConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->cachedConnectionFactory->createContext()->getDbalConnection()->rollBack();
    }

    private function getDocumentStore(): DocumentStore
    {
        return new DbalDocumentStore(
            CachedConnectionFactory::createFor(new DbalReconnectableConnectionFactory($this->getConnectionFactory())),
            true,
            InMemoryConversionService::createWithoutConversion()
        );
    }
}