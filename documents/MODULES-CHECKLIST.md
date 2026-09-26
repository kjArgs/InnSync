# InnSync — Modules Checklist

> Implementation checklist for the online-only reservation MVP.

All boxes begin unchecked. These are proposed tasks, not claims that the existing project implements them. Follow [Business Rules](BUSINESS-RULES.md), [System Design](SYSTEM-DESIGN.md), and [Database Relationships](DATABASE-RELATIONSHIPS.md).

## 0. Application Foundation

- [ ] Create the Laravel application and Vue frontend as one modular monolith.
- [ ] Configure Composer autoloading and register module routes/providers.
- [ ] Configure PostgreSQL and versioned migrations.
- [ ] Set hotel timezone/currency, arrival/departure times, same-day booking cutoff, and no-show policy in configuration.
- [ ] Define consistent JSON success, validation, forbidden, not-found, and conflict responses.
- [ ] Implement shared idempotent-command handling and canonical payload hashing.
- [ ] Implement safe serialization, structured error logging, and secret redaction.
- [ ] Configure HTTPS and same-origin deployment.
- [ ] Configure reset-email delivery; do not require email delivery for reservation confirmation.
- [ ] Establish a single lock order and transaction conventions.

**Done when:** the application reaches PostgreSQL, module routes load, and shared request/error handling is usable without a separate service deployment.

## 1. Identity Module

### Backend

- [ ] Create users, auth_sessions, and password_reset_tokens migrations/models.
- [ ] Seed the initial admin through a controlled setup process.
- [ ] Implement customer-only registration with normalized unique email and password hashing.
- [ ] Pin a Laravel-compatible JWT implementation and configure its guard.
- [ ] Issue short-lived JWT access tokens tied to auth sessions.
- [ ] Validate signature/claims and current account/session state on protected requests.
- [ ] Authorize using current role and resource ownership.
- [ ] Revoke current session on logout and all sessions on disable/reset.
- [ ] Implement expiring single-use password-reset tokens and generic reset-request responses.
- [ ] Rate-limit login, registration, and password recovery.
- [ ] Implement admin-only staff creation/deactivation; prevent public staff registration.
- [ ] Keep refresh tokens out of this MVP.

### Vue

- [ ] Customer registration and login pages.
- [ ] Staff login access using the same identity rules.
- [ ] Forgot-password and reset-password pages.
- [ ] In-memory access token and bearer request handling.
- [ ] Expiry/logout returns to login without persisting a raw token in browser storage.
- [ ] Admin staff list/create/disable interface.

### Verify

- [ ] Customer cannot register as admin/receptionist by changing payload fields.
- [ ] Expired, forged, revoked, or wrong-audience JWT is rejected.
- [ ] Logout/disable revokes an otherwise unexpired token.
- [ ] Two simultaneous reset submissions consume a token at most once.
- [ ] Another customer's booking cannot be accessed by ID or reference.

## 2. Rooms Module

### Backend

- [ ] Create room_types and rooms with UUID keys, constraints, and indexes.
- [ ] Implement admin-only inventory/type/rate management.
- [ ] Publish only active rooms and safe public fields.
- [ ] Implement date/capacity availability queries using half-open intervals.
- [ ] Apply hotel-local past-date and same-day-cutoff validation.
- [ ] Block deactivation with active occupants or unresolved future reservations.
- [ ] Reject readiness changes through generic inventory update routes.

### Vue

- [ ] Public room list/details.
- [ ] Date and party-size search.
- [ ] Available/unavailable results with current price and capacity.
- [ ] Admin room/type/rate management.

### Verify

- [ ] Adjacent stays are allowed; overlapping active stays are excluded.
- [ ] Inactive rooms cannot be newly reserved.
- [ ] Dirty/cleaning does not block every future date.
- [ ] Rate edits leave existing booking snapshots unchanged.

## 3. Reservations Module

### Backend

- [ ] Create bookings and booking_events with required snapshots.
- [ ] Add online-only source CHECK, PostgreSQL overlap exclusion, and one-current-occupant index.
- [ ] Implement customer-only CreateOnlineBooking action.
- [ ] Derive ownership/source/status on the server.
- [ ] Lock room type/room and recheck inventory, capacity, cutoff, and price.
- [ ] Detect changed displayed quotes before accepting a different total.
- [ ] Save confirmed booking, creation event, and duplicate-request result atomically.
- [ ] Implement scoped customer booking list/detail and staff operational list/detail.
- [ ] Implement customer cancellation before scheduled arrival and staff cancellation with reason.
- [ ] Implement staff no-show only after saved cutoff and before any actual arrival.
- [ ] Preserve original total while deriving zero payable/refund due for cancelled/no-show bookings.
- [ ] Block terminal reopening, generic status editing, room transfers, and date amendments.

### Vue

- [ ] Customer reservation form and sign-in requirement.
- [ ] Confirmation page with booking reference, dates, price, and balance.
- [ ] My Reservations and customer booking details.
- [ ] Eligible customer cancellation action.
- [ ] Staff existing-booking search/detail and cancellation/no-show actions.
- [ ] No staff Create Booking button, form, route, or impersonation action.

### Verify

