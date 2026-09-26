# InnSync — Business Rules

> Minimum server-enforced rules for online reservation, arrival, payment recording, checkout, and room preparation.

This document follows [the MVP](../README.md). Every reservation is created online by its customer owner. Staff have no booking-creation permission, including when calling the API directly.

---

## 1. Shared Rules

- PostgreSQL is the source of truth.
- Use one hotel, one configured currency, and one configured hotel timezone.
- Store calendar stay dates as dates, event instants as UTC-aware timestamps, and money as fixed decimals.
- Reference records by foreign keys; retain referenced users, rooms, bookings, payments, and history.
- Use transactions when an action changes multiple related records.
- Use explicit action endpoints instead of general status editing.
- Idempotent commands require a key scoped to authenticated actor and operation plus a canonical payload hash. Same key/payload returns the committed result; mismatched payload returns conflict.
- Normal financial and lifecycle records are append-only or changed only through defined transitions.
- Customers never supply trusted ownership, role, state, financial amount, or actor values.

## 2. Identity and Access

- Roles are customer, receptionist, and admin.
- Public registration always sets customer, regardless of submitted role fields.
- Only admin can create or disable staff accounts.
- Passwords are hashed and never returned.
- Login returns a signed, short-lived JWT access token bound to an auth session.
- Validate signature, allowed algorithm, issuer, audience, expiration, subject, and session ID.
- Protected requests also check current account activation, current database role, and non-revoked session state.
- Logout revokes the current session immediately. Disabling an account or resetting its password revokes all sessions.
- No refresh-token workflow is included. Expiration requires login.
- Password-reset tokens are random, hashed in storage, expiring, purpose-limited, and consumed once. A reset token cannot authenticate API requests.
- Reset requests return a generic response whether or not the email exists.
- Rate-limit login, registration, reset, availability, and reservation submission.
- Customers can read/cancel only their own bookings. A booking reference is an identifier, not access authorization.

## 3. Inventory and Online Availability

- Every room belongs to one room type. Room numbers and normalized room-type names are unique.
- Capacity is a positive integer; rates are nonnegative.
- Only active rooms are offered for new bookings.
- Admin cannot deactivate a room with current occupants or future confirmed bookings until those bookings have been explicitly resolved.
- Room readiness is ready, dirty, or cleaning. Readiness is not a reservation-calendar flag.
- Booking intervals are half-open: [check_in_date, check_out_date).
- Departure must be later than arrival; new reservations cannot start in the past.
- Adults must be at least one; children are nonnegative; total occupants cannot exceed capacity.
- Confirmed and checked-in bookings hold scheduled inventory.
- Availability search is a preview. Creation locks the relevant room and repeats validations inside the transaction.
- Same-day reservations are allowed only before the configured same-day reservation cutoff. Search and submission enforce the same cutoff.
- Physical occupancy can outlast scheduled dates. An overstay never permits another actual check-in.

## 4. Online Reservation

- Only an authenticated active customer can create a booking.
- Customer owner ID comes from the authenticated user; source is fixed to online by the server.
- Staff accounts cannot invoke creation even with a customer ID in the payload.
- The room must pass current activation, dates, capacity, cutoff, and conflict checks.
- Price is current nightly rate multiplied by local-calendar nights; persist rate, currency, total, and guest-contact snapshots.
- Save booking and creation event together, with initial status confirmed.
- Display the committed reference and details immediately; confirmation email is not required to establish a booking.
- New bookings require no payment or deposit to hold inventory.
- Later rate/profile changes cannot rewrite saved snapshots.
- One booking owns one room. Date amendments, room transfer, and multiple-room reservations are deferred.

## 5. Cancellation and No-Show

- Customer cancellation requires ownership, confirmed status, and current time before the saved scheduled check-in instant.
- Receptionist/admin can cancel a confirmed booking before actual arrival, with a required reason.
- No-show requires confirmed status, no actual arrival, and time at or after the saved no-show cutoff.
- Save scheduled arrival, departure, and no-show cutoff instants at creation using hotel-local configuration. Later configuration changes do not shift existing cutoffs.
- Initial no-show cutoff is the start of the day after the arrival date in the hotel timezone; it must be before scheduled checkout.
- Cancellation/no-show releases scheduled inventory and preserves original booking total and payment records.
- Initial policy charges no cancellation/no-show fee. Effective amount payable becomes zero; net money collected is refundable.
- Record cancellation/no-show and its event atomically.
- A refund is not implied by a status change. Only recording money actually returned reduces refund due.
- Cancelled/no-show/checked-out bookings cannot be reopened.

