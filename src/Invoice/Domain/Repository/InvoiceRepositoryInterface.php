<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Invoice Repository Interface.
 *
 * Port (Domain Boundary) for invoice persistence operations.
 *
 * Responsibilities:
 * - Persist and retrieve invoices
 * - Dispatch domain events after save operations
 * - Provide query methods for invoice retrieval
 * - Generate unique identifiers
 *
 * Implementation Notes:
 * - Infrastructure layer provides Doctrine implementation
 * - Repository converts between domain models and entities
 * - All queries must respect multi-tenancy (RLS at database level)
 * - Domain events are dispatched after successful persistence
 */
interface InvoiceRepositoryInterface
{
    /**
     * Persist an invoice and dispatch domain events.
     *
     * @param Invoice $invoice Invoice aggregate to save
     *
     * @throws \RuntimeException If save operation fails
     */
    public function save(Invoice $invoice): void;

    /**
     * Find an invoice by its unique identifier.
     *
     * @param InvoiceId $id Invoice identifier
     *
     * @return Invoice|null Invoice aggregate or null if not found
     */
    public function findById(InvoiceId $id): ?Invoice;

    /**
     * Find an invoice by its invoice number within a tenant.
     *
     * Invoice numbers are unique per tenant/year combination.
     *
     * @param TenantId      $tenantId      Tenant identifier
     * @param InvoiceNumber $invoiceNumber Invoice number (e.g., INV-2025-000001)
     *
     * @return Invoice|null Invoice aggregate or null if not found
     */
    public function findByNumber(TenantId $tenantId, InvoiceNumber $invoiceNumber): ?Invoice;

    /**
     * Find an invoice associated with an order.
     *
     * Typically, one order has one invoice, but this could return null
     * if the invoice hasn't been created yet.
     *
     * @param OrderId $orderId Order identifier
     *
     * @return Invoice|null Invoice aggregate or null if not found
     */
    public function findByOrderId(OrderId $orderId): ?Invoice;

    /**
     * Find all invoices for a specific customer.
     *
     * Returns invoices across all states (draft, issued, paid, cancelled).
     * Ordered by creation date descending (newest first).
     *
     * @param CustomerId $customerId Customer identifier
     *
     * @return Invoice[] Array of invoice aggregates (may be empty)
     */
    public function findByCustomerId(CustomerId $customerId): array;

    /**
     * Find all invoices for a specific tenant.
     *
     * Returns invoices across all states.
     * Ordered by creation date descending (newest first).
     *
     * @param TenantId $tenantId Tenant identifier
     *
     * @return Invoice[] Array of invoice aggregates (may be empty)
     */
    public function findByTenantId(TenantId $tenantId): array;

    /**
     * Find overdue invoices for a tenant.
     *
     * Returns issued invoices that are past their due date and not yet paid.
     *
     * Business Rules:
     * - Invoice must be in "issued" state
     * - Due date must be in the past (< current date)
     * - Invoice must not be paid
     * - Ordered by due date ascending (oldest first)
     *
     * @param TenantId $tenantId Tenant identifier
     *
     * @return Invoice[] Array of overdue invoice aggregates (may be empty)
     */
    public function findOverdue(TenantId $tenantId): array;

    /**
     * Delete an invoice.
     *
     * WARNING: This is a hard delete. Consider using soft delete
     * (cancel invoice) for audit purposes in most cases.
     *
     * @param InvoiceId $id Invoice identifier
     *
     * @throws \RuntimeException If delete operation fails
     */
    public function delete(InvoiceId $id): void;

    /**
     * Generate a new unique invoice identifier.
     *
     * @return InvoiceId New invoice identifier (UUID v4)
     */
    public function nextIdentity(): InvoiceId;
}
