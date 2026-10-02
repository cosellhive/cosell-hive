# 03 — Affiliate Feed, Tracked Links & Embeds

Maps to mockups **03 (marketplace feed)** and **06 (tracked links & embed generator)**, PRD §5.3 (tracking) + §5.5 (discovery, V1 curated tier).

## What it does

Approved (`live`) listings become promotable. Affiliates browse a searchable feed, generate a tracked link per product, and embed products on their own sites via shortcode or Gutenberg block. Clicks resolve through a local `/go/ch/<ref>/` redirector that mints the tracking token **at click time** and lands the visitor on the store's product page with `?ch_token=`.

## How to use it

1. Open **CoSellHive → Marketplace**. Search, filter by minimum commission, and click **+** on any card to jump to link generation for that product.
2. Open **CoSellHive → My Links**. Pick a product: the tracked link is minted via the hub and shown with a **Copy** button.
3. Pick an embed style (**Product card / Text link / Banner**). Copy the generated shortcode — your affiliate ID is baked in — and paste it into any post or page. Or add the **CoSellHive Product** block and enter the same listing ID + affiliate ID.
4. Visitors clicking through hit `/go/ch/<ref>/?a=<affiliate>`, get a fresh token, and land on the product page. Checkout stays on the store (order attribution lands in Phase 4).

Only `live` listings render anywhere: the feed, link minting, shortcode, and block all refuse anything else (shortcode fails safe to an HTML comment, the redirector lets WP 404).

## Attribution design (why tokens are minted at click, not render)

A token baked into page HTML would be shared by every visitor (and every cache layer) — attribution would collapse to whoever loaded the page first. So static embeds carry only `{ref, affiliate_id}`; the redirector mints one token per click server-side. Cached pages stay correct by construction.

## Reference

**REST** (logged-in users + `wp_rest` nonce):

| Endpoint | Meaning |
| --- | --- |
| `GET /cosell-hive/v1/feed?search=&category=&min_commission=` | live listings, newest first; search/category via Woo query, min-commission filtered in PHP (V1 scale) |
| `POST /cosell-hive/v1/links {listing_id}` | `{token, url, ref, affiliate_id}` — hub-minted token, local go URL |

**Shortcode:** `[cosell_product id="mock_42" affiliate="3" style="card"]` — `id` accepts hub ID, `p-<product_id>`, or plain product ID; `style` is `card` (default), `text`, or `banner`.

**Block:** `cosell-hive/product-card` (`blocks/product-card/block.json`), dynamic/server-rendered through the same renderer, editor script built as `cosell-hive-product-card.js`.

**Redirect:** rewrite `^go/ch/([^/]+)/?` → `?cosell_go=$1`, handled on `template_redirect`. Base URL is filterable via `cosell_hive_go_base` — swap to the hub `go.*` domain when it exists without touching callers.

**Files:** `modules/Affiliate/{Module,FeedController,LinksController}.php`, `includes/{Marketplace/ListingPresenter,Shortcodes/ProductCard,Tracking/Redirector}.php`, `includes/Repository/ListingRepository.php` (`find_by_product_id`), `src/affiliate/{feed,links}.tsx`, `src/blocks/product-card/`, `assets/css/frontend.css` (enqueued only when an embed is present).

**Verify:** `composer lint` · `npx tsc --noEmit` · `npm run build` · approve a listing, open the feed as a subscriber, mint a link, paste the shortcode on a page, click through and confirm `?ch_token=` on the product URL.
