# 02 — Admin Approval Queue

Maps to mockup **05 (listing approval queue)**, PRD §5.6 (trust & safety: manual approval in V1).

## What it does

Every product a store publishes lands in `pending` and stays invisible to affiliates until a marketplace operator approves it. The queue UI shows submitted listings newest-first with product thumbnail, store, commission, and relative submit time. Approving flips the listing `live` (visible to the affiliate feed in Phase 3); rejecting flips it `rejected` with an optional reason stored on the product. Listings whose commission looks anomalous carry a red warning banner.

This is V1's main lever against a bad-actor store poisoning a thin marketplace: curation is manual on purpose. Every decision is also reported to the hub (`upsert_listing` with the decision status) — local rows mirror hub authority, never override it.

## How to use it

1. Open **CoSellHive → Approvals**.
2. The **Pending review (N)** tab lists submissions. Switch to **Approved** / **Rejected** to audit past decisions.
3. A red banner means the rate needs a second look (see flags below). Click **Approve** or **Reject**. Rejections can carry a reason, which is saved to the product's `_cosell_hive_review_note` and forwarded to the hub.
4. Decided listings can't be re-decided from the queue (409) — unpublish/re-publish the product to re-submit.

## Anomaly flag (V1 static rule)

`high_commission`: percent-type rate at **≥ 2x the average** of same-category (`product_cat`) opted-in listings, requiring a **sample of ≥ 2 peers**. Message shows the multiple and the category average. Flat-type commissions are never flagged in V1, and thin categories (< 2 peers) stay silent rather than guessing. The Phase 4 fraud model replaces this heuristic.

## Reference

**Table `wp_cosell_listings`** (created on activate via dbDelta, upgraded via `admin_init` version check):

| Column | Meaning |
| --- | --- |
| `product_id` | unique — one workflow row per product |
| `hub_listing_id` | hub catalog ID once acknowledged |
| `status` | `pending` \| `live` \| `paused` \| `rejected` (+ `draft` for opted-out) |
| `submitted_at` / `updated_at` | workflow timestamps |

**REST** (all gated by `manage_options` / `manage_woocommerce` + `wp_rest` nonce):

| Endpoint | Meaning |
| --- | --- |
| `GET /cosell-hive/v1/listings?status=pending` | `{ counts, items[] }` — items enriched with title, thumbnail, store, commission label, flags |
| `POST /cosell-hive/v1/listings/{id}/approve` | → `live` locally + product meta + hub notify |
| `POST /cosell-hive/v1/listings/{id}/reject` | → `rejected` (+ optional `reason`) locally + product meta + hub notify |

**Files:** `includes/Repository/ListingRepository.php`, `includes/Admin/ApprovalQueueController.php`, `src/admin/approval-queue.tsx`, `includes/Core/Installer.php` (tables + upgrade), `modules/Store/SyncService.php` (records rows on every sync).

**Verify:** `composer lint` · `npx tsc --noEmit` · `npm run build` · submit two same-category products at 10% and 35% — the 35% card carries the red banner; approve one, reject one with a reason, check tabs and product meta.
