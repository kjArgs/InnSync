# InnSync

> An online room reservation system that connects customer bookings with arrivals, payment records, checkout, and room preparation.

## Description

**InnSync** helps customers reserve rooms online and helps hotel staff manage the resulting stay until the room is ready for the next arrival.

The MVP follows one flow:

**Online reservation → arrival → payment recording → checkout → cleaning → ready.**

All reservations originate from the customer website. Receptionists and admins cannot create walk-in, onsite, phone, or on-behalf-of reservations. Recording a payment at the front desk is allowed; creating a reservation there is not.

This is a proposal for a rewrite, not a description of a completed application.

---

## Core Features

- Customer registration, login, logout, and password reset.
- Staff login and admin-managed staff access.
- Public room information and date-based availability.
- Authenticated online reservation and an immediate booking reference.
- Customer access to their own bookings, balances, and eligible cancellation.
- Staff arrival queues, guest verification, and check-in.
- Manual recording of verified payments, including partial payments.
- Admin recording of refunds for cancelled/no-show bookings when money was collected.
- Checkout after settlement.
- Room preparation through **ready → dirty → cleaning → ready**.
- Basic room/type/rate management needed to publish available inventory.
- Booking and readiness history.

No payment gateway, online card collection, payment-proof upload, QR scanning, parking, bellhop module, dining, packages, inspection module, or advanced reporting is included.

### Scope Assumptions

- One hotel, one operating currency (initially PHP), and one configured timezone (initially Asia/Manila).
- Customers must sign in before making a reservation; browsing is public.
- One physical room per booking and one customer owner.
- Customers reserve for themselves in this version.
- Reservations become confirmed immediately when saved; no deposit is required to hold a room.
- A nightly-rate snapshot determines the final quoted room amount. Itemized taxes, extras, discounts, and cancellation fees are outside this version.
- Cancellation/no-show releases inventory and makes any collected amount refundable under the initial no-fee policy.
- No room changes, date changes, stay extensions, reopening terminal bookings, or group reservations.
- Staff manage cleaning status; there is no separate cleaner account or assignment subsystem.

---

## User Roles

### Customer

A registered user who reserves a room through the website.

**Responsibilities**

- Browse room details and search available dates.
- Enter their guest details and party size.
- Submit an online reservation.
- View their own confirmation, payment history, and balance.
- Cancel their own eligible confirmed booking.
- Maintain account details and reset their password.

Customers cannot set prices, payment records, operational booking states, or room readiness.

### Receptionist

A staff member who manages stays created through online reservations.

**Responsibilities**

- View arrivals, departures, and existing bookings.
- Verify the arriving customer and booking.
- Check guests in.
- Record money actually received or externally verified.
- Check guests out after settlement.
- Cancel eligible existing reservations or mark no-shows with a reason.
- Start cleaning and mark a room ready when cleaning is complete.

Receptionists cannot create reservations, manage staff roles, or post refunds.

### Admin

A staff member who maintains hotel inventory and access.

**Responsibilities**

- Manage staff accounts, rooms, room types, capacity, and rates.
- Perform receptionist operations on existing online bookings.
- Record authorized refunds for cancelled/no-show bookings.
- Review booking and room-readiness history.

Admin access does not introduce an onsite or on-behalf-of booking path. Public registration always creates a customer account; it cannot create staff roles.

---

## Core Workflow

### Customer Reservation

~~~text
Customer browses rooms
          |
          v
Select dates and party size
          |
          v
View available rooms
          |
          v
Register or log in
          |
          v
Select room and enter guest details
          |
          v
Server rechecks availability and calculates price
          |
          v
Save confirmed booking atomically
          |
          v
Show reference, dates, price, and unpaid balance
          |
          v
Customer views booking in My Reservations
~~~

A failed availability recheck returns a conflict and lets the customer select another room. It must not leave behind a partial booking.

### Stay and Data Management

~~~text
Existing online booking
          |
          v
Receptionist verifies guest at arrival
          |
          v
Check booking, time, readiness, and actual occupancy
          |
          v
Check in
          |
          +---- Record verified collections as received
          |
          v
Review and settle remaining balance
          |
          v
Check out and mark room dirty together
          |
          v
Staff starts cleaning
          |
          v
Staff confirms cleaning completed
          |
          v
Room ready for next arrival
~~~

Customer ownership, server-calculated prices, payment records, actual stay timestamps, and history are persisted in PostgreSQL. Updating an account or room rate does not rewrite the booking's saved contact or price snapshots.

---

## Booking Lifecycle

~~~text
CONFIRMED
    |
    v
CHECKED_IN
    |
    v
CHECKED_OUT

CONFIRMED ----> CANCELLED
CONFIRMED ----> NO_SHOW
~~~

