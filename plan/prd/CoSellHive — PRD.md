# CoSellHive — PRD

**Model B: Open, cross-store product marketplace with AI-assisted discovery**

Status: Draft v1 · Owner: Jay

---

## 1. Vision

A WordPress plugin that turns any WooCommerce store into a node in a shared affiliate network. Store owners publish products with a commission offer into a central marketplace; anyone running the plugin as an "affiliate" can pull those products onto their own site and earn commission on resulting sales — without ever touching fulfillment, payment, or inventory.

Positioning: **"ShareASale / Refersion Marketplace, native to WordPress — install a plugin instead of signing up for a SaaS dashboard."** WooCommerce runs a large share of small e-commerce and most of those store owners don't want a separate SaaS login, a JS pixel, and a second billing relationship. We make the marketplace and the store live in the same admin the owner already uses.

## 2. Users

| Role | Wants | Does not want to deal with |
| --- | --- | --- |
| **Store Owner** | More sales channels without hiring a marketing team | Managing individual affiliate relationships, chasing content creators |
| **Affiliate** | Products to promote that fit their audience, without inventory/fulfillment risk | Sourcing suppliers, customer service, shipping, returns |
| **Platform (us)** | Transaction volume + trust, so both sides return | Being blamed as "merchant of record" for a bad product or a bad affiliate |

## 3. Core Loop

1. Store owner marks a product "available for affiliation," sets commission (flat or %), publishes to the marketplace.
2. Affiliate browses/searches the marketplace, adds a product to their site (via shortcode/block, pulling live title, image, price, stock from our API).
3. Visitor clicks through from the affiliate's site → redirected to the store's own product/checkout page with a tracked token.
4. Order is placed and fulfilled entirely on the store owner's site.
5. Commission is calculated, held, confirmed, and eventually paid to the affiliate's wallet.

The affiliate never touches the order. The store owner never manages individual affiliate relationships one-by-one — the marketplace does discovery and matching.

## 4. Where AI Makes This Different

Being "ShareASale for WordPress" alone is a distribution wedge, not a moat — a well-funded competitor could copy the plugin. The AI layer is what makes the marketplace *better with scale* rather than just *available on WordPress*.

| AI Feature | Problem it solves | For |
| --- | --- | --- |
| **Fit-matching feed** | Affiliates drown in an undifferentiated product list; most listings are irrelevant to their audience | Affiliate |
| **Auto-generated marketing copy** | Affiliates (especially small ones) don't have time/skill to write product copy, so they never activate a listing | Affiliate |
| **Commission advisor** | Store owners don't know what commission rate actually gets affiliate uptake — they either underprice (no one promotes it) or overprice (kills margin) | Store owner |
| **Fraud/anomaly scoring** | COD + cross-site attribution is a fraud magnet (fake orders, self-referral, click farms); manual review doesn't scale | Platform |
| **Natural-language marketplace search** | Category/price filters don't capture intent ("eco-friendly skincare under $20 with good margin") | Affiliate |
| **AI storefront generator** | Non-technical affiliates (a lot of the long tail) won't build a page manually | Affiliate |

Detail on each is in §6.

## 5. Business Logic

### 5.1 Product publishing & sync

- Store owner selects a product, sets `commission_type` (`flat` | `percent`), `commission_value`, optional min/max cap.
- On publish, product metadata (title, image, price, category, stock, description) pushes to the central catalog via webhook on `woocommerce_product_object_updated` / `woocommerce_update_product_stock`.
- Fallback: periodic reconciliation poll (every few hours) in case a webhook is missed.
- Product is auto-delisted from the marketplace if: out of stock, store subscription lapses, or store owner unpublishes it.

### 5.2 Commission engine — state machine

```
click_tracked → order_placed (pending) → order_confirmed → holding_period → payable → paid
                                     ↘ (refund/cancel at any stage) → reversed
```

