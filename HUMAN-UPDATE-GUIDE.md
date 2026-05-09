# Bsync Auction Plugin: Human Update Guide

This is the practical guide for you (or any teammate) to safely update the plugin later.

## 1) What this plugin does

`bsync-auction` is a standalone WordPress plugin that provides:

- Auction posts (`Auctions`)
- Auction item posts (`Auction Items`)
- Public pages for auctions and items
- A fast manager grid to update item rows during/after auction events
- Buyer-number-to-user linking
- Auto updates from GitHub tags/releases

## 2) File map (where things live)

- Main loader: `bsync-auction.php`
- Core behavior:
  - `includes/core/activation.php`
  - `includes/core/cpt.php`
  - `includes/core/meta-boxes.php`
  - `includes/core/user-profile.php`
  - `includes/core/updater.php`
- Admin:
  - `includes/admin/menu.php`
  - `includes/admin/manager-grid.php`
  - `includes/admin/ajax.php`
  - `assets/js/admin-grid.js`
- Public templates:
  - `templates/archive-bsync_auction.php`
  - `templates/single-bsync_auction.php`
  - `templates/archive-bsync_auction_item.php`
  - `templates/single-bsync_auction_item.php`

## 3) Important rules already implemented

- Item numbers are auto-generated and protected against duplicates.
- Buyer/member number is set on WP user profile under `Auction` section.
- Buyer/member number is unique per user (cannot be reused on another user).
- User profile shows the next available number under the field.
- Manager grid uses buyer number text input and resolves to a WP user.
- If sold price is entered (> 0), item status is auto-set to `sold` unless withdrawn.
- Buyer Totals & Receipts defaults every buyer row to `Unpaid` until you save payment status.
- Receipt popup payment settings are persistent (Paid Status, method checkboxes, and check number).

## 4) Before you change anything

1. Pull latest code and verify branch.
2. Make sure plugin still activates in local/staging site.
3. Open these pages and make sure current behavior is working:
   - Admin > Auctions
   - Admin > Auctions > Manager Item Grid
   - User edit screen (Auction section)
   - Public `/auctions/` and `/auction-items/`

## 5) Common update tasks

### A) Add a new item field

1. Add input in item meta box (`includes/core/meta-boxes.php`).
2. Save/validate that field in `bsync_auction_save_meta_boxes()`.
3. If needed in grid:
   - add column/input in `includes/admin/manager-grid.php`
   - include it in AJAX payload in `assets/js/admin-grid.js`
   - validate/save in `includes/admin/ajax.php`
4. If needed on public pages, add to templates in `templates/`.

### B) Change buyer number behavior

1. Primary user profile key is `bsync_member_number` in `includes/core/user-profile.php`.
2. Resolver key list lives in `bsync_auction_get_buyer_number_meta_keys()`.
3. If you add keys, update user/admin helper text to match.
4. Keep uniqueness validation in place unless intentionally changing requirements.

### C) Change statuses

If you add/rename/remove statuses, update all of these:

1. `bsync_auction_get_item_statuses()` in `includes/core/cpt.php`
2. Item edit status dropdown in `includes/core/meta-boxes.php`
3. Manager grid dropdown in `includes/admin/manager-grid.php`
4. AJAX validation in `includes/admin/ajax.php`
5. Public badge rendering in `includes/frontend/templates.php`

### D) Change menu/access behavior

1. Menu definitions are in `includes/admin/menu.php`.
2. Manager grid permissions are in:
   - `bsync_auction_user_can_manage_grid()`
   - submenu capability declarations in `menu.php`
3. Keep role/cap additions in activation file aligned with any new capabilities.

### E) Change public template output

1. Routing is in `includes/frontend/templates.php`.
2. Layout markup is in `templates/*.php`.
3. The current setup intentionally does not rely on block editor content.

### F) Change Buyer Totals payment status behavior

1. Main logic is in `includes/admin/buyer-receipts.php`.
2. Popup interactions and AJAX calls are in `assets/js/admin-buyers.js`.
3. Saved payment data is stored in user meta key `bsync_auction_receipt_payment_data` keyed by auction filter context.
4. Keep status keys aligned across PHP + JS + table column labels:
   - `unpaid`
   - `partially_paid`
   - `paid`

## 6) GitHub update process (very important)

The plugin now checks GitHub for new tags and can auto-update itself.

### Current repository

- `git@github.com:jarednolt/bsync-auction.git`

### How updates are detected

- Updater reads tags from `jarednolt/bsync-auction`.
- It chooses the highest semantic version tag.
- If that tag is higher than plugin header version, WP shows update.

### Your release steps each time

1. Update code.
2. Bump plugin version in `bsync-auction.php` (`Version:` and `BSYNC_AUCTION_VERSION`).
3. Commit and push.
4. Create a matching Git tag (example: `v1.0.1`).
5. Push the tag.
6. In WordPress admin, check plugin updates.

If your repo is private, define `BSYNC_AUCTION_GITHUB_TOKEN` in wp-config or a secure config include.

## 7) Testing checklist after any update

1. Create/edit auction with location, address, dates.
2. Create/edit auction item and confirm:
   - item number auto-fills and stays stable
   - order number saves
   - status saves
3. Open Manager Item Grid and test row save:
   - order number
   - buyer number
   - status
   - opening/current/sold price
4. Verify sold-price auto-status to sold.
5. Edit a user and test Auction buyer number field:
   - uniqueness blocks duplicate values
   - next available number displays
6. Visit public auction and item pages for rendering/sorting sanity.
7. Run PHP lint on changed files.

## 8) Useful commands

Run from plugin folder:

```bash
php -l bsync-auction.php
find includes templates -name "*.php" -print0 | xargs -0 -I{} php -l "{}"
```

Git tag release example:

```bash
git tag v1.0.1
git push origin v1.0.1
```

## 9) Known gotchas

- If tag version is lower/equal to plugin version, WP will not show update.
- Non-semver tags are ignored by updater.
- Buyer number lookup in grid can fail if users do not have a matching number/meta/user ID/login.
- Changing meta keys without migration can break existing data visibility.
- License scanner relies on AAMVA field codes and maps to user profile fields by selector IDs; if bsync-member field IDs change, scanner auto-fill will stop until selectors in `assets/js/license-scan.js` are updated.

## 10) Fast rollback strategy

1. Reinstall previous known-good plugin zip (or checkout previous tag).
2. Keep database as-is (meta keys are backward compatible in current design).
3. Re-test manager grid and user profile auction field immediately after rollback.
