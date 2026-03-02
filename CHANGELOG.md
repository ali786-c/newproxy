# UpgradedProxy — Changelog

All changes are logged here in reverse chronological order (newest first).


---

## 2026-03-02

### Critical Fixes & Hardening 🛠️
- **Manual Payment Fix:** Resolved `Class 'App\Http\Controllers\Request' does not exist` error in `BillingController`.
- **Frontend Stability:** Fixed a `ReferenceError: SEOHead is not defined` on the Forgot Password page by adding missing imports in `ForgotPassword.tsx`.
- **Password Reset Logic:** Fixed a variable mismatch (`reset_url` vs `action_url`) between the controller and the dynamic templates.
- **Frontend Build**: Performed a complete production build (`npm run build`) and deployed to `htdocs/`.
- **Git Synchronization:** Performed a comprehensive push of all source and build changes to GitHub.

## 2026-03-01

### Feature: Premium Dynamic Email System 📧
- **Dynamic Template Engine:** Switched from hardcoded notifications to a database-driven system using the `email_templates` table.
- **Unified Branding:** Implemented a new premium HTML base layout (Dark Mode) with professional headers and footers.
- **Automated Triggers:** Integrated dynamic email triggers across the entire application:
    - **Welcome & Auth:** New user registration (User welcome + Admin alerts).
    - **Support Tickets:** Ticket opened confirmations and staff reply notifications.
    - **Proxy Orders:** Instant "Proxy Ready" emails with full order details.
    - **Manual Payments:** Admin alert for new proof submissions + User alerts for status updates (Approve/Reject).
    - **Security:** Password reset links and change confirmations.
- **Reliability Enhancements:**
    - **Synchronous Sending:** Disabled queuing for notifications to ensure IMMEDIATE delivery on environments like cPanel/XAMPP.
    - **Direct Routing:** Bypassed Laravel's default model serialization to prevent "Serializable" errors during email dispatch.
    - **Admin Alerts:** Standardized the `admin_notification_email` setting, configurable via the Admin Panel.
- **Code Refactor**: Moved all email notification triggers outside of `DB::transaction` blocks to ensure reliability.

---

## 2026-02-22

### Feature: Real Data Integration (Phase 4) 📊
- **Dashboard Stats:** Switched from mock data to live metrics. Now displays real **Wallet Balance**, **Active Proxies**, **Total Orders**, and **Bandwidth Usage** (pulled from Evomi API).
- **Profile Management:** Enabled real-time updates for **Display Name** and **Password** in Settings. Verified synchronization with the Laravel backend.
- **IP Allowlist System:** Built a full backend (Model, Migration, Controller) for IP-based proxy authentication.
- **API Key Management:** Integrated real API key generation and revocation. Keys are securely hashed on the backend.
- **Phase 4 Refinement & UX Polish:**
  - **Persistent Delete (Allowlist):** Implemented `POST` with `_method: DELETE` spoofing to ensure IP deletions work flawlessly on all server environments (including cPanel).
  - **API Key UX:** Refactored key generation to show the raw key directly in the popup dialog with a one-click Copy button.
  - **Settings Navigation:** Set "Profile" as the default tab to provide a more intuitive landing experience.
  - **Backend Hardening:** Added `Log` facade imports and explicit request parameter handling to `AllowlistController` to eliminate 500 errors on production.

### Feature: Integrated Proxy History & Refined Sidebar 🚀
- **Consolidated Refactor:** Merged the standalone "Proxy History" page into category-specific views. Users now visit `/app/my-proxies/:type` for an integrated experience.
- **Sidebar UX:** Renamed and reorganized product categories (Residential, Datacenter, Mobile, Datacenter IPv6, etc.) to match the Evomi ecosystem.
- **Detailed Proxy View:** Implemented a "View Details" (eye icon) modal for every historical order.
  - **Auto-Config Snippets:** Added ready-to-use samples for **cURL**, **Python (Requests)**, and **Node.js (Fetch)** within the modal.
  - **Table View:** Clean rendering of individual proxy credentials (host:port:user:pass) for each order.
- **Backend Optimization:** Updated `ProxyController::list` to support server-side filtering by product type via query parameters.

### Critical Fixes (Production Reliability) 🛠️
- **Icon Rendering Fix:** Resolved a `ReferenceError: Zap is not defined` in `SidebarNav.tsx` that caused a white page crash in production.
- **Dialog Modal Fix:** Resolved a `ReferenceError: DialogDescription is not defined` in `ProxyHistory.tsx` preventing users from opening proxy details.
- **Accessibility:** Added missing `DialogTitle` and corrected Radix UI implementation for better screen reader and browser compatibility.

---

### Phase 3: Real Proxy Flow & Connectivity Verified ✅
- **Phase 3 Completion:** Implemented the full proxy generation lifecycle: Subuser initialization → Proxy Key Retrieval → Bandwidth Allocation (Balance) → Database Persistence.
- **Endpoint Alignment:** Updated `ProxyController` to use correct product-specific hosts (`rp.evomi.com`, `dcp.evomi.com`, `mp.evomi.com`) and ports (1000, 2000, 3000) as per latest documentation.
- **EvomiService Robustness:** Enhanced `ensureSubuser` to handle cases where subuser data might be partially missing or corrupted in the local DB.
- **Connectivity Test:** Successfully verified end-to-end routing with a Palau exit node (`country-PW`) via `curl`.