**Confirmed means the room is reserved. It does not mean the guest has paid.**

| Transition | Actor | Required conditions |
|---|---|---|
| New → confirmed | Customer | Online submission, available active room, valid dates/capacity |
| Confirmed → checked_in | Receptionist/admin | Verified arrival; ready active room; no current occupant; permitted arrival time |
| Checked_in → checked_out | Receptionist/admin | Settled balance; actual arrival exists |
| Confirmed → cancelled | Owner or receptionist/admin | Customer before scheduled arrival; staff before actual check-in with reason |
| Confirmed → no_show | Receptionist/admin | No actual arrival; saved no-show cutoff passed |

Terminal bookings cannot be reopened. Staff actions operate on an existing booking; there is no staff reservation-creation endpoint.

---

## Room Readiness

~~~text
READY
  |
  | Guest checks out
  v
DIRTY
  |
  | Staff starts cleaning
  v
CLEANING
  |
  | Staff confirms cleaning is complete
  v
READY
~~~

Room readiness is independent of booking status and payment balance. A ready room may be occupied; readiness records its preparation state, while actual occupancy comes from a checked-in booking.

Future reservations use date availability. Immediate check-in requires readiness to be ready and no current occupant. A dirty or cleaning room can still be reserved for future dates.

Room activation is separate: an inactive room is excluded from new reservations. Staff must resolve existing future bookings before deactivation.

---

## Tech Stack

| Area | Technology |
|---|---|
| Frontend | Vue |
| Backend | Laravel |
| PHP dependencies | Composer |
| ORM | Eloquent |
| Database | PostgreSQL; psql for command-line access |
| Authentication | JWT access tokens |
| Password recovery | Expiring, single-use reset tokens |

A JWT is the format of the access token; these are not separate credentials. A password-reset token is a separate credential usable only for password recovery. This MVP has no refresh token: expired access requires login again.

The JWT guard/provider implementation will be chosen when dependencies are pinned. Do not assume enabling Laravel authentication automatically implements the requested JWT contract.

---

## Architecture

**A modular Laravel monolith with Vue**, one application deployment, and one PostgreSQL database.

~~~text
Vue customer and staff interfaces
                  |
                  v
Laravel module routes
                  |
                  v
JWT authentication + policies + request validation
                  |
                  v
Module controllers
                  |
                  v
Business actions and transactional rules
                  |
                  v
Eloquent models
                  |
                  v
PostgreSQL
~~~

Modules organize one application; they are not independently deployed services.

Suggested modules:

~~~text
Identity
Rooms
Reservations
Payments
StayOperations
Housekeeping
Overview
~~~

Admin staff management belongs to Identity. The daily overview reads other modules' committed records and owns no counters or report tables.

---

## Responsibilities

| Layer/module | Responsibility |
|---|---|
| Vue | Forms, navigation, authorized screens, pending/error states, refreshing affected views |
| Identity | Registration, login, JWT validation/revocation, reset tokens, roles, staff access |
| Rooms | Room types, capacity, rates, physical rooms, published details, activation |
| Reservations | Online creation, ownership, pricing snapshots, availability, cancellation/no-show rules |
| Payments | Verified collections, refund records, balance calculation, duplicate protection |
| StayOperations | Arrival verification, check-in, settled checkout |
| Housekeeping | Dirty/cleaning/ready transitions and readiness history |
| Overview | Today's arrivals/departures, current occupants, dirty/cleaning rooms |
| Controllers | HTTP request/response handling and delegation |
| Policies and request validators | Server-enforced actor permissions and input validation |
| Actions | State transitions, resource locking, calculations, and atomic writes |
| Eloquent/PostgreSQL | Persistence, relationships, constraints, and source of truth |

Cross-module operations call explicit actions within the same transaction. They do not copy business rules into controllers or use internal HTTP calls.

---

## Documentation

- [Backend Setup](backend/README.md)
- [Frontend Setup](frontend/README.md)
- [Business Rules](documents/BUSINESS-RULES.md)
- [System Design](documents/SYSTEM-DESIGN.md)
- [Database Relationships](documents/DATABASE-RELATIONSHIPS.md)
- [Editable Relationship Schema](documents/schema.dbml)
- [Modules Checklist](documents/MODULES-CHECKLIST.md)

These documents replace the previous staff-created reservation proposal in this folder. The older broad hotel-management plan remains separate under docs/planning.

---

## Disclaimer

**InnSync is for learning and educational purposes only.** It is a practice project for exploring application design and development, and is not intended for production hotel operations or handling real customer or payment data.

## Ownership

Original InnSync code and documentation belong to their respective authors and contributors. Third-party frameworks, libraries, trademarks, and assets belong to their respective owners and remain subject to their own licenses. Their inclusion does not imply affiliation with or endorsement of InnSync.
