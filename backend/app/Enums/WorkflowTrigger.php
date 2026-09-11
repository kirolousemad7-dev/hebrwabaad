<?php

namespace App\Enums;

enum WorkflowTrigger: string
{
    case OrderCreated = 'order.created';
    case OrderStatusChanged = 'order.status_changed';
    case CrmOpportunityWon = 'crm.opportunity.won';
    case CrmLeadCreated = 'crm.lead.created';
    case CalendarTaskOverdue = 'calendar.task.overdue';
    case CalendarTaskCreated = 'calendar.task.created';
    case CalendarTaskCompleted = 'calendar.task.completed';
    case ProjectDeadlineApproaching = 'project.deadline_approaching';
    case ProjectStatusChanged = 'project.status_changed';
    case CrmQuotationExpiring = 'crm.quotation.expiring';
    case PrintingRequiredDateApproaching = 'printing.required_date_approaching';
    case PrintingStatusChanged = 'printing.status_changed';
    case PrintingAssigned = 'printing.assigned';
    case PrintingCompleted = 'printing.completed';
    case PrintingReadyForDelivery = 'printing.ready_for_delivery';
    case PrintingOverdue = 'printing.overdue';
    case PrintingQuotationSent = 'printing.quotation.sent';
    case PrintingQuotationAccepted = 'printing.quotation.accepted';
    case PrintingQuotationRejected = 'printing.quotation.rejected';
    case PrintingPaymentRequirementMet = 'printing.payment_requirement_met';
    /** Alias for confirmed payments (printing + shared payment paths). */
    case PaymentConfirmed = 'payment.confirmed';
    case PrintingExecutionEligible = 'printing.execution_eligible';
    case PrintingDeliveryCompleted = 'printing.delivery.completed';
    case ApprovalApproved = 'approval.approved';
    case ApprovalRejected = 'approval.rejected';
    case QuoteRequestCreated = 'quote_request.created';
    case QuoteRequestInformationRequested = 'quote_request.information_requested';
    case QuoteRequestCustomerResponded = 'quote_request.customer_responded';
    case QuotationSent = 'quotation.sent';
    case QuotationRevisionRequested = 'quotation.revision_requested';
    case QuotationAccepted = 'quotation.accepted';
    case QuotationRejected = 'quotation.rejected';
    case QuotationExpiring = 'quotation.expiring';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
