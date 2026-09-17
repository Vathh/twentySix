<?php

namespace Tests\Unit\Http;

use App\Support\Http\DomainExceptionHttp;
use DomainException;
use PHPUnit\Framework\TestCase;

class DomainExceptionHttpTest extends TestCase
{
    public function test_default_is_unprocessable(): void
    {
        $this->assertSame(422, DomainExceptionHttp::status(new DomainException('x')));
    }

    public function test_conflict_code(): void
    {
        $this->assertSame(409, DomainExceptionHttp::status(new DomainException('x', DomainExceptionHttp::CONFLICT)));
    }

    public function test_forbidden_code(): void
    {
        $this->assertSame(403, DomainExceptionHttp::status(new DomainException('x', DomainExceptionHttp::FORBIDDEN)));
    }

    public function test_ignores_non_http_codes(): void
    {
        $this->assertSame(422, DomainExceptionHttp::status(new DomainException('x', 1)));
    }
}
