<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Domain\ValueObject;

use App\AuditLog\Domain\ValueObject\ActionType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActionTypeTest extends TestCase
{
    public function testFromStringCreatesValidActionType(): void
    {
        $actionType = ActionType::fromString('create');

        $this->assertEquals('create', $actionType->toString());
    }

    public function testFromStringThrowsExceptionForInvalidAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action type: invalid_action');

        ActionType::fromString('invalid_action');
    }

    public function testCreateFactoryMethod(): void
    {
        $actionType = ActionType::create();

        $this->assertEquals('create', $actionType->toString());
        $this->assertTrue($actionType->isCreate());
    }

    public function testUpdateFactoryMethod(): void
    {
        $actionType = ActionType::update();

        $this->assertEquals('update', $actionType->toString());
        $this->assertTrue($actionType->isUpdate());
    }

    public function testDeleteFactoryMethod(): void
    {
        $actionType = ActionType::delete();

        $this->assertEquals('delete', $actionType->toString());
        $this->assertTrue($actionType->isDelete());
    }

    public function testLoginFactoryMethod(): void
    {
        $actionType = ActionType::login();

        $this->assertEquals('login', $actionType->toString());
        $this->assertTrue($actionType->isLogin());
    }

    public function testLogoutFactoryMethod(): void
    {
        $actionType = ActionType::logout();

        $this->assertEquals('logout', $actionType->toString());
        $this->assertTrue($actionType->isLogout());
    }

    public function testActivateFactoryMethod(): void
    {
        $actionType = ActionType::activate();

        $this->assertEquals('activate', $actionType->toString());
    }

    public function testDeactivateFactoryMethod(): void
    {
        $actionType = ActionType::deactivate();

        $this->assertEquals('deactivate', $actionType->toString());
    }

    public function testCancelFactoryMethod(): void
    {
        $actionType = ActionType::cancel();

        $this->assertEquals('cancel', $actionType->toString());
    }

    public function testRefundFactoryMethod(): void
    {
        $actionType = ActionType::refund();

        $this->assertEquals('refund', $actionType->toString());
    }

    public function testPlaceFactoryMethod(): void
    {
        $actionType = ActionType::place();

        $this->assertEquals('place', $actionType->toString());
    }

    public function testCaptureFactoryMethod(): void
    {
        $actionType = ActionType::capture();

        $this->assertEquals('capture', $actionType->toString());
    }

    public function testAuthorizeFactoryMethod(): void
    {
        $actionType = ActionType::authorize();

        $this->assertEquals('authorize', $actionType->toString());
    }

    public function testViewFactoryMethod(): void
    {
        $actionType = ActionType::view();

        $this->assertEquals('view', $actionType->toString());
    }

    public function testExportFactoryMethod(): void
    {
        $actionType = ActionType::export();

        $this->assertEquals('export', $actionType->toString());
    }

    public function testEquals(): void
    {
        $actionType1 = ActionType::create();
        $actionType2 = ActionType::create();
        $actionType3 = ActionType::update();

        $this->assertTrue($actionType1->equals($actionType2));
        $this->assertFalse($actionType1->equals($actionType3));
    }

    public function testIsCreateReturnsFalseForOtherTypes(): void
    {
        $actionType = ActionType::update();

        $this->assertFalse($actionType->isCreate());
    }

    public function testIsUpdateReturnsFalseForOtherTypes(): void
    {
        $actionType = ActionType::create();

        $this->assertFalse($actionType->isUpdate());
    }

    public function testIsDeleteReturnsFalseForOtherTypes(): void
    {
        $actionType = ActionType::create();

        $this->assertFalse($actionType->isDelete());
    }

    public function testIsLoginReturnsFalseForOtherTypes(): void
    {
        $actionType = ActionType::create();

        $this->assertFalse($actionType->isLogin());
    }

    public function testIsLogoutReturnsFalseForOtherTypes(): void
    {
        $actionType = ActionType::create();

        $this->assertFalse($actionType->isLogout());
    }

    public function testAllValidActionsCanBeCreated(): void
    {
        $validActions = ['create', 'update', 'delete', 'login', 'logout', 'activate', 'deactivate', 'cancel', 'refund', 'place', 'capture', 'authorize', 'view', 'export'];

        foreach ($validActions as $action) {
            $actionType = ActionType::fromString($action);
            $this->assertEquals($action, $actionType->toString());
        }
    }
}
