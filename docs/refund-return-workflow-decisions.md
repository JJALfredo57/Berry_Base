# Pending refund and return workflow decisions

Status: requirements captured; do not implement until the owner requests a complete plan and explicitly approves it.

## Corrected authority and money flow

- The seller, not the super-admin, decides whether a customer return/refund request is approved or rejected.
- The seller must not enter or modify a refund amount.
- The refund amount is the actual confirmed amount paid by the customer for the order. It is read-only and is carried unchanged into the refund process.
- When the seller approves, the case moves to the super-admin refund queue.
- The super-admin is a refund processor only: they cannot approve, reject, or edit the amount. They send the fixed amount to the customer's registered GCash account, attach/verify the receipt, save the reference number, and mark the refund complete.
- When the seller rejects, no refund is created. The rejection requires a reason and should clearly record that the case is not refundable because the issue is customer fault or otherwise not eligible.

## Pickup flow

- A customer can report an issue while still at the pickup location and while the seller is present.
- For pickup, the seller response/note is optional on approval.
- A seller rejection still requires a reason.
- Pickup evidence is currently optional; this will be reviewed with the complete plan against the requirement for proof-based decisions.

## Delivery flow (corrected requirement)

- A customer must be able to submit a return/refund request while the delivery rider is still at the customer's location. The order must not yet be marked Delivered.
- The previous two-hour post-delivery reporting window is not the desired flow and must be replaced for this use case.
- Delivery requests require both photo evidence and video evidence as proof.
- The rider stays at the location while the customer submits the report and the seller reviews it.
- If the seller approves, the rider accepts the cake back from the customer and returns it to the seller, avoiding a second rider trip.
- The customer waits the configured working days for the fixed refund amount after the return is accepted and the refund is processed.
- If the issue is customer fault, the seller rejects it with a reason and the payment is not refundable.

## Current system behavior that conflicts with these decisions

- The current seller screen only accepts a text response; it does not approve or reject.
- The current super-admin screen approves/rejects and sets the approved amount.
- The current delivery eligibility requires Delivered or Picked Up status and a two-hour post-delivery window.
- The current pickup flow makes photos and video optional.

## Items to define in the complete plan

- The exact order/rider status that means the rider is currently at the customer location.
- The customer-facing working-day refund promise and the operational deadline for seller review while the rider waits.
- Rider confirmation steps for accepting the returned cake and confirming arrival back at the seller.
- Whether pickup evidence should remain optional or require at least photos/video for parity with delivery.
- Status names, audit trail, notifications, role permissions, migration/rollback, finance reconciliation, and regression tests.
