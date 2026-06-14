# Lighthouse — login (`index.php`) on `feat/login-vanilla`

**URL:** `http://localhost:8000/index.php`  
**Run:** Lighthouse 12.6 CLI, mobile perf preset, headless, extensions disabled  
**Branch:** `feat/login-vanilla` (jQuery removed, icon-font/Fancybox dropped from header)

| Metric | Value |
|--------|-------|
| Performance | **99** |
| FCP | 1.8 s |
| LCP | 1.8 s |
| TBT | 0 ms |
| CLS | **0** |

## Top opportunities (localhost, no compression)

1. **Render-blocking resources** — est. ~1.1 s (theme `formate.css`, `main.css`, `register.css` on index append)
2. **Unused CSS** — ~34 KiB
3. **Text compression** — enable gzip/brotli in prod (not `php -S`)

## Compare to prior login audit (with jQuery)

Earlier run on same stack had **CLS ~0.101** on login (uni-stats JS toggle). This branch targets **CLS 0** via server-rendered active uni row + lighter JS payload.

Raw JSON: `reports/lighthouse-login-vanilla.json` (local only, not committed).
