# Bsync Auction Plugin: AI Update Specification

This document is the machine-oriented source of truth for updating and extending the `bsync-auction` plugin.

## 1) Plugin Identity and Load Order

- Main bootstrap file: `bsync-auction.php`
- Plugin constants:
  - `BSYNC_AUCTION_VERSION`
  - `BSYNC_AUCTION_PLUGIN_FILE`
  - `BSYNC_AUCTION_PLUGIN_DIR`
  - `BSYNC_AUCTION_PLUGIN_URL`
  - `BSYNC_AUCTION_AUCTION_CPT` = `bsync_auction`
  - `BSYNC_AUCTION_ITEM_CPT` = `bsync_auction_item`
  - `BSYNC_AUCTION_MANAGE_CAP` = `bsync_manage_auctions`
- Included modules in load order:
  1. `includes/core/activation.php`
  2. `includes/core/cpt.php`
  3. `includes/core/meta-boxes.php`
  4. `includes/core/user-profile.php`
  5. `includes/core/updater.php`
  6. `includes/admin/menu.php`
  7. `includes/admin/manager-grid.php`
  8. `includes/admin/ajax.php`
  9. `includes/frontend/templates.php`
- Activation hooks:
  - `bsync_auction_activate_plugin`
  - `bsync_auction_deactivate_plugin`

## 2) Core Data Model

### 2.1 Custom Post Types

- Auction CPT: `bsync_auction`
  - Archive slug: `/auctions/`
  - Supports: `title`, `thumbnail`
  - Block editor disabled
- Auction Item CPT: `bsync_auction_item`
  - Archive slug: `/auction-items/`
  - Supports: `title`, `thumbnail`
  - Block editor disabled

### 2.2 Auction post meta keys

- `bsync_auction_location`
- `bsync_auction_address`
- `bsync_auction_auctioneer_id`
- `bsync_auction_starts_at` (stored UTC `Y-m-d H:i:s`)
- `bsync_auction_ends_at` (stored UTC `Y-m-d H:i:s`)

### 2.3 Auction item post meta keys

- `bsync_auction_id` (linked auction post ID)
- `bsync_auction_item_number` (auto-generated, immutable by UI)
- `bsync_auction_order_number` (editable integer >= 1)
- `bsync_auction_opening_bid` (decimal string, 2dp)
- `bsync_auction_current_bid` (decimal string, 2dp)
- `bsync_auction_sold_price_internal` (decimal string, 2dp)
- `bsync_auction_buyer_id` (WP user ID)
- `bsync_auction_buyer_number` (raw buyer-number input from grid)
- `bsync_auction_item_status` values:
  - `draft`
  - `available`
  - `sold`
  - `pending`
  - `withdrawn`

### 2.4 Bids DB table

- Table: `{wp_prefix}bsync_auction_bids`
- Columns: `id`, `item_id`, `user_id`, `bid_amount`, `bid_time`, `status`
- Created via `dbDelta()` on activation

## 3) Roles and Capabilities

- Capability used by plugin: `bsync_manage_auctions`
- On activation:
  - Added to `administrator`
  - Role `bsync_auctioneer` created and granted capability
  - Existing role `bsync_member_manager` granted capability (if role exists)

## 4) User Profile Buyer Number Integration

Module: `includes/core/user-profile.php`

- Adds a section to user profile screens via:
  - `show_user_profile`
  - `edit_user_profile`
  - `user_new_form`
- Field stored in user meta key: `bsync_member_number`
- Permissions to view/save:
  - `manage_options` OR
  - `bsync_manage_auctions` OR
  - `bsync_manage_members`
- Uniqueness enforcement:
  - Validation hook: `user_profile_update_errors`
  - Save-time defensive duplicate check before `update_user_meta`
- UX helper:
  - Displays `Next available number: X` under input
  - Computed from max numeric `bsync_member_number` + 1

## 5) Buyer Number Resolution Logic

Module: `includes/core/meta-boxes.php`

- Resolver function: `bsync_auction_resolve_user_by_buyer_number($buyer_number)`
- Lookup order:
  1. User meta key list from `bsync_auction_get_buyer_number_meta_keys()`
  2. Fallback numeric buyer number as WP user ID
  3. Fallback as `user_login`
- Default meta key list (filterable):
  - `bsync_member_number`
  - `bsync_member_id_number`
  - `buyer_number`
  - `member_number`