- **Card payment:** confirmed on successful charge; short holding period (default: store's own refund window, e.g. 3–7 days) before payable.
- **COD:** stays `pending` until order status = `completed`/delivered (store owner action or courier webhook if available); if no confirmation within a configurable window, commission expires unconfirmed.
- Refund or cancellation at any point before `paid` zeroes the commission. Refund **after** payout creates a negative balance carried forward against the affiliate's next payout — never a retroactive clawback demand.

### 5.3 Tracking & attribution

- Server-issued token per click (not cookie-only) — resilient to third-party cookie blocking / Safari ITP.
- Redirect flow: affiliate link → central hub logs click + token → redirect to store's actual product/checkout page with token param → store plugin writes token to WooCommerce order meta on order creation → reports back to hub on status change.
- Last-click attribution, configurable window (default 30 days) for v1–v2. Multi-touch attribution is a later-stage idea, not v1.
- **No embedded/remote checkout in early versions** — checkout always happens on the store owner's own site. This avoids PCI scope and per-store tax/shipping complexity entirely.

### 5.4 Payouts

- Per-affiliate wallet, balance accrues from `payable` commissions across all stores.
- v1: manual payout request → admin approval → manual transfer (bank/bKash/Nagad locally, PayPal internationally).
- v2+: automated rails (Stripe Connect / PayPal Payouts), minimum payout threshold, scheduled runs (weekly/monthly).

### 5.5 Marketplace discovery

- v1: category browse, manual curation.
- v2: search + filters (category, commission %, price range).
- v3: AI-personalized feed (see §6.1) layered on top of, not replacing, manual search.

### 5.6 Trust & safety

- Store approval before public listing (manual in v1 — this is your main lever against a bad-actor store poisoning the marketplace early).
- Affiliate approval + basic identity check before payout eligibility.
- Self-referral detection: flag matching billing info / IP / device between affiliate account and buyer.
- Dispute/report flow for either side to flag the other.

### 5.7 Monetization

Primary mechanism: marketplace take rate on transactions. Everything else below is additive once GMV justifies it.

**Revenue streams**

1. **Transaction-based (take rate)** — primary, live from V1/V2. % cut of affiliate commission (e.g. 15–20%), flat % of GMV, or a hybrid minimum-fee + %. Store owner only pays when a sale happens — easiest pitch against a free single-store plugin. *Implementation:* computed and deducted inside the commission ledger (§5.2) at the `payable` state, as a line item on every commission record — not a separate invoicing process.
2. **Subscription/SaaS tiers** — V2+. Free/Growth/Pro for store owners, gated by listed-product count, affiliate count, analytics depth, AI feature access. Optional affiliate-side pro tier. *Implementation:* tier flag on the account record, enforced at the API layer in the central hub (never in the plugin — see §11).
3. **Sponsored/boosted listings** — V3, after AI fit-matching (§6.1) is solid. Paid placement in the affiliate feed, clearly labeled. *Implementation:* a capped `sponsored_weight` blended into the ranking score so paid placement can't fully override relevance.
4. **AI feature usage fees** — V2–V3. Credits/pay-per-use for copy generation beyond a free allowance; paid storefront generator; commission-advisor insights as a premium unlock. *Implementation:* usage metering per account, billed via the same rail as subscription tiers.
5. **Payout-adjacent fees** — V3+. Small fee per payout run; FX spread on cross-border payouts. Instant/early payout advance for a fee — gated until fraud/holding-period accuracy is proven. Float/interest on held balances is flagged, not built early: money-transmitter licensing exposure in many jurisdictions, and reputational risk.
6. **Data & insights** — V3+. Aggregated, anonymized benchmark reports (feeds off the commission-advisor model); API access fees for third parties wanting catalog data, with consent and anonymization. Never sell individual affiliate/store performance data.
7. **Services revenue** — available from V1, not scalable but useful for early cash flow. Paid onboarding/migration for larger stores; agency partner referral program (also a distribution channel).
8. **B2B2B / licensing** — V4+, gated. White-label the hub infrastructure to agencies/other platforms; API-as-a-service for third-party developers. See §11 for how this is protected.
9. **Enterprise contracts** — after track record exists. Custom annual deals, volume-discounted take rate for committed spend + SLAs.

**Phasing**

| Now (V1–V2) | Once volume exists (V3) | Later, gated (V4+) |
| --- | --- | --- |
| Marketplace take rate | Sponsored/boosted listings | Instant payout advance |
| Freemium subscription tiers | AI feature usage credits | White-label/API licensing |
| Paid onboarding | Benchmark/insight reports | Float/interest income |
| Agency partner referrals | Enterprise contracts |  |

## 6. AI Features — Detail

### 6.1 Fit-matching feed

Embed each affiliate's declared niche + their existing site content (via a one-time crawl/RSS read at signup) and each product's title/description/category into a shared vector space. Score = similarity + category conversion history + commission attractiveness. Surface as "Recommended for your site" instead of a raw catalog dump. This is the single highest-leverage AI feature — it directly reduces both sides' biggest friction (irrelevant matches) and gets stronger as more transaction data accumulates.

### 6.2 Auto-generated marketing copy

From product metadata, generate a short blurb, a social caption, and a page-ready description in the affiliate's apparent tone (inferred from their site content). Affiliate can accept, edit, or regenerate. Removes the "I don't have time to write this" activation barrier for the long tail of small affiliates.

### 6.3 Commission advisor

Given a product's category, price, and margin (if the store owner opts to share cost data), recommend a commission range benchmarked against marketplace-wide uptake data for similar products ("products in this range at 15-20% get picked up by 3x more affiliates than at 8%"). Solves store owners' cold-start pricing problem.

### 6.4 Fraud/anomaly scoring

A scoring model over click velocity, IP/device overlap between affiliate and buyer, COD non-delivery rate by affiliate, and order-timing patterns. Feeds directly into the commission state machine — high-risk orders get an extended holding period or manual review instead of auto-progressing to `payable`. This needs real transaction volume to train against, so it's a v3+ feature, not v1.

### 6.5 Natural-language marketplace search

Semantic search over the catalog ("eco-friendly skincare under $20 with 20%+ commission") instead of filter forms. Straightforward once the catalog has embeddings from §6.1.

### 6.6 AI storefront generator

One-click generated themed landing page (layout + copy + a curated product set) from a handful of selected products. Lowers the bar for non-technical affiliates to get a working page instead of hand-building one. A scale/moat feature, not core-loop-critical — sequence it late.

## 7. Version Roadmap

### V1 — Foundation (prove the trust loop)

*No AI. No self-serve marketplace yet. Curated and manual on purpose — the goal is correctness, not scale.*

- WP plugin: store role + affiliate role, central hub API
- Product publish → **admin-approved** curated marketplace (quality control while the network is thin)
- Full commission state machine (flat/% commission)
- Redirect-based tracking, server-issued token + cookie fallback
- Manual payout via admin-approved requests
- Basic affiliate dashboard: browse curated listings, get embed/link
- Pilot with a small hand-picked group (10–20 stores, 20–50 affiliates) to validate commission math and payout trust before opening up

### V2 — Self-serve marketplace + first AI

- Open self-serve publish/browse (remove manual curation bottleneck; automated basic listing checks instead)
- Search & filters (category, commission %, price)
- Automated payouts (Stripe/PayPal + regional wallets)
- Automated refund/clawback tied to WooCommerce order-status webhooks
- **AI: fit-matching feed (6.1)**
- **AI: auto-generated marketing copy (6.2)**
- Platform fee turned on (monetization live)

### V3 — Trust & intelligence layer

- **AI: fraud/anomaly scoring (6.4)** feeding the commission state machine
- **AI: commission advisor (6.3)**
- **AI: natural-language search (6.5)**
- Analytics dashboards (store: top affiliates/conversion; affiliate: earnings/top products)
- Consolidated cross-store affiliate wallet if not already unified

### V4 — Scale & moat

- **AI: storefront generator (6.6)**
- Multi-currency / multi-region payout rails
- Optional tiered/multi-level affiliate structure
- Public API/webhooks (Zapier-style integrations)
- Embeddable widget for non-WordPress sites (expand beyond the WP niche)
- Store/affiliate reputation & rating system

## 8. Platform Expansion — Shopify & Multi-Platform SaaS (Gated, Not Roadmapped)

**Not part of V1–V4. This is a set of trigger conditions, not a planned phase.**

### 8.1 Why this isn't a natural "next version"

WordPress is a genuine gap: no existing WP plugin runs a true cross-store affiliate marketplace. Shopify is not — **UpPromote already owns this exact mechanic** on Shopify (4.9 rating, 2,600+ reviews), with its own affiliate-facing marketplace as its headline differentiator against a long tail of other apps (GoAffPro, Social Snowball, BixGrow, Refersion, Affiliatly, LeadDyno, ReferralCandy, Tapfiliate). Porting the product as-is means competing on feature parity against an entrenched leader, on a platform where our "no separate SaaS login" wedge doesn't apply (everything on Shopify is already an app-store experience).

### 8.2 Barriers

- **Technical:** a second, non-trivial integration — OAuth + Admin GraphQL API + embedded App Bridge UI, not a port of the WooCommerce REST/webhook connector. Mandatory GDPR compliance webhooks (customer data request/redact, shop redact) required to pass app review.
- **Platform economics:** not the blocker — Shopify takes 0% revenue share up to $1M lifetime app revenue, 15% above that. The cost is distribution and support, not platform fees.
- **Distribution:** Shopify App Store search is pay-to-play-adjacent; competing apps have years of reviews and, in some cases, paid growth budgets.
- **Support:** a second merchant profile and platform to support with a still-small team.
- **Strategic:** our differentiator has to shift from "the marketplace exists" (already true on Shopify) to "the marketplace is smarter" — i.e., it only works if the AI layer (§6) is genuinely ahead of what UpPromote/Refersion offer.

### 8.3 Gating criteria — don't evaluate Shopify entry until ALL of these hold on WooCommerce

- V2 self-serve marketplace live for 6+ months with organic (non-hand-held) store and affiliate signups
- At least one AI feature from §6 (fit-matching or auto-copy) live and measurably improving activation or conversion vs. a pre-AI baseline
- Fraud/clawback rate stable and low enough to trust the commission engine unattended
- A funding or revenue position that can absorb a second engineering + support track without slowing WooCommerce development

### 8.4 If/when triggered

- Build Shopify as a **second connector into the existing central hub**, not a new product — commission engine, AI layer, payouts, and fraud scoring stay platform-agnostic; only the store-side connector (Admin GraphQL API vs. WooCommerce REST) and app-store packaging/billing differ.
- Position explicitly against the incumbent's weak point (smarter matching / lower fraud vs. "a marketplace"), never on feature parity alone.
- Budget for GDPR compliance webhooks and Shopify App Store review as fixed engineering cost, not a formality.

### 8.5 Full platform-agnostic SaaS (becoming a Refersion/ShareASale/Impact competitor)

Treat this as an option the central-hub architecture *preserves*, not a plan being executed. Those incumbents have years of fraud data, existing affiliate rosters, and funding well beyond bootstrap scale — this is a multi-year, likely externally-funded undertaking, and premature pursuit of it risks diluting focus from the WooCommerce wedge that's actually winnable now.

## 9. Key Risks / Open Decisions

- **Cold start:** two-sided marketplace — no affiliates without products, no products without affiliate traffic. V1's manual curation and hand-picked pilot exists specifically to de-risk this before opening the gates.
- **Fee resistance:** store owners can already get a free single-store affiliate plugin (AffiliateWP-style); the pitch has to be "we bring you affiliates," not "we track commissions," or the platform cut won't feel worth it.
- **Webhook reliability at scale:** WooCommerce sites vary wildly in hosting quality; the reconciliation-poll fallback in §5.1 isn't optional.
- **Merchant-of-record clarity:** contracts need to state plainly that the store owner is the seller of record — the platform is a marketing/referral layer, not a party to the sale. Get this reviewed legally before opening the public marketplace (V2).
- **Cross-border payouts:** if affiliates and stores span countries (likely, given WordPress's global footprint), payout rails and tax reporting get complex fast — scope V1/V2 to one or two regions first.

## 10. Commission Enforcement — Making Sure the Platform Actually Gets Paid

The core risk: this is a **self-hosted plugin**, not a platform-controlled app like a Shopify app. A store owner has root access to their own server and could, in principle, modify the plugin, skip the webhook call, or otherwise avoid reporting a commission-bearing order. Enforcement can't rely on "the plugin behaves correctly" — it has to make circumventing the platform cost more than it saves.

### 10.1 Core principle

The central hub is the sole source of truth. The plugin is a thin reporter with no authority to grant itself a "confirmed, fee-free" state — that state only exists if the hub says so. Everything below is different implementations of this one idea.

### 10.2 Concrete API surface

- `POST /v1/events/order` — signed webhook from the plugin on order create/status-change: `{order_id, attribution_token, status, amount, currency, timestamp}`.
- Signature header: `X-Signature: HMAC-SHA256(request_body, install_secret)`, plus `X-Timestamp`; requests older than \~5 minutes are rejected outright (replay protection).
- `install_secret` is issued once, at plugin activation, via a paired key exchange (site registers its URL, hub returns a secret that's stored in `wp_options` and never re-displayed) — not embedded in distributed code.
- `GET /v1/reconcile/{store_id}?since=<timestamp>` — a **hub-initiated pull**, independent of anything the plugin reports, using a read-only WooCommerce REST API key (scoped to `read` on orders) granted once at store connection.

### 10.3 Reconciliation cadence & escalation

- Run the reconciliation pull on a schedule (e.g., every 24h, more frequently for high-volume stores) and diff: orders in the store's own WooCommerce data that carry an attribution token vs. orders present in the hub's ledger.
- Allow a small tolerance (e.g., low single-digit % mismatch) for normal timing lag between order creation and webhook delivery. Beyond that:
  1. **First flagged mismatch** → automatic notice + a short grace period (e.g., 48h) to self-correct (re-sync, check webhook config).
  2. **Repeat mismatch within a rolling window** → store's public marketplace listings auto-pause.
  3. **Clear, sustained pattern** (tracked orders consistently missing) → account suspended pending manual review, formal breach notice issued.
- This ladder is automatic, not manual triage — the deterrent only works if the cost is immediate and certain.

### 10.4 Card payments — the durable fix

- Once volume justifies the build (realistically V3/V4): move card processing to **Stripe Connect destination charges**. The customer's payment lands in a platform-controlled Stripe account; `application_fee_amount` (the take rate) is deducted automatically; the remainder transfers to the store's connected account.
- This removes the enforcement problem entirely for card orders processed this way — there's nothing to under-report because the store never touches the gross amount. The cost is becoming a payment facilitator: owning the Connect platform account and handling connected-account KYC.

### 10.5 COD — the irreducible weak point

- No payment processor sees cash-on-delivery money, so reconciliation and trust-tiering are the ceiling here, not a full fix.
- Require a track record on card-payment reconciliation (e.g., several clean months) before a store is eligible to list COD-enabled products in the public marketplace.
- A refundable deposit/bond from high-COD-volume stores is a real mechanic other marketplaces use as insurance against systematic under-reporting — worth considering at V3+, not before; it adds onboarding friction that isn't justified early.

### 10.6 Contractual backstop (illustrative — have counsel finalize actual language)

> "Merchant agrees that all Orders attributable to a Platform-tracked referral (evidenced by a valid Attribution Token) will be reported to the Commission Engine and remain subject to the applicable Platform Fee. Circumventing this reporting, including by technical means, constitutes a material breach and grounds for immediate suspension of Marketplace access."

## 11. IP Protection & Licensing Implementation

Two different things need protecting, and they need different strategies: the plugin code itself, and the "mechanism" (matching, fraud, ranking logic) when it's licensed out.

### 11.1 The plugin code itself

WordPress plugins are conventionally GPL(-compatible) — the PHP is effectively redistributable whether or not it's listed on wp.org, so technical/legal lockdown of the code itself is a losing fight. Design around it instead: the plugin is a thin, largely disposable client (order/click event reporting, product display) that is worthless without an active connection to the central hub. The commission ledger, matching/ranking model, fraud scoring, and marketplace catalog all live server-side, in code that's never shipped. Someone can copy the plugin; they can't copy what makes it valuable, because that part was never distributed.

### 11.2 Distribution channel decision

- **wp.org listing** requires fully GPL-compatible code and means ceding any technical lockdown of the PHP — but it's the best organic-discovery channel, matching the "just install a plugin" positioning. Use it for a **free connector tier**.
- **Direct distribution** (selling from your own site, like established commercial WP plugins do) gives more legal room but loses wp.org's discovery.
- Common middle path: a free connector plugin on wp.org for install/discovery, with paid features unlocked via license + live API access rather than by shipping different code.

### 11.3 Practical licensing mechanics

- License key: a unique identifier tied to `{account_id, plan_tier}`, issued at signup.
- **Domain lock:** the key activates against the store's URL; changing domains requires re-activation through the licensing server, which caps reactivations per period to deter sharing.
- **Heartbeat validation:** the plugin checks in with the licensing server on a schedule (e.g., daily). On failure — expired, revoked, domain mismatch — paid features degrade gracefully rather than breaking checkout outright, so a transient API hiccup doesn't punish a legitimate customer.
- **Build vs. buy:** this exact flow (key issuance, domain lock, update delivery, renewal billing) is a solved problem — Freemius or EDD Software Licensing handle it, and building it in-house is rarely worth the time versus the core product.
- The license check itself lives in distributable PHP and could technically be stripped out — but since the plugin does nothing useful without live API access, bypassing the check gains an attacker only a non-functional UI shell.

### 11.4 API response minimization

- The ranking endpoint returns `{product_id, rank}` — never the underlying similarity score, conversion weight, or feature vector.
- The fraud-check endpoint returns `{order_id, action: "release" | "hold" | "review"}` — never a numeric risk score or the specific signals that triggered it.
- Rate-limit per API key to blunt systematic probing aimed at reconstructing the ranking or fraud model by repeated querying.

### 11.5 White-label / infrastructure licensing (§5.7 item 8)

- **Default: hosted white-label.** The licensee gets a branded subdomain/config calling the hosted API — no code ever leaves the platform's infrastructure. This avoids IP leakage by construction, not by contract enforcement.
- **On-prem/source-available** is reserved for large enterprise deals where hosted genuinely isn't acceptable, priced high enough that it's rarely the path of least resistance. If ever used: source-available-not-redistributable terms, a no-derivative-competing-product clause, audit rights, and the highest-value modules (ranking weights, fraud model) either shipped compiled/obfuscated or still calling back to the platform's API for actual inference — never a fully offline-capable copy.

### 11.6 Trademark & patents

- Register the product/brand name as a trademark early — cheap relative to the protection it provides against copycats or white-label licensees implying affiliation.
- Patents are a "maybe, later": only worth pursuing if a specific mechanism (a particular attribution method or fraud-scoring approach) is genuinely novel and the cost is justified. Consult an IP attorney before investing here — not a V1–V4 priority.