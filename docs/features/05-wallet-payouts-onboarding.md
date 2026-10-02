# 05 — Wallet, Manual Payouts & Onboarding

Maps to mockups **04 (wallet & payouts)** and **07 (onboarding)**, PRD §5.4 (payouts, V1 manual tier).

## What it does

Affiliates see derived balances (pending / payable / available), request manual payouts with their account details, and track request history. Admins see a payout queue (masked details only), approve or reject requests, then mark them paid after transferring manually (bank / bKash / Nagad / PayPal). Marking paid consumes `payable` commission rows FIFO. Onboarding connects the site (license key + Store/affiliate role) and shows live entitlements; a daily heartbeat re-validates gracefully.

## How to use it

**Affiliate:** open **CoSellHive → Wallet**. The blue tile is your available balance (payable minus any post-payout refund carry). Fill amount + method + account details → **Request payout**. Details are encrypted before storage — the history table shows only masked values (`****1234`).

**Admin:** the same screen shows a **payout queue** above the wallet when you can `manage_options`: **Approve** after reviewing, transfer the money yourself, then **Mark paid** (this flips the underlying commissions to `paid`). **Reject** optionally carries a reason.

**Onboarding:** open **CoSellHive → Onboarding**, enter the license key, pick **Store** or **Affiliate**, **Activate and connect**. The header confirms tier and listing limit from live hub entitlements.

## Money rules (V1)

- Balances derive from `payable` rows only; pending/holding rows show under Pending. Available can never go negative (floored at 0).
- Requests must satisfy `1 ≤ amount ≤ available` and the `payout_minimum_minor` floor (default 0, filter `cosell_hive_setting_payout_minimum_minor`).
- Commission rows are indivisible: marking paid consumes whole rows FIFO until the amount is covered. Small dust below the smallest row stays `payable` — the payout records what it actually consumed (`consumed_minor` + note). Exact split accounting is hub-side later.
- Post-payout refunds already reduced `available` via negative adjustments (Phase 4), so they can't be paid out twice.

## Security notes

- Payout details: AES-256-CBC with per-message IVs, key derived via HKDF from `AUTH_KEY` + `SECURE_AUTH_SALT` (never in the DB). Unique salts are mandatory — requests fail closed with an explanatory error otherwise. Rotating salts orphans stored details by design (re-enter them).
- Heartbeat failure degrades, never punishes: an admin warning notice + limited premium UI. Tracking, checkout, and payouts don't consult the license, so an outage or lapse can't break money movement.

## Reference

**Table `wp_cosell_payouts`:** `affiliate_id`, `amount_minor`, `consumed_minor`, `method`, `details_enc` (text), `status` (`requested|approved|paid|rejected`), `note`, `requested_at`, `decided_at`, `decided_by`.

**REST** (nonce-checked; affiliate endpoints any logged-in user acting as self, decisions `manage_options`):

| Endpoint | Meaning |
| --- | --- |
| `GET /cosell-hive/v1/wallet` | `{balances, methods, minimum}` |
| `GET /cosell-hive/v1/payouts` | own history; admins get the masked cross-affiliate queue (pass `scope=mine` for own) |
| `POST /cosell-hive/v1/payouts` | `{amount_minor, method, details}` → request id |
| `POST /cosell-hive/v1/payouts/{id}/approve\|reject\|mark-paid` | admin decisions (`note` supported on reject) |
| `GET /cosell-hive/v1/onboarding` | `{connected, site_role, entitlements}` |
| `POST /cosell-hive/v1/onboarding/activate` | `{key, role}` → stored license + role + onboarded flag |

**Files:** `includes/Repository/PayoutRepository.php`, `includes/Security/Crypto.php`, `includes/Wallet/{WalletService,PayoutService}.php`, `includes/License/Heartbeat.php`, `modules/Affiliate/{WalletController,OnboardingController,Module}.php`, `src/affiliate/wallet.tsx`, `src/admin/onboarding.tsx`, `includes/Core/{Plugin,Installer}.php` + `uninstall.php` (new options/crons).

**Verify:** `composer lint` · `npx tsc --noEmit` · `npm run build` · as a subscriber with a `payable` row: request more than available (rejected), request valid amount, approve + mark paid as admin, confirm commissions flipped and balance dropped.