- [ ] Receptionist and admin API calls to POST /bookings receive forbidden responses.
- [ ] Customer cannot forge another owner, paid status, room readiness, or price.
- [ ] Concurrent overlapping reservations produce one winner and no orphan history.
- [ ] Same key/payload returns the same reference; changed payload under the same key fails.
- [ ] Cancellation/no-show releases inventory without deleting collections or history.

## 4. Payments Module

### Backend

- [ ] Create payments with collection/refund type, original collection link, actor, and reference fields.
- [ ] Implement staff-only completed collection recording.
- [ ] Derive totals and balance from posted financial records.
- [ ] Permit partial collections; reject zero/negative/over-balance amounts.
- [ ] Restrict collections to confirmed/checked-in bookings.
- [ ] Implement admin-only refund recording for cancelled/no-show bookings.
- [ ] Validate original collection, matching booking, available refundable amount, and reason.
- [ ] Lock booking/original collection to prevent concurrent overpayment or over-refund.
- [ ] Add payment event and idempotent response in the same transaction.
- [ ] Reject editing/deleting posted records and duplicate external receipt references.

### Vue

- [ ] Staff payment-history and record-collection form inside booking details.
- [ ] Admin refund action referencing the original collection.
- [ ] Customer read-only balance/history.
- [ ] Separate balance due from refund due.
- [ ] No payment gateway, card form, payment-proof upload, or simulated payment-success screen.

### Verify

- [ ] Partial collection followed by final collection reaches exactly zero balance.
- [ ] Customers cannot create payment records or mark themselves paid.
- [ ] Receptionists cannot post refunds.
- [ ] Concurrent collections cannot exceed balance; concurrent refunds cannot exceed collection.
- [ ] A cancellation preserves the original quoted total and exposes unresolved refund due.

## 5. StayOperations Module

### Backend

- [ ] Implement explicit staff CheckInBooking and CheckOutBooking actions.
- [ ] Check identity-verification input and require confirmed, active, ready, unoccupied room at arrival.
- [ ] Enforce saved arrival/departure bounds and reject terminal bookings.
- [ ] Record actual arrival and event atomically.
- [ ] Require checked-in state and settled balance before checkout.
- [ ] Commit actual departure, checked-out state, dirty room, both histories, and idempotent result together.

### Vue

- [ ] Existing-booking arrival verification and check-in action.
- [ ] Checkout review with collections and remaining balance.
- [ ] Clear readiness, occupancy, timing, and unpaid-balance errors.

### Verify

- [ ] No arrival action creates a new reservation.
- [ ] Dirty/cleaning/inactive/occupied room cannot accept check-in.
- [ ] Overstay blocks another actual occupant even after planned departure.
- [ ] Unpaid checkout fails without partial changes.
- [ ] Repeated successful checkout returns the prior result after the room is cleaned; it does not dirty it again.

## 6. Housekeeping Module

### Backend

- [ ] Create room_readiness_events with actor and optional originating booking.
- [ ] Implement dirty → cleaning and cleaning → ready commands for receptionist/admin.
- [ ] Lock room and validate expected state/version and absence of occupant.
- [ ] Derive/validate the originating checkout booking across the turnover cycle.
- [ ] Save readiness state and event atomically.
- [ ] Reject dirty → ready shortcuts and arbitrary ready → dirty commands.
- [ ] Return original results on retries without mutating a later cycle.

### Vue

- [ ] Dirty and cleaning queues.
- [ ] Start Cleaning action only for dirty rooms.
- [ ] Complete Cleaning action only for cleaning rooms.
- [ ] Readiness history with actor/time for staff.

### Verify

- [ ] Full ready → dirty → cleaning → ready cycle works after online-booking checkout.
- [ ] Customers cannot change readiness.
- [ ] Stale/concurrent actions do not skip a state or create duplicate transitions.
- [ ] Completed cleaning does not change reservation status or clear another occupant.

## 7. Overview Module

- [ ] Query today's expected arrivals and departures using hotel-local dates.
- [ ] Show current occupants, including overstays.
- [ ] Show dirty and cleaning rooms separately.
- [ ] Show unresolved no-shows and refunds due to permitted staff.
- [ ] Link each queue entry to the existing booking/room action.
- [ ] Refetch affected views after successful mutations.
- [ ] Keep financial/history details outside public responses.
- [ ] Do not create manually updated dashboard counters or an analytics subsystem.

**Done when:** the dashboard reconciles to bookings, room states, and payment rows after each completed flow.

## 8. Release Acceptance

- [ ] Customer registers, logs in, searches, and creates an online reservation.
- [ ] Confirmation appears without a deposit or gateway transaction.
- [ ] Customer can access only their own reservation.
- [ ] Staff can operate on the reservation but cannot create another on the customer's behalf.
- [ ] Guest arrives; staff verifies and checks in.
- [ ] Staff records verified partial and final collections.
- [ ] Checkout succeeds only after settlement and atomically creates dirty-room history.
- [ ] Staff starts and completes cleaning; next arrival can use the ready room.
- [ ] Cancellation/no-show and actual refund recording reconcile correctly.
- [ ] PostgreSQL concurrency and constraint tests pass.
- [ ] Password recovery, access expiration, logout, and staff disabling work as specified.
- [ ] Backup/restore rehearsal preserves bookings, financial records, and histories.

The first release is complete when this flow works reliably end to end. Deferred features must not introduce extra tables, roles, or user journeys into the MVP.