- Filter hook: `bsync_auction_buyer_number_meta_keys`

## 6) Admin UX Modules

### 6.1 Admin menu

Module: `includes/admin/menu.php`

- Top-level menu: `Auctions`
- Subpages:
  - Auctions list
  - Add Auction
  - Items
  - Manager Item Grid
  - Auctioneers
  - How It Works
- Extra top-level fallback for member managers without auction cap:
  - `Auction Item Grid`

### 6.2 Manager Item Grid

Module: `includes/admin/manager-grid.php`

- Purpose: inline-editing auction item records
- Permission helper: `bsync_auction_user_can_manage_grid()`
- Grid rows now include auction context attributes used by JS quick-add:
  - `data-auction-id`
  - `data-auction-name`
- Grid row editable fields:
  - `order_number`
  - `buyer_number` (text input)
  - `status`
  - `opening_bid`
  - `current_bid`
  - `sold_price`
- Sorting:
  1. `bsync_auction_order_number`
  2. `bsync_auction_item_number`
- Includes helper notice showing active buyer-number meta keys
- Script localization also includes quick-add config:
  - `quickAddNonce`
  - quick-add UI labels/messages
  - status map used to render inserted rows

### 6.3 Grid AJAX save endpoint

Module: `includes/admin/ajax.php`

- AJAX action: `wp_ajax_bsync_auction_save_item_row`
- Validation checks:
  - capability
  - nonce
  - item existence and post type
  - order >= 1
  - non-negative prices
  - valid status key
  - buyer number resolvable if provided
- Side effect:
  - if sold price > 0 and status is not `withdrawn`, force status to `sold`
- Response payload contains:
  - save message
  - linked buyer display name
  - normalized buyer number
  - order number
  - normalized status (used by client to reflect sold auto-promotion)

### 6.4 Grid AJAX quick-add endpoint

Module: `includes/admin/ajax.php`

- AJAX action: `wp_ajax_bsync_auction_quick_add_item`
- Purpose: create a new auction item during live clerking without leaving the grid
- Trigger pattern: client shows quick-add only after a successful sold row save
- Validation checks:
  - capability (`bsync_auction_user_can_manage_grid`)
  - quick-add nonce (`bsync_auction_quick_add_item`)
  - valid auction post type
  - auction scope access (`bsync_auction_user_can_access_auction_scope`)
  - non-empty title
- Creation behavior:
  - creates item post (`BSYNC_AUCTION_ITEM_CPT`)
  - sets immutable fixed item number via `bsync_auction_generate_next_item_number()`
  - assigns same auction (`bsync_auction_id`)
  - sets order from request (default fallback `1`)
  - initializes status `available`, buyer empty, money fields `0.00`
- Response payload contains full row bootstrap data:
  - `itemId`, `itemNumber`, `orderNumber`, `title`, `editUrl`
  - `auctionId`, `auctionName`
  - defaults for `buyerNumber`, `status`, money fields, and buyer label

### 6.5 JS behavior

File: `assets/js/admin-grid.js`

- Click handler on `.bsync-auction-save-row`
- Sends row payload to `admin-ajax.php`
- Updates row UI after success:
  - status message
  - order number input
  - buyer number input
  - linked user label
- Quick-add flow:
  - when save succeeds and row status resolves to `sold`, append `.bsync-auction-quick-add` button to row action cell
  - on quick-add click, prompt for title and submit to `bsync_auction_quick_add_item`
  - insert returned row HTML immediately after current sold row without page refresh
  - inserted row supports the same `Save Row` action/event delegation

### 6.5 Buyer Totals payment persistence

Module: `includes/admin/buyer-receipts.php`

- Buyer totals table includes a `Paid Status` column.
- Status values are `unpaid`, `partially_paid`, `paid`.
- Default status is `unpaid` for every row unless saved.
- Payment selections (status + method checkboxes + check number) are saved per buyer and per auction-filter context in user meta key `bsync_auction_receipt_payment_data`.
- AJAX actions:
  - `wp_ajax_bsync_auction_save_buyer_receipt_payment`
  - `wp_ajax_bsync_auction_send_buyer_receipt` (also persists payment data before emailing)

File: `assets/js/admin-buyers.js`

- Handles popup actions to save payment status without emailing.
- Sends `payment_status` alongside payment method checkboxes/check number.
- Updates the table `Paid Status` cell immediately after successful save/email.

