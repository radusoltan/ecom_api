<?php

declare(strict_types=1);

namespace App\AuditLog\Domain\ValueObject;

/**
 * Represents the type of action performed in the audit log.
 */
final class ActionType
{
    private const CREATE = 'create';
    private const UPDATE = 'update';
    private const DELETE = 'delete';
    private const LOGIN = 'login';
    private const LOGOUT = 'logout';
    private const ACTIVATE = 'activate';
    private const DEACTIVATE = 'deactivate';
    private const CANCEL = 'cancel';
    private const REFUND = 'refund';
    private const PLACE = 'place';
    private const CAPTURE = 'capture';
    private const AUTHORIZE = 'authorize';
    private const VIEW = 'view';
    private const EXPORT = 'export';
    private const RETRY = 'retry';

    private const VALID_ACTIONS = [
        self::CREATE,
        self::UPDATE,
        self::DELETE,
        self::LOGIN,
        self::LOGOUT,
        self::ACTIVATE,
        self::DEACTIVATE,
        self::CANCEL,
        self::REFUND,
        self::PLACE,
        self::CAPTURE,
        self::AUTHORIZE,
        self::VIEW,
        self::EXPORT,
        self::RETRY,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::VALID_ACTIONS, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid action type: %s. Valid actions are: %s', $value, implode(', ', self::VALID_ACTIONS)));
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function create(): self
    {
        return new self(self::CREATE);
    }

    public static function update(): self
    {
        return new self(self::UPDATE);
    }

    public static function delete(): self
    {
        return new self(self::DELETE);
    }

    public static function login(): self
    {
        return new self(self::LOGIN);
    }

    public static function logout(): self
    {
        return new self(self::LOGOUT);
    }

    public static function activate(): self
    {
        return new self(self::ACTIVATE);
    }

    public static function deactivate(): self
    {
        return new self(self::DEACTIVATE);
    }

    public static function cancel(): self
    {
        return new self(self::CANCEL);
    }

    public static function refund(): self
    {
        return new self(self::REFUND);
    }

    public static function place(): self
    {
        return new self(self::PLACE);
    }

    public static function capture(): self
    {
        return new self(self::CAPTURE);
    }

    public static function authorize(): self
    {
        return new self(self::AUTHORIZE);
    }

    public static function view(): self
    {
        return new self(self::VIEW);
    }

    public static function export(): self
    {
        return new self(self::EXPORT);
    }

    public static function retry(): self
    {
        return new self(self::RETRY);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ActionType $other): bool
    {
        return $this->value === $other->value;
    }

    public function isCreate(): bool
    {
        return self::CREATE === $this->value;
    }

    public function isUpdate(): bool
    {
        return self::UPDATE === $this->value;
    }

    public function isDelete(): bool
    {
        return self::DELETE === $this->value;
    }

    public function isLogin(): bool
    {
        return self::LOGIN === $this->value;
    }

    public function isLogout(): bool
    {
        return self::LOGOUT === $this->value;
    }

    public function isRetry(): bool
    {
        return self::RETRY === $this->value;
    }
}