### Frontend: Dynamic Data Integration
- **Dynamic Geo:** Integrated Evomi's Proxy Settings API to fetch live countries/cities for the generation form.
- **Real Stats:** Switched Dashboard from mock Supabase data to live Laravel data for balance and bandwidth monitoring.

---

## 2026-02-21

### Phase 1: Auth Bridge Integrated (Laravel Sanctum)
- **Files:** `AuthContext.tsx`, `client.ts`, `api.php`, `AuthController.php`, `cors.php`
- **Goal:** Full migration from Supabase Auth to Laravel Sanctum Backend
- **Major Changes:**
  - **AuthContext Rewrite:** Systematic removal of `@supabase/supabase-js`. Now uses `/auth/login`, `/auth/signup`, and `/auth/me` endpoints.
  - **API Client Fix:** Implemented `Bearer` token injection via `localStorage` and global 401 interceptor for auto-logout.
  - **Backend Routes:** Aligned routes to `/auth` prefix to match frontend.
  - **CORS Config:** Created `api/config/cors.php` to allow cross-origin requests from `devwithguru.site`.

### Permanent Fix: Supabase Purge (Anti-Crash)
- **Problem:** "SupabaseUrl is required" error causing white page on live site.
- **Root Cause:** Legacy components (CurrencyContext, Admin Pages) still initializing Supabase client.
- **Fix:**
  - Mocked Supabase client in `integrations/supabase/client.ts` to satisfy orphans.
  - Purged `supabase.from` calls from high-level contexts.
  - Migrated `CurrencyContext` to static data + `Intl.NumberFormat`.

### Phase 2: Evomi API Integration (Subuser & Usage)
- **Files:** `EvomiService.php`, `SubuserController.php`, `ProxyController.php`, `users` table migration.
- **Goal:** Connect Laravel Backend to Evomi Reseller API for real proxy management.
- **Major Changes:**
  - **EvomiService:** Core wrapper for Evomi API (creation, bandwidth allocation, usage data).
  - **Subuser Management:** Created `SubuserController` to link Laravel users to unique Evomi subuser identities.
  - **Usage Tracking:** Implemented server-side caching (5 min) for subuser usage stats to optimize dashboard performance.
  - **Database Migration:** Added `evomi_username` and `evomi_subuser_id` to `users` table.
  - **Frontend Hooks:** Added `useSubuserStatus` and `useSetupSubuser` hooks to `use-backend.ts`.
  - **Debug Route:** Added temporary public route `/api/test-evomi` for verification.

### Fix: Install Wizard — HTTP 500 on Install Now Button
- **File:** `api/install.php` (complete rewrite)
- **Problem:** Clicking "Install Now" caused HTTP 500 on cPanel
- **Root Cause:** No error handling around `exec()` calls + wrong PHP binary path on shared hosting
- **Fix:**
  - Added `ob_start()` + `error_reporting(0)` + `try/catch(Throwable)` to prevent all 500 errors
  - PHP binary autodetection: tries 8 cPanel-specific paths (`ea-php83`, `ea-php82`, `/usr/local/bin/php`, etc.)
  - `exec()` availability check with `shell_exec()` fallback
  - Graceful degradation: if both disabled, shows friendly message to run migrations via cPanel Terminal

### Fix: Install Wizard — React Router 404 on `/api/install.php`
- **File:** `htdocs/index.php` (complete rewrite)
- **Problem:** Visiting root domain showed React's 404 page instead of install wizard
- **Root Cause:** `index.php` redirecting to `/api/install.php` caused an infinite redirect loop → browser loaded React SPA → React Router showed 404
- **Fix:** Embedded the full installation wizard **directly inside `index.php`** — no redirect at all. When `installed.lock` is missing, PHP serves the wizard HTML. When installed, serves React SPA.

### Fix: api/.htaccess — Updated for install.php passthrough
- **File:** `api/.htaccess`
- **Change:** Added `RewriteRule ^install\.php$ - [L]` to serve `api/install.php` directly without bridging to `public/`

### New File: `api/install.php`
- Moved install wizard from `api/public/install.php` to `api/install.php` (root of api folder)
- Accessible directly at `yourdomain.com/api/install.php` without `.htaccess` bridging

---

## 2026-02-20

### Build: cPanel Deployment Setup
- Compiled React frontend with `npm run build`
- Copied `dist/` contents to `htdocs/`
- Configured `.htaccess` for SPA routing + API proxy
- Created `api/.htaccess` to bridge requests to Laravel `public/`

### New File: Root `index.php`
- PHP entry point at `htdocs/` root
- Checks `api/storage/installed.lock` to decide: show installer or serve React SPA

### New File: `api/public/install.php`  *(superseded by `api/install.php`)*
- Original 5-step install wizard
- Replaced by the version at `api/install.php`

---

## UI Rebuild Reminder
> **Every time React/frontend code changes:**
> ```
> cd htdocs-react-src
> npm run build
> cp -r dist/* c:\xampp\htdocs\
> git add . && git commit -m "..." && git push
> ```
