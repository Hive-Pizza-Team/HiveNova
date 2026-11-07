# SEO decorate plan

Working plan for improving public metadata, internal linking, keyword targeting and index hygiene on the public lobby (`index.php`). Written from a 10,000 ft review; nothing here is implemented yet.

Scope: the public surface only (`includes/pages/login/*`, `styles/templates/login/*`, `includes/classes/PublicSeo.php`, `robots.txt`, `sitemap*.xml`). In-game pages stay `noindex, nofollow`.

## Current state

**Already in place**
- Title, description, canonical, hreflang (8 languages + x-default), Open Graph and Twitter cards on every lobby page, generated centrally by `PublicSeo`.
- `VideoGame` JSON-LD with `sameAs` on the homepage.
- `robots.txt`, `sitemap.xml`, `sitemap_index.xml`, with unit tests (`PublicSeoTest`, `PublicSitemapFilesTest`).
- In-game header always emits `noindex, nofollow`.

**Gaps**
1. Public pages do not link to each other. Lobby has no links to Rules, Screenshots, Hall of Fame or Ban List. Login nav is language flags only; the footer links only to external sites. Hall of Fame rows carry a report id (`rid`) but do not link to the report.
2. Little indexable text. Lobby is hero + JS visualization + login form; H1 is only the game name. Screenshots is image-only with a generic `alt`. Hall of Fame is a bare table with inline styles.
3. Questionable indexing choices. Ban List (names real players, thin) is indexable and in the sitemap. Contact (`disclamer`) is in the sitemap with little search value. Shared battle reports cannot rank (`game.php` disallowed in robots.txt, game header is `noindex`).
4. Technical issues. Sitemap has no `lastmod`; `changefreq`/`priority` are ignored by Google. `sitemap_index.xml` only points at `sitemap.xml`. Canonical URL ignores the universe (`uniN/` rewrite), so per-universe Hall of Fame content collapses to one URL. Sitemap lists English URLs only. `og:locale` is `en` (spec wants `en_US`); OG image is 4:3 (1024x768) rather than ~1.91:1. JSON-LD is homepage-only (no `WebSite`/`Organization`/`BreadcrumbList`).

## Phase 1: low-hanging fruit

Contained changes, low risk, no product decisions needed.

| # | Change | Files |
|---|--------|-------|
| 1.1 | Add a footer (and/or nav) with internal links: Rules, Screenshots, Hall of Fame, Register, plus GitHub | `main.footer.tpl`, `main.navigation.tpl`, language files |
| 1.2 | Link Hall of Fame rows to their battle reports | `page.battleHall.default.tpl`, `ShowBattleHallPage.php` |
| 1.3 | `noindex` Ban List; drop Ban List and Contact from the sitemap | `PublicSeo::INDEXABLE_PAGES`, `SITEMAP_PAGES`, `sitemap.xml`, tests |
| 1.4 | Add `lastmod` to the sitemap; remove ignored `changefreq`/`priority` | `sitemap.xml`, `PublicSitemapFilesTest` |
| 1.5 | Descriptive `alt` text on screenshots; move Hall of Fame inline styles to CSS design tokens | `page.screens.default.tpl`, `page.battleHall.default.tpl`, login CSS |
| 1.6 | Fix `og:locale` (`en_US` style codes) and add `og:locale:alternate` | `main.header.tpl`, `PublicSeo` |
| 1.7 | Add `WebSite` + `Organization` JSON-LD alongside `VideoGame` | `PublicSeo::indexJsonLd` |

Any new user-facing string must be added to all 8 language files (CI enforces this).

## Phase 2: content and keywords

Needs copywriting decisions, but still no architecture change.

- **Lobby copy.** Keyword-bearing H1 (e.g. "free browser space strategy game") and a short "what is this game" section above the activity feed. Candidate FAQ block, with `FAQPage` JSON-LD if the questions are real.
- **Keyword themes to validate** (hypotheses; check volume before committing):
  - Core: free browser space strategy game, OGame alternative, OGame clone, browser space empire game, no-download space MMO.
  - Differentiators: Hive blockchain game, play to earn Hive, Hive Keychain game, PIZZA token game; no-wipe Uni 1 and the one-week Uni 3 season.
  - Informational: OGame fleet calculator, battle simulator, tech tree.
- **Per-page titles/descriptions** reviewed against those themes (Rules and Screenshots descriptions are currently generic).
- **Hall of Fame intro text** so the page has more than a table.
- **Translated sitemap entries** (`?lang=` URLs) with hreflang annotations.

## Phase 3: deeper decisions

These need discussion before any code.

1. **Public battle reports.** Hall of Fame reports are likely the best long-tail content, but indexing them means exposing player names in search results and changing robots/noindex rules for a `game.php` page. Needs a privacy call and likely an opt-in or anonymized public report URL.
2. **Public tools.** A public tech tree and/or battle simulator are natural link-bait ("OGame fleet calculator"), but they currently sit behind login. Needs a decision on a read-only public version.
3. **Per-universe URLs.** Decide how canonical and sitemap should treat universes (`uniN/` rewrite, `UNI`) so per-universe content is not collapsed or duplicated.
4. **Content hub.** Whether to add static guide/FAQ pages (e.g. getting started, Hive integration explained) as a rankable content layer, and who maintains them.
5. **Measurement.** Search Console setup, a baseline of impressions/queries, and a way to tell whether changes help. The footer still ships legacy `ga.js` analytics; decide whether to replace it.

## Open questions

- Are shared battle report URLs meant to be publicly viewable by guests today, and in what form?
- Is the Contact page worth keeping indexable at all?
- Who owns copy for the lobby and guide content?
