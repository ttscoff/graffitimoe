<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\FlaggedMessages;
use PHPUnit\Framework\TestCase;

final class FlaggedMessagesTest extends TestCase
{
    public function test_remember_has_forget_and_sync(): void
    {
        $f = new FlaggedMessages(new ArraySession());
        $this->assertFalse($f->has(1));
        $f->remember(1);
        $f->remember(2);
        $this->assertTrue($f->has(1));
        $this->assertSame([1, 2], $f->idList());
        $f->forget(1);
        $this->assertFalse($f->has(1));
        $f->sync([9, 8]);
        $this->assertSame([9, 8], $f->idList());
        $this->assertTrue($f->has(9));
        $this->assertFalse($f->has(1));
    }
}