## 6. Check-In

- Receptionist/admin checks in an existing online booking only.
- Booking must be confirmed, the room active and ready, and there must be no currently checked-in occupant.
- Verify identity against saved booking details.
- Arrival must be at or after saved scheduled check-in and before saved scheduled checkout. Early arrival and extensions have no override workflow in this MVP.
- Arrival after the no-show cutoff is possible only if staff have not marked the booking no-show and scheduled checkout has not passed.
- Payment may remain due at check-in; it is required before checkout.
- Record checked_in, actual arrival, and booking event together under room/booking locks.

## 7. Payments and Refunds

- Receptionist/admin records completed collections received or externally verified; customers cannot create financial records or mark themselves paid.
- No gateway, online card form, automated bank integration, or proof-upload workflow exists.
- Allow cash and externally verified bank transfer, with receipt/reference where applicable.
- Accept collections only for confirmed or checked-in bookings.
- Amount must be positive and no greater than the remaining balance; partial collections are permitted.
- Store the actor, method, amount, effective receipt time, creation time, and reference.
- Use request idempotency and unique external receipt namespace/reference pairs where present.
- Payments contain collection or refund records; only admins post refunds.
- Refunds are allowed only for cancelled/no-show bookings in this version.
- Every refund references a collection on that booking. Total refunds against a collection must not exceed its original amount.
- Refund records must use the booking currency and record a reason.
- Lock the booking and original collection when validating refunds; concurrency cannot over-refund.
- Posted records cannot be edited/deleted. Correction workflows beyond a permitted refund are deferred.

~~~text
net_collected = collections - refunds

For confirmed, checked_in, checked_out:
    payable = original_total
    balance_due = payable - net_collected

For cancelled or no_show:
    payable = 0
    balance_due = 0
    refund_due = net_collected
~~~

A zero-price booking has zero balance without a fabricated payment. Refund due remains visible until actual refunds are recorded.

## 8. Checkout

- Only receptionist/admin can check out a checked-in booking.
- Actual arrival must exist and financial balance must be zero.
- There is no unpaid-checkout override.
- In one transaction, record actual departure, set checked_out, set room dirty, and append booking/readiness events.
- Checkout does not delete or alter payment history.
- Repeating the same checkout command must not dirty a room again after it has subsequently been cleaned.

## 9. Room Readiness

- Initial rooms may be seeded ready; the normal turnover cycle is ready → dirty → cleaning → ready.
- Only checkout creates the ready-to-dirty turnover transition.
- Receptionist/admin starts cleaning only from dirty, with no current occupant.
- Receptionist/admin completes cleaning only from cleaning, with no current occupant.
- Do not allow dirty → ready as a shortcut.
- Every transition records actor, previous/new state, timestamp, room, and originating booking when applicable.
- A readiness event may have no booking link for initial configuration; ordinary turnover events carry the originating checkout booking.
- Readiness commands recheck expected state under room lock. Retried commands return the prior result without changing newer room state.
- Future reservations neither start cleaning nor mark a room occupied or dirty.

## 10. Data Access and History

- Customer lists/details are scoped by authenticated owner, never a caller-supplied user ID.
- Customers see their own financial history and public lifecycle events, not internal staff remarks or other accounts.
- Staff see only data required to manage stays; only admin manages inventory/staff and posts refunds.
- Booking events record creation, cancellation, no-show, check-in/out, collections, and refunds.
- Readiness events are separate room history, not a second booking lifecycle.
- Event actors are derived from authenticated identity and historical records remain readable after account deactivation.
- History rows are appended within the same transaction as their action.
- Role-sensitive API serialization must exclude password/token hashes and internal reset/session metadata.

## 11. Operational Overview

- Arrivals: confirmed bookings scheduled to arrive today in hotel-local dates.
- Departures: checked-in bookings scheduled to leave today.
- Current occupants: checked-in bookings with no actual departure, including overstays.
- Pending turnover: rooms with dirty or cleaning readiness.
- Unresolved no-shows: confirmed bookings whose saved no-show cutoff has passed.
- Refunds due: cancelled/no-show bookings with positive net collections.
- Values are queried from committed records; do not maintain manually edited counters.

## 12. Explicit Non-Goals

No staff-created reservations, walk-ins, phone booking entry, customer impersonation for booking creation, payment gateway, separate cleaner role, inspection approval, room moves, date changes, packages, parking, or advanced accounting.
