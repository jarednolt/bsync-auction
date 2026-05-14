# bsync-auction Plugin Instructions

## Purpose
Manages auctions, auction items, staff assignments, manager-grid clerking flow, buyer receipts, and auction-scoped operations.

## Dependencies
- Requires bsync-member active (`BSYNC_MEMBER_VERSION` must exist).
- Uses member role/cap patterns for manager-style access integration.

## Current Behavior (May 2026)
1. Multi-auction operations are supported.
2. Items are linked to auctions by `bsync_auction_id` meta.
3. Assignment scope helpers control visibility and query filtering.
4. Manager Grid supports inline save and quick-add item flows.
5. Duplicate order numbers now produce alerts with next whole-number suggestions.
6. Manager Grid and item editor support one-click suggested order-number apply.
7. Import preserves unique order numbers and auto-fixes missing/duplicate values to next available whole number, with warning messages.

## Key Files
- Bootstrap: `bsync-auction.php`
- Permissions: `includes/core/permissions.php`
- Scope helpers: `includes/core/query-scope.php`
- Assignments: `includes/core/assignments.php`
- Grid page: `includes/admin/manager-grid.php`
- Auction AJAX: `includes/admin/ajax.php`
- Import: `includes/admin/import.php`
- Buyer receipts: `includes/admin/buyer-receipts.php`

## Development Rules
1. Never enforce auction scope only in UI. Enforce it in every server endpoint.
2. Keep assignment and scope logic centralized in permission/scope helpers.
3. Any change to role/cap checks must be tested on manager grid, buyer receipts, quick-add, and import.
4. Keep order-number uniqueness logic server-side.

## Pending Priority Hardening
1. Ensure scoped auctioneers/clerks get strict auction-limited receipts/add-item behavior where policy requires it.
2. Keep member-manager multi-auction management intact.