## 7) Meta Box Save Rules

Module: `includes/core/meta-boxes.php`

- Auto-generate item number on item save when empty or duplicate
- Item number generation:
  - SQL `MAX(CAST(meta_value AS UNSIGNED))` for item-number meta
  - Increment and loop until unique
- Sold status automation:
  - if `sold_price_internal > 0` and status not withdrawn, status becomes `sold`
- Data normalization:
  - money fields normalized by `bsync_auction_money()`
  - date/time normalized by `bsync_auction_normalize_datetime()`

## 8) Public Front-End Rendering

Module: `includes/frontend/templates.php`

- Hooks `template_include` to load plugin templates for archives/singles:
  - `templates/archive-bsync_auction.php`
  - `templates/single-bsync_auction.php`
  - `templates/archive-bsync_auction_item.php`
  - `templates/single-bsync_auction_item.php`
- No dependence on post content/editor fields for display
- Utility functions:
  - `bsync_auction_get_items_for_auction($auction_id)`
  - `bsync_auction_get_status_badge($status)`
  - `bsync_auction_format_money($amount)`

## 9) GitHub Updater Flow

Module: `includes/core/updater.php`

- Repository target:
  - owner `jarednolt`
  - repo `bsync-auction`
  - tags endpoint used for version discovery
- Update discovery filter:
  - `pre_set_site_transient_update_plugins`
- Plugin details popup filter:
  - `plugins_api`
- Auto-update enablement filter:
  - `auto_update_plugin` returns true for this plugin basename
- Tag parsing:
  - accepts semver-like tags (`v1.2.3` or `1.2.3`)
  - picks highest valid version
- Optional private repo token:
  - define `BSYNC_AUCTION_GITHUB_TOKEN`

## 10) Mandatory Update Checklist (AI)

When implementing any change, execute this sequence:

1. Read impacted modules and verify cross-module function dependencies.
2. Keep capability checks aligned with existing policy.
3. Preserve buyer-number uniqueness behavior unless intentionally changed.
4. If changing meta keys or resolver order, update helper notice text and docs.
5. If changing Manager Grid row actions, preserve sold-save quick-add behavior and scope checks.
6. If changing inserted-row fields/statuses, keep JS row renderer aligned with PHP response payload.
7. If changing statuses, update all of:
  - `bsync_auction_get_item_statuses()`
  - admin dropdowns
  - AJAX validation
  - status badge output
8. If changing update/version behavior:
  - bump `BSYNC_AUCTION_VERSION` in `bsync-auction.php`
  - ensure Git tag format matches semver parser
9. Run lint for changed PHP files:
  - `php -l path/to/file.php`
10. Sanity check WordPress flows:
  - create/edit auction
  - create/edit item
  - save manager grid row
  - edit user buyer number
  - inspect plugin update screen
11. Update both documentation files:
  - `AI-UPDATE-SPEC.md`
  - `HUMAN-UPDATE-GUIDE.md`

## 11) Regression Checks (Quick Add + Grid Routing)

1. Save a row as `sold` in Manager Grid and verify `Quick Add Next` appears.
2. Click quick add, provide title, verify a new row appears directly under the sold row.
3. Confirm new row defaults:
  - same auction
  - unique fixed item number
  - status `available`
  - buyer empty
  - opening/current/sold prices at zero
4. Save the newly inserted row and verify standard save endpoint still works.
5. Verify filtered grid URL uses `admin.php?page=bsync-auction-manager-grid&auction_id=...` and does not route through `edit.php?page=...`.

## 12) Safe Extension Points

- `bsync_auction_buyer_number_meta_keys` filter for buyer-number lookup keys
- Additive admin submenus under existing `bsync-auctions` parent slug
- Additional fields in grid row payload if AJAX handler and JS are updated together

## 13) Known Couplings

- `includes/admin/ajax.php` depends on helpers in `includes/core/meta-boxes.php`
- `includes/admin/manager-grid.php` depends on resolver/meta-key helper functions in `meta-boxes.php`
- `includes/core/user-profile.php` stores key used by buyer-number resolver (`bsync_member_number`)
- `includes/core/updater.php` assumes semver tags are present in GitHub repo
- `assets/js/license-scan.js` parses AAMVA data and writes to profile fields rendered by `bsync-member` (`#first_name`, `#last_name`, `#bsync_member_number`, `#bsync_member_address`, `#bsync_member_main_birthdate`)
