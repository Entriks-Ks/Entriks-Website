<?php

/**
 * IDE Stubs for MongoDB Extension
 * This file is for IDE indexing only and should not be loaded at runtime.
 */

namespace MongoDB\Driver;

class Manager
{
    public function __construct(string $uri = 'mongodb://127.0.0.1/', array $uriOptions = [], array $driverOptions = []) {}

    public function getReadConcern(): ReadConcern
    {
        return new ReadConcern();
    }

    public function getReadPreference(): ReadPreference
    {
        return new ReadPreference();
    }

    public function getWriteConcern(): WriteConcern
    {
        return new WriteConcern();
    }
}

class ReadConcern {}
class ReadPreference {}
class WriteConcern {}

namespace MongoDB\Driver\Exception;

interface Exception extends \Throwable {}

class RuntimeException extends \RuntimeException implements Exception {}
class InvalidArgumentException extends \InvalidArgumentException implements Exception {}

namespace MongoDB\BSON;

class ObjectId
{
    public function __construct(?string $id = null) {}

    public function __toString(): string
    {
        return '';
    }
}

class UTCDateTime
{
    public function __construct(int|string|null $milliseconds = null) {}

    public function toDateTime(): \DateTime
    {
        return new \DateTime();
    }
}

class Document {}
class PackedArray {}

namespace MongoDB\Driver;

interface CursorInterface extends \Iterator {}
