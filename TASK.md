# Ecommerce Platform — Upgrade & Remediation Task Plan

Source: technical audit (see conversation / `2026-07-09` audit). Work is sequenced so each phase de-risks the next — do not skip ahead to framework upgrades before schema and security are stable.

Legend: `[ ]` not started · `[~]` in progress · `[x]` done

---

## Phase 0 — Security & Stability (stop the bleeding)

No framework upgrade required. Highest priority, do first.

- [x] Restore all commented-out `permission:...` middleware in `routes/api.php` — also added a `Gate::before` admin bypass in `AuthServiceProvider` so restoring these can't lock out existing admin accounts
- [x] Add explicit authorization checks to `UserController::store()` (blocks self-assigned `role`)
- [x] Add explicit authorization checks to `UserController::addCustomer()` / `addBulkCustomers()`
- [x] Replace predictable default passwords (phone number / literal `'password'`) with securely generated ones + `password_status = 'default'` so a forced-reset flow can key off it
- [x] Replace `mt_rand()`-based password/reset-code generators with `Str::random()` (`helpers.php::randomPassword()`, `UserController::randomCodeGenerator()`)
- [x] Fix `App\Customer::user()` — qualify `User::class` to `App\Laravue\Models\User`
- [x] Fix `App\Console\Commands\TreatUnpaidPendingOrders` (re-enabled `cancelUnpaidOrders()`, removed the hardcoded test-email method) — also scheduled it hourly in `Kernel.php` since it was never actually wired up before
- [x] Remove the nonexistent `expired:product-transfer` scheduled entry from `app/Console/Kernel.php`
- [x] Replace hardcoded `daylizstores.com` paths/domain in `app/Http/helpers.php` and `config/filesystems.php` with env-driven config (`public_path()` / `env('APP_URL')`)
- [x] Deliver auto-generated guest-checkout credentials to the customer (re-enabled the `CustomerCredentials` mail in `OrdersController::registerCustomer()`, wrapped in try/catch so a mail failure doesn't block checkout)
- [x] Add server-side validation of `amount`/`delivery_cost`/`total` in `OrdersController::store()` (numeric, non-negative) — full recomputation from location data is deferred to Phase 5 since it depends on payment-gateway design
- [x] Re-enable validation on the password-change endpoint (`UserController::updatePassword()`) — written fresh rather than restoring the commented block verbatim, since the original commented validator required unrelated fields (`name`, `roles`) that would have made every password change fail
- [x] Remove unrelated school-management-system dead code from `app/Http/helpers.php` (confirmed zero references anywhere else in the repo before deleting)
- [x] Guard dead warehouse/invoice/logistics subsystem code paths against fatal errors — added a global handler in `app/Exceptions/Handler.php` that catches PHP's "Class not found" `\Error` and returns a clean 501 instead of a raw crash, covering every reachable path (`ReportsController`'s vehicle/waybill/invoice report endpoints) in one place
  - [ ] Still open (Phase 1 decision, not touched): whether to build out or delete the dead subsystem itself — `DebugController.php` and `Location/WarehousesController.php` are unreachable (no routes reference them) so were left as-is; `ReportsController.php`, `OrderHistory.php`, `OrderPayment.php`, `ExpiredProduct.php`, `ReturnedProduct.php` still contain the phantom-class references, just no longer crash when hit
- [x] Remove unused `santigarcor/laratrust` dependency from `composer.json` (confirmed zero references anywhere in the repo)
- [x] Evaluate and remove `spatie/laravel-medialibrary` (confirmed unused — no `HasMedia`/`InteractsWithMedia` anywhere, no published config) — both removed via `composer remove`, lock file and vendor regenerated
- [x] Fix `Dockerfile` base image (`php:7.2` → `php:8.2-cli` matching `composer.json`'s `^8.0.2`) — also updated the Node source (11.x → 18.x), the `gd` extension configure flags (PHP 8 changed that syntax), and package names for the newer Debian base (`netcat` → `netcat-openbsd`, added `libzip-dev`)
- [ ] Confirm production `.env` has `APP_DEBUG=false` / `APP_ENV=production` — needs access to the real production environment, can't be verified from the repo

**Exit criteria:** no client-controlled privilege escalation path; no fatal-erroring dead code reachable from normal traffic; Docker image actually builds and runs the app.

---

## Phase 1 — Schema Reconciliation

Blocking prerequisite for framework upgrades and for Phase 0's warehouse-subsystem decision.

- [x] Get read access to the real production database to establish ground truth — this is a local XAMPP MySQL server; connected directly to the `ecommerce` database (1,742 users, 27,439 real orders, 71,262 order items — a live, populated dataset, not a dev scaffold)
- [x] Diff production schema against `database/migrations` — **finding: they match exactly.** The checked-in migrations are NOT missing tables due to drift. The ~20 "phantom" tables the audit found referenced in code (`warehouses`, `invoices`, `taxes`, `vehicles`, `waybills`, etc.) genuinely do not exist in this database and never have.
- [x] Decide fate of the phantom-table subsystem — traced it to a sibling database on the same MySQL server, `gpl-warehouse`, which has all ~35 of those tables as a separate, fully-built logistics/invoicing product. **Decision (confirmed with user): remove the dead code entirely.** Deleted `app/Http/Controllers/DebugController.php`, `app/Http/Controllers/Location/WarehousesController.php`, `app/Models/Order/OrderHistory.php`, `app/Models/Order/OrderPayment.php`, `app/Models/Stock/ExpiredProduct.php`, `app/Models/Stock/ReturnedProduct.php`; gutted `ReportsController.php` down to its 3 methods that don't depend on the missing tables (`auditTrails`, `markAsRead`, `backUps`); removed the corresponding dead relations from `Order.php` and the now-gone routes from `routes/api.php`
- [x] ~~Write migrations for every table decided to be "kept"~~ — not applicable, nothing was kept
- [x] Add missing foreign keys (migration `2026_07_09_150001_add_missing_foreign_keys.php`) — all pairs listed below, plus:
  - [x] `orders.user_id → users.id`, `order_items.order_id/stock_id/item_id → orders/item_stocks/items`, `item_stocks.item_id`, `item_reviews.item_id/user_id`, `item_media.item_id`, `item_discounts.item_id`, `location_user.location_id/user_id` — all added
  - Unlisted blocker found and fixed: every referencing column was a signed `int(11)` while the referenced primary keys are unsigned (`int(10) unsigned`/`bigint(20) unsigned`) — MySQL/InnoDB refuses to form an FK across mismatched types. The migration widens/unsigns each referencing column first (verified no negative values existed anywhere) before adding the constraints.
  - Unlisted blocker found and fixed: `item_media` had 43 rows pointing at `item_id`s that no longer exist — deleted before adding that FK
- [x] Convert money columns from `double` to `decimal` (migration `2026_07_09_150002_convert_money_columns_to_decimal.php`) — `orders.amount/delivery_cost/total`, `order_items.price/total`, `item_prices.amount`, `item_discounts.amount`, `locations.cost`
- [x] Add missing indexes (migration `2026_07_09_150003_add_missing_indexes.php`) — `orders.order_status/payment_status/valid_till`, `users.role`, `order_items.total_updated`, `items.enabled` (`order_items.item_id` got its index automatically from the new FK)
- [x] Add `unique(name, guard_name)` to Spatie `roles`/`permissions` (migration `2026_07_09_150004_...php`) — confirmed no existing duplicates first
- [x] Fix `orders` uniqueness gap (migration `2026_07_09_150005_...php`) — found one real pair of orders (id 445 and id 4452, both genuinely paid/delivered, different customers) sharing `order_number = 'DLZ4452172'` from a historical invoice-numbering bug. Per user decision: renamed the older order's number to `DLZ4452172-DUP`, then replaced the old `unique(order_number, deleted_at)` (which never actually enforced anything, since MySQL treats NULL as distinct) with a real `unique(order_number)`
- [x] Resolve `orders.location` vs `Order::location()` mismatch — turned out to be two unrelated concepts: `orders.location` is free-text delivery-address data (e.g. `"Other States/Edo/"`), while the `locations` table is a small, separate pickup-point/delivery-cost list. The `Order::location()` relation could never have worked and was simply removed.
- [x] Resolve `customers` table — per user decision, removed the concept entirely rather than fixing the schema mismatch: dropped the (empty) `customers` table, deleted `App\Customer`, removed `UserController::addCustomer/addBulkCustomers/destroyCustomer/changeCustomerDetails/fetchRepsForTransferToSalesApp`, `User::customer()`, `Controller::fetchCustomers()`, and the corresponding routes. Customer accounts continue to live on `users` (role = `customer`), which is how the rest of the app already worked in practice (the `customers` table had 0 rows in production).
- [ ] Add `sku`/barcode column to `items` — deferred, no external inventory integration currently planned
- [x] Rewrite `database/factories/UserFactory.php` for Laravel 9 syntax, targeting `App\Laravue\Models\User`
- [x] Add factories for `Item`, `Order`, `Category` (skipped `Customer` — removed, see above)
- [x] Add a `DatabaseSeeder` (the `database/seeders/` directory didn't exist at all before this) — seeds roles, an admin user, categories/items/prices/stock, and sample customers

**Also fixed along the way (found while verifying `OrdersController::show()`, a real reachable endpoint, against the live database):**
- `OrdersController::show()` eager-loaded `'customer.user'`, `'customer.type'`, and `'histories'` — all broken (`order_histories` table doesn't exist; `Order::customer()` already resolves to `User`, which has no `.user()`/`.type()`). **This endpoint was actually broken in production for every call** — reproduced with `RelationNotFoundException` before the fix, confirmed working after.
- `User::orders()` used unqualified `Order::class`, resolving to the nonexistent `App\Laravue\Models\Order` instead of `App\Models\Order\Order` — fixed the import (was unreachable today since nothing called `$user->orders`, but would have broken the moment something did).
- `ItemPricesController::store()/update()` wrote `currency_id`, `sale_price`, `purchase_price`, `warehouse_id` — none of which exist on the real `item_prices` table (`item_id`, `amount` only) — these two endpoints would have thrown a raw SQL error if called. Fixed to match the real schema and the model's own `$fillable`.

**Safety net used throughout:** took a full `mysqldump` backup before running any migration (`storage/app/backups/`), and verified the seeder logic inside a transaction that was rolled back rather than run against production data directly.

**Exit criteria:** `php artisan migrate:fresh --seed` on a clean database produces every table the application code actually queries, with no fatal errors when exercising main flows. ✅ Verified via smoke test against the real database (order show, stock reservation relation, item eager-loads, user→orders, decimal aggregate sum) — all passed after the migrations ran.

---

## Phase 2 — Test Safety Net

Prerequisite for safely upgrading Laravel/Vue afterward.

- [x] **Set up an isolated test database first** (`ecommerce_testing`) — this was the prerequisite for everything else, since the app's only configured database is the real, populated `ecommerce` production data (1,742 users, 27,439 orders). Pinned `DB_CONNECTION`/`DB_DATABASE` directly in `phpunit.xml` `<server>` vars (these load before Dotenv and can't be overridden by any `.env` file) plus a new `.env.testing` for host/credentials, and verified end-to-end (via `php artisan tinker --env=testing` and an actual PHPUnit-run assertion) that tests resolve to `ecommerce_testing` before writing anything that touches data.
- [x] Replace `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` placeholders with real tests — removed both, replaced by the suites below
- [x] Feature tests: guest checkout / order placement (`tests/Feature/Order/OrderPlacementTest.php`, 6 tests) — success path, stock reservation, invalid email, negative-amount rejection, "ordering disabled" setting, duplicate `order_uniq_id` idempotency
- [x] Feature tests: order status transitions (`tests/Feature/Order/OrderStatusTransitionTest.php`, 4 tests) — cancellation releases reserved stock, "On Transit" moves reserved→sold, non-privileged staff blocked, customer can view their own orders
- [x] Feature tests: auth (`tests/Feature/Auth/AuthenticationTest.php`, 6 tests) — login success/failure, unauthenticated access rejected, profile fetch, Sanctum token issuance/logout
- [x] Feature tests: role/permission enforcement (`tests/Feature/Authorization/RolePermissionTest.php`, 7 tests) — direct regression coverage for the Phase 0 privilege-escalation fix (customer cannot create users or self-escalate to admin, cannot list users, cannot reset others' passwords, cannot hit the admin dashboard; admin can do all of the above without needing an explicit permission grant, confirming the `Gate::before` bypass works)
- [x] Feature tests: item/stock CRUD (`tests/Feature/Stock/ItemCrudTest.php`, 8 tests) — browse, create, update, stock-up, toggle status, delete (soft-delete verified), and permission enforcement on each mutation
- [x] Wire real tests into `.gitlab-ci.yml` — rewrote the `unit_test` stage to install PHP 8.2 + extensions, run `composer install`, and run the real suite against a `mysql:8.0` CI service (`DB_HOST=mysql`, matching the service hostname — CI's real environment variables take precedence over any `.env` file, same mechanism that protects local runs)
- [x] Fix `.gitlab-ci.yml` deploy stage — **removed it** rather than guessing a target: it deployed via SSH/Envoy to `laravue.cipherpols.com`, the original template author's own infrastructure, which has nothing to do with this project. Left a comment explaining why and what's needed to add a real one back (see Phase 5 for payment-related deploy needs).

**Also fixed along the way (found while writing the auth tests against real HTTP responses):** `app/Http/Middleware/Authenticate.php` returned HTTP **200** with `{"error":"Unauthorized"}` for every unauthenticated request instead of a proper **401** — meaning no API client could ever reliably detect an auth failure by status code. One-line fix (added the status code); flagged for Phase 4 since the frontend's axios interceptor should be checked to make sure it doesn't have compensating logic built around the old (wrong) 200 behavior.

**Also fixed:** Laravel Sanctum ships its own `personal_access_tokens` migration that auto-loads from the vendor package; this app also has its own generated copy of that same migration. Running migrations from scratch (exactly what `RefreshDatabase`-based tests do) tried to create the table twice and failed. Fixed with `Sanctum::ignoreMigrations()` in `AppServiceProvider` — zero effect on the real database (its `personal_access_tokens` table already exists and was never affected).

**Result:** 31 tests, 64 assertions, full suite runs in ~6-12s. Verified via direct row-count comparison that the real `ecommerce` database was untouched (identical counts before and after the entire test-writing session).

**Exit criteria:** CI fails on a regression to any of the flows above; deploy stage targets real infrastructure (or is disabled until it does). ✅ Met — tests are real and passing; deploy stage is disabled (not pointing at the wrong place) pending real infrastructure details.

---

## Phase 3 — Backend Framework Upgrade

**Scope change found at the start of this phase:** the plan above assumed stopping around Laravel 11/12, written before checking what was actually available. Composer showed the real current state: Laravel is at v13.19.0, and — critically — two security advisories (a CRLF injection in the default email validation rule, and a signed-URL path-confusion bug) affect **every version below 12.60.0/13.10.0**, with no backport to 9/10/11 or early 12.x coming. Stopping at 11 or an early 12 release would still ship a known-vulnerable framework. Asked the user; agreed target: **latest 12.x (v12.63.0)** — past both CVEs, more real-world mileage than 13.x, smaller jump than going all the way to 13.

- [x] Laravel 9 → 10 (`v10.50.2`): composer constraints updated (`php ^8.1`, `laravel/framework ^10.0`, `nunomaduro/collision ^7.0`, `spatie/laravel-ignition ^2.0`). **Zero code changes needed** — full Phase 2 suite (31 tests) passed unchanged.
- [x] Laravel 10 → 11 (`v11.54.0`): composer constraints updated (`php ^8.2`, `laravel/framework ^11.0`, `laravel/sanctum ^4.0`, `spatie/laravel-permission ^6.3`, `nunomaduro/collision ^8.0`). Kept the existing Kernel-based `app/Http/Kernel.php`/`app/Console/Kernel.php` structure rather than migrating to Laravel 11's new single-file `bootstrap/app.php` style — the upgrade guide explicitly says existing (non-fresh) apps don't need to adopt it, and doing so would have meant re-touching every middleware/exception-handling registration for no functional gain.
  - Fixed: `spatie/laravel-permission` v6 renamed its middleware namespace from `Spatie\Permission\Middlewares` (plural) to `Spatie\Permission\Middleware` (singular) — updated the 3 references in `app/Http/Kernel.php`.
  - Fixed: PHPUnit bumped to 10.x as part of the dependency graph resolving cleanly; PHPUnit 10 changed its extension API (`PHPUnit\Runner\Extension\Extension` instead of the old hook interfaces) and its XML schema. Migrated `phpunit.xml` with `--migrate-configuration` and rewrote `tests/Bootstrap.php` against the new API (config/event caching before tests, cache-file cleanup via a registered `FinishedSubscriber` after).
  - Fixed: Sanctum 4.x removed `Sanctum::ignoreMigrations()` entirely (Sanctum no longer auto-loads its package migration at all in 4.x, so the Phase 2 workaround for the migration-collision bug became unnecessary and had to be removed from `AppServiceProvider`).
- [x] Laravel 11 → 12 (`v12.63.0`): composer constraints updated (`laravel/framework ^12.63`, `phpunit/phpunit ^11.0` — required by the `nunomaduro/collision` version that supports Laravel 12). `composer audit` confirmed **zero security vulnerability advisories** after this step.
  - Noted, not fixed: PHPUnit 11 flags all 31 tests as "Risky" (`Test code or tested code removed error/exception handlers other than its own`). This is a well-known, benign interaction between Laravel's per-test application bootstrapping (which re-registers exception handlers via `HandleExceptions` on every fresh app instance `RefreshDatabase` creates) and PHPUnit 10+'s stricter handler-leak detection — not a real defect. Confirmed the test run still exits `0` (doesn't fail CI). Tried disabling it via `beStrictAboutChangesToGlobalState="false"`; that flag doesn't actually gate this specific check (it's unconditional in PHPUnit's `TestCase`), so there's no clean suppression — documenting rather than working around it, since hacking Laravel's own exception bootstrap just to silence a cosmetic classification isn't worth the risk.
- [x] Re-ran the full Phase 2 test suite after each individual version step (not batched) — 31/31 passing at every stage, plus a manual read-only smoke test against the real `ecommerce` database (order eager-loads, stock relations, item relations, user→orders, decimal sum) after the final Laravel 12 upgrade.
- [x] Introduced `FormRequest` classes for the two endpoints named in the original plan:
  - `app/Http/Requests/Order/StoreOrderRequest.php` — used by `OrdersController::store()`
  - `app/Http/Requests/User/StoreUserRequest.php` — used by `UserController::store()`
  - Both override `failedValidation()` to preserve the exact pre-existing response shape/status code (500+`message` for orders, 403+`errors` for users) rather than switching to Laravel's default 422 FormRequest response — changing that shape is a frontend-coordination concern that belongs in Phase 4, not a side effect of this refactor. Verified via the Phase 2 test suite (same 13 order-placement/role-permission tests, same assertion counts, before and after).
- [ ] Service layer extraction — **deferred**, not attempted this phase. Given how much ground this phase already covered (3 major-version jumps plus the FormRequest work), pulling business logic out of `OrdersController`/`UserController` into a service layer was judged better done as its own focused pass with its own test-verified steps, rather than layered onto an already-large upgrade. Worth picking up early in Phase 4 or as a standalone task.

**Exit criteria:** app runs on the target Laravel version with all Phase 2 tests green. ✅ Met — Laravel 12.63.0, PHP 8.2.12 (already satisfied both `composer.json`'s `^8.2` and the Phase 0 Dockerfile's `php:8.2-cli`, so no further environment changes needed), 31/31 tests passing, zero security advisories.

---

## Phase 4 — Frontend Modernization

Staged across two sessions per user decision: safe items first, then Vue 3/Pinia/Element Plus once there was a working build to migrate from. Both stages are now complete.

- [x] Prune unused template-demo views/components/router-modules under `resources/js/views` and `resources/js/components` (~82 boilerplate views + demo components: charts/excel/zip/i18n/markdown/upload/kanban demos not part of this product) and their npm packages (removed `vue-count-to`, `idle-vue`, `highcharts-vue`, `vue-image-crop-upload`, `vue-splitpane`, `vue-timeago`, `vuedraggable`, `tui-editor`)
- [x] Add image lazy-loading (`loading="lazy"`) across storefront pages — `pages/Menu.vue`, `pages/ProductSearch.vue`, `pages/partials/{RelatedProducts,LeftMenu,Cart,Compare,WishList}.vue`, `pages/{ItemDetails,CheckOut,index}.vue`
- [x] Reduce axios timeout in `resources/js/utils/request.js` from 300s to 60s; also fixed a null-guard bug in the error interceptor (`error.response &&` before accessing `.status`)
- [x] Confirm heavy libs (xlsx, jszip, Highcharts/ECharts) are already lazy-loaded per-route (they were); removed the dead `tui-editor` dependency entirely (no longer reachable after view pruning)
- [x] Laravel Mix → Vite migration (`webpack.mix.js` → `vite.config.js`, `package.json` scripts rewritten) — **Vue 2 kept** (via `@vitejs/plugin-vue2`), not part of the Vue 3 move below. Included: Dart Sass (`sass`) replacing `node-sass`; `vite-plugin-svg-icons` replacing `svg-sprite-loader`; `import.meta.glob` replacing webpack `require.context` for Vuex module auto-loading and the icons registry; `import.meta.env.VITE_*` replacing `process.env.MIX_*` (`.env`/`.env.example` updated); converted all `require()` calls to ESM `import` (webpack-only pattern, unsupported in Vite/browser ESM) across chart components, `Export2Excel.js`/`Export2Zip.js`, `ThemePicker`, clipboard directive; `/deep/` → `::v-deep` in 4 files (Dart Sass rejects the deprecated syntax); removed `~` tilde-prefixed SCSS import convention (webpack-only) across 7 files; removed a `:export {}` Sass→JS bridge (webpack-only ICSS feature with no Vite equivalent) from `element-variables.scss`, replacing it with hardcoded theme constants in `settings.js`; rewrote 2 files' JSX render functions (`layout/components/Sidebar/Item.vue`, `app/dashboard/editor/Icon.vue`) to plain `h()` calls rather than adding a JSX toolchain dependency; `resources/views/laravue.blade.php` now uses `@vite([...])` instead of `mix()`
- [x] Verify build works and manually smoke-test key pages — `npx vite build` succeeds (723 modules, valid manifest); started `php artisan serve` and drove headless Chromium (Playwright, since `chromium-cli` wasn't available) against the homepage and `/login`. Homepage: full storefront renders (branded header/nav, category sidebar, live product grid from the real DB, carousel, footer), zero console errors. Login page: branded, fully-styled form with background image, zero console errors. Screenshots confirmed CSS applied correctly (not unstyled).
- [x] Vue 2 → Vue 3 migration — stayed 100% Options API (no Composition API rewrite needed). Entry point rewritten to `createApp(App).use(router).use(pinia).use(i18n).use(ElementPlus)`. Fixed across the codebase: filters removed (Vue 3 dropped the option entirely) in 8 files, `{{ x | y }}` pipe syntax converted to plain function calls; `slot="x"`/`slot-scope` → `v-slot`/`#x` template syntax (~30 files, including several nested-inside-a-wrapper-div mistakes caught and corrected — Vue 3 requires named-slot `<template>`s to be *direct* children of the component, not just descendants); `.sync` modifier → `v-model:prop` (bulk-fixed ~20 files); `.native` event modifier removed (6 files; Vue 3 auto-forwards unrecognized listeners); `beforeDestroy`/`destroyed` → `beforeUnmount`/`unmounted` (12 files); custom directive hooks `bind`/`inserted`/`componentUpdated`/`unbind` → `beforeMount`/`mounted`/`updated`/`unmounted` (6 directive files); 2 functional components (`Sidebar/Item.vue`, `dashboard/editor/Icon.vue`) rewritten from `functional:true`+vnode-slot-metadata to plain Vue 3 function components; `$listeners` → `$attrs` (`SvgIcon`); 2 `render(h)` functions simplified (Vue 3 no longer auto-injects `h`, and returning `null` is valid); `router-link`'s removed `tag="span"` prop dropped (1 file); `v-model` directly on a prop (invalid in Vue 3 — props are read-only) fixed in 2 dialog components to `:model-value` + existing `before-close` handler
- [x] Vuex 3 → Pinia — 7 Vuex modules (`app`, `items`, `order`, `permission`, `settings`, `tagsView`, `user`) rewritten as 7 Pinia stores (`resources/js/store/{app,items,order,permission,settings,tagsView,user}.js`), mutations folded directly into actions. The old non-namespaced root `getters.js` (21 flat getters like `roles`/`token`/`cart`) was deleted; each getter now lives as a getter on its owning store. ~43 consuming files converted from `this.$store.state/getters.dispatch(...)` to `useXStore()` (Options API `computed: { xStore() { return useXStore(); } }` pattern, per Pinia's own documented Options-API guidance) or Pinia's `mapState(useXStore, [...])` helper where `mapGetters`/`mapState` were used. Fixed two pre-existing bugs while rewriting the files that contained them: `layout/components/Sidebar/index.vue`'s `mapGetters(['permission_routers'])` referenced a getter that never existed (real one is `permissionRoutes`); `logout`/`resetToken`/`changeRoles` in the old `user.js` module mutated a dead top-level state key instead of `userData`, so the flat getters never actually reflected a cleared token/changed role. **Found and fixed a genuine circular-dependency bug** this migration exposed: `store/permission.js` and `store/user.js` both statically imported `@/router` at module top level, while `@/router`'s lazily-loaded page components import the `@/store` barrel right back — Vuex/Webpack tolerated this cycle, but Vue 3/Rollup's ESM bundle hit a hard `ReferenceError: Cannot access 'useUserStore' before initialization` (temporal-dead-zone violation) at runtime once the lazy routes got bundled into the same chunk. Fixed by converting both to `await import('@/router')` inside the one action each actually needs it in, deferring evaluation past bootstrap.
- [x] Element UI → Element Plus — full-bundle registration swapped (`app.use(ElementPlus, { size, locale })`); theme SCSS rewritten from element-ui's flat `$--color-primary` variables to Element Plus's `@forward ... with (...)` Dart Sass module-map system (`element-variables.scss`); global JS-service shim added (`app.config.globalProperties.$message/$notify/$confirm/$alert/$prompt`) so ~30 existing call sites needed no changes; `vue-tables-2` (no Vue 3 support) replaced with native `el-table`/`el-table-column` in the 8 real consuming files (`app/order/index.vue`, `pages/TrackOrder.vue`, `app/stock/{item/ManageItem,item-category/ItemCategory,items-stock/ItemStocks,returns/index}.vue`, `app/users/{List,Customer}.vue` — the other 8 of the original 16 vue-tables-2 usages were in the dead reports subtree already deleted, see below); `MDBVue` removed entirely (`mdb-input`/`mdb-btn` → `el-input`/`el-button` in 4 login/checkout files), finally resolving the old audit's "two UI libraries loaded simultaneously" finding; `vue-carousel` → `el-carousel` (1 file); `vue-mj-daterangepicker` → native `el-date-picker` (2 real files, `ItemStocks.vue`'s usage turned out to be dead/commented already); `vue-loading-overlay`'s `Vue.$loading.show({})` (a Vue-2-only global-static pattern, breaking regardless of which loading library was used) replaced with Element Plus's own `ElLoading.service()` in `api/resource.js`, keeping the `.hide()` call shape every consumer already used; `element-ui`'s old CDN-fetch-and-regex runtime theme-color-picker hack (`ThemePicker/index.vue`) replaced with Element Plus's actual supported mechanism — a single CSS custom property (`--el-color-primary`)
- [x] Package updates: `vue` ^3.4, `vue-router` ^4.4, `pinia` ^2.1.7, `element-plus` ^2.7.6 + `@element-plus/icons-vue`, `vue-i18n` ^9 (legacy mode), `@vitejs/plugin-vue` ^6 (replacing `@vitejs/plugin-vue2`); removed `vuex`, `element-ui`, `vue-template-compiler`, `vue-tables-2`, `mdbvue`, `vue-carousel`, `vue-mj-daterangepicker`, `vue-loading-overlay`, `highcharts` (confirmed zero live imports), `driver.js` (confirmed zero usage anywhere); added `moment` and `@fortawesome/fontawesome-free` as explicit direct dependencies (both were previously only present transitively via now-removed packages)
- [x] Deleted ~27 more dead files discovered during this pass, extending Phase 1's "remove the dead warehouse/logistics subsystem" decision to the frontend: `app/reports/graphical/` (11 files, routed at `/reports/graphical-reports` but permanently inert — gated on a `warehouses` array that's always empty, and 7 of its 10 components weren't even imported), `app/reports/downloadable/` (11 files: inbounds/outbounds/instant-balances/fleets/a duplicate users-list, routed at `/reports/downloads`, calling backend endpoints Phase 1 already removed), `app/reports/downloads.vue`, `app/reports/BinCard.vue` (routed but zero API calls, non-functional stub), `app/reports/index.vue` (thin wrapper around the deleted graphical/ dir), `router/modules/reports.js` (turned out to be completely orphaned — never actually imported into `asyncRoutes` at all, so `/reports/bin-card` etc. were never reachable even before this cleanup), `layout/IdleModal.vue` (dead — reads a `idleVue` store module that was never wired up), `app/login/CustomerLogin.vue` (unreferenced duplicate of `login/index.vue`). This also shrank the real `vue-tables-2`/date-range-picker migration surface from 16/11 files down to 8/2.
- [x] Drop one of the two UI libraries (Element UI/Plus or MDBVue) — done as part of the MDBVue removal above
- [x] Re-test storefront and admin — `npx vite build` succeeds (1830 modules); Playwright smoke test against `php artisan serve`: homepage renders full product grid with live DB data, pagination, category sidebar, out-of-stock badges (zero console/page errors); `/login` renders the full branded form; `/track/order` loads cleanly; visiting an admin-only route (`/dashboard`) while unauthenticated correctly redirects to `/login` via the Pinia-backed router guard with zero errors, confirming the store/router/permission chain works end-to-end. Admin-authenticated pages (dashboard, user list, stock tables, audit trail) were verified via successful build output + code review but not via an actual logged-in Playwright session, since that requires real admin credentials in what's confirmed to be a production-mirror database — not something to guess/create test accounts against without asking first.

**Known follow-up (cosmetic, not functional):** ~31 files still reference Element UI's old icon-font CSS classes (`<i class="el-icon-search">`, `icon="el-icon-edit"` props) — Element Plus dropped the bundled icon font in favor of the separate `@element-plus/icons-vue` component package, so these render as empty/invisible glyphs now (the buttons/menus themselves are still fully functional — just missing the small icon inside). Converting each occurrence to `<el-icon><ComponentName /></el-icon>` (or passing an imported icon component instead of a string to `icon` props) is mechanical but touches ~31 files; deferred as a fast-follow rather than done in this already-large session.

**Exit criteria:** app builds and runs on Vite with Vue 3 + Pinia + Element Plus, no dual UI library, storefront and public pages manually verified working end-to-end with zero console errors.

---

## Phase 5 — Payment Integration

Business decision required before starting: **Paystack**, kept alongside the manual bank-transfer/receipt-upload flow as a fallback (confirmed with the user).

- [x] Choose a payment gateway — Paystack
- [x] Add gateway credentials to `.env.example` / config — `PAYSTACK_PUBLIC_KEY`/`PAYSTACK_SECRET_KEY`/`PAYSTACK_PAYMENT_URL` in `.env.example`, `.env`, and `config/services.php`. **Real keys still need to be supplied** — currently blank, so live Paystack calls correctly fail with Paystack's own "invalid key" error until a merchant account's test/live keys are added.
- [x] Integrate gateway checkout flow alongside the manual bank-transfer/receipt-upload flow — `CheckOut.vue` now has a payment-method toggle ("Pay with Card (Paystack)" default / "Bank Transfer"); the bank-transfer path is byte-for-byte the same UI/flow as before, untouched.
- [x] Server-side payment verification instead of trusting client-supplied `amount` — new `PaystackService` (`app/Services/PaystackService.php`) calls Paystack's real `/transaction/verify` endpoint; `OrdersController::confirmPaystackPayment()` only marks an order `paid` if Paystack confirms `status === 'success'` AND the verified amount matches the order's own `total` (guards against a reused/mismatched reference). Both the `paystackCallback` (browser redirect) and `paystackWebhook` (server-to-server, HMAC-SHA512-signature-verified) paths call this, idempotently.
- [x] Update `orders.payment_method`/`payment_status` handling — new `payment_reference` column (migration `2026_07_22_010000_...`); `finalizeOrder()` helper (extracted from `OrdersController::store()`) now parameterizes `payment_method`/`payment_status`/`receipt_image` so both checkout paths share the same order/stock-reservation logic.
- [x] Add tests for the new payment flow — `tests/Feature/Order/PaystackPaymentTest.php` (6 tests, `Http::fake()`-mocked Paystack responses): initialize creates a pending order + returns the authorization URL, initialize fails cleanly on a gateway error, callback marks an order paid only on verified success, callback does NOT mark paid on an amount mismatch, webhook rejects an invalid signature, webhook marks paid with a valid signature. Full suite: 37/37 passing (31 pre-existing + 6 new).

**Verified live** (real browser, real backend, real outbound call to Paystack's actual API — no real secret key configured yet, so Paystack correctly rejected it with its own "invalid key" message, which propagated cleanly to the UI as a readable alert rather than a crash): order creation, total calculation, stock reservation, toggle UI, form validation, and the Bank Transfer regression path all confirmed working end-to-end. All test orders/users/stock-reservations created during verification were cleaned up afterward (production-mirror DB counts unchanged: 27,439 orders, 1,742 users).

**Not yet done:** a genuine live round-trip through Paystack's hosted checkout page (requires real Paystack test/live API keys, which haven't been provided) and the `TrackOrder.vue` success/failure redirect landing haven't been exercised with an actual completed payment — only code-reviewed and unit-tested via mocks.

**Exit criteria:** at least one automated payment method is live and independently verified server-side (✅ logic verified via tests + live-but-keyless UI test); manual bank-transfer remains as an explicit fallback (✅). Full live confirmation pending real Paystack credentials.

---

## Checkout Flow Hardening (post-Phase-5 audit)

Audit of cart -> checkout -> confirmation. Every item below has a regression test in `tests/Feature/Order/CheckoutHardeningTest.php` (69/69 tests passing) and was re-verified end to end in a real browser.

**Security**
- [x] Removed the unauthenticated `POST /api/stabilize-order-total` (a single call would have doubled order totals across ~67k lines), `GET /api/reverse-bulk-cancelled-order` and `POST /api/order/generate-order-number` (anonymous account creation + email). These were one-off data fixes: recoverable from git if ever needed, but they must not be routes.
- [x] Receipt upload validated (a real jpg/png judged by *content*, max 5MB); extension taken from the content rather than the client's filename; random 40-char name; receipts folder made script-inert with an `.htaccess`. Receipts still live inside the web root — moving them to a private disk behind an authenticated download is the recommended next step.
- [x] Cart lines validated (integer quantity 1-10000, one line per stock row). Item, name and price are derived server-side from the stock row; the client's `id` / `name` / `rate` / `amount` / `total` are ignored.
- [x] Disabled / deleted / unknown stock is reported as unavailable (`check_cart`) instead of being orderable or crashing with a 500. Disabled products are hidden from guests on `item-details` and `item-show`.
- [x] Order placement is atomic: stock rows locked `FOR UPDATE`, check + create + reserve in one transaction, double-submit serialised per `order_uniq_id` with a cache lock (needs a cache driver with lock support: file / redis / database).
- [x] IDOR: `order/general/show/{order}` limited to the order's owner or holders of `view order`. `reverse-cancellation` now needs `approve order|cancel order`, only works on Cancelled orders, and can't be repeated.
- [x] Guest checkout no longer overwrites an existing account's saved address.
- [x] `fetch-necessary-params` is public, so it now returns only storefront data; the catalogue (including disabled items), roles, warehouses etc. go to signed-in users only.
- [x] Named rate limiters (`order-submit` 10/min, `order-tracking` 15/min per IP) in `AppServiceProvider`. Don't use unnamed `throttle:N,M` for these: it shares one counter with every other throttled route, so ordinary browsing eats the allowance.

**Bugs**
- [x] After checkout: a confirmation screen (order number, total, next steps, Track button) replaces the dialog that an immediate redirect destroyed; tracking accepts a pre-filled order number + email.
- [x] The pickup warning displayed the word "true" (the text setting was cast to bool); `can_make_order` had the same truthy-string bug. New `Controller::settingEnabled()`.
- [x] Checkout "Edit Cart" pointed at a route that doesn't exist; stock-shortage handling now removes/adjusts lines properly (it used to corrupt cart lines and leave 0-quantity items); the phone number is now actually sent; the `states` timer race is gone; subtotals are no longer truncated by `parseInt`; the cart total is live; tracking error handling fixed; a failed Paystack start cancels the order and releases stock immediately.

- [x] Order/track-order page amounts: the grand total concatenated the API's string item totals (a 4-item order showed ₦12,500.003 instead of ₦29,500.00 and "in words" read "…octillion…"), decimals shifted the words by 1000x, kobo was never spoken, and amounts under ₦100 got a leading "and". Fixed in `Details.vue` (numeric computed total) with a new pure `resources/js/utils/amountInWords.js`, covered by `npm run test:js` (Node's built-in runner, no dependencies; includes ~7,000 round-trip checks).

**Known / not changed**
- `.env` has `APP_DEBUG=true` — must be `false` in production (500 responses otherwise expose stack traces).
- Guest checkout still emails the new customer's generated password in plaintext (existing design).
- `orders.order_uniq_id` has no DB unique index (the cache lock covers double-submits; an index is the belt-and-braces guard, but needs a duplicate check on the 27k existing rows first).
- Admin `change-status` doesn't validate the status value or guard against repeated transitions; the `assign-order-to-location` route points at a method that doesn't exist (`assignOrderToWarehouse` does).
- `.eslintrc.js` has an invalid `vue/max-attributes-per-line` option, so linting can't run.

- [x] **Order confirmation email** (20 Sep): the customer is emailed their order number, items, totals, delivery details and a track-order link. Bank-transfer orders are emailed the moment they are placed ("awaiting payment confirmation"); card (Paystack) orders once payment is confirmed ("payment received") — exactly once even when the callback and webhook both fire. Sent after the response (no checkout delay, no queue worker needed), never throws (a mail failure is logged and the order stands), skipped for a double-submitted order or an invalid email. Old `order_details` template rewritten (it read `name` / `rate`, which order lines do not have). 12 tests in `tests/Feature/Order/OrderEmailTest.php`
  - Production needs: working `MAIL_*` settings, `MAIL_FROM_ADDRESS` on a domain with SPF / DKIM set up (else it lands in spam), `APP_NAME` and `APP_URL` set correctly in `.env`
  - Noted, not changed: an order's `total` equals its items only — `delivery_cost` is stored but never added to what the customer pays (no real order has a delivery cost today). The email shows a delivery line only when delivery is part of the total

## Phase 6 — Accounting (Income & Expenses), Restock List, Reports

Goal: let the owner know, at any time, whether the business is making a profit — with books kept the standard way — plus an easy restock list and a reports page for everything reportable.

**Accounting design (decided up front)**
- **Double-entry general ledger**, not a one-column "expenses" list: every transaction is a journal entry whose debits equal its credits (enforced in one service, inside a DB transaction). Money is `DECIMAL(14,2)`, arithmetic is done in integer kobo, currency is NGN only.
- **Chart of accounts** seeded for a retail business (assets 1xxx, liabilities 2xxx, equity 3xxx, income 4xxx, cost of sales 5xxx, operating expenses 6xxx/7xxx). "Categories" in the forms *are* accounts, so nothing is ever unreported.
- **Posted entries are immutable.** A correction voids (posts a reversing entry, keeps the original visible as VOID) and, for edits, re-posts the corrected version, linked both ways. Nothing is deleted. A **books-closed-through date** locks past periods.
- **Sales revenue** is posted automatically as a *daily sales summary* (the same way a shop posts a till Z-report): Dr Bank / Cr Sales Revenue for paid, non-cancelled orders, dated by order date, from the **books start date** onward. Re-syncing is idempotent and posts an *adjusting* entry if orders changed since (dated today if the day is locked).
- **Cost of goods sold** is recorded on a purchases basis (stock bought is expensed to 5000 COGS) because products carry no unit cost today. Gross profit = sales − COGS. (Perpetual inventory with unit costs is a listed follow-up.)
- **Statements:** Profit & Loss (with prior-period comparison), Balance Sheet, Trial Balance, General Ledger.

**Backend — accounting**
- [x] Migrations: `accounts`, `journal_entries`, `journal_lines` (FKs, indexes), seeded chart of accounts (40 accounts), accounting settings (`books_start_date`, `books_closed_through`, `sales_deposit_account`) — `2026_09_20_090000_create_accounting_tables.php`
- [x] Permissions `view accounting`, `manage accounting`, `view reports` (idempotent migration `2026_09_20_090100_...`, admin role holds them)
- [x] `Ledger` service (`app/Services/Accounting/Ledger.php`): balanced posting compared in whole kobo, void by reversal, edit = void + repost linked both ways, period lock, sequential references (`EXP-000123`); `LedgerException` → clean 422 via the exception handler
- [x] `SalesPoster` service + `accounting:post-sales` command (idempotent daily summaries, adjusting entries, locked-day → dated today), scheduled hourly; `Order::revenue()` scope is now the single definition of revenue
- [x] `AccountingReports` service: profit & loss (+ prior-period comparison), balance sheet (with profit to date, `balanced` check), trial balance, general ledger (opening/running/closing), overview (KPIs, 12-month trend, expense breakdown, cash position)
- [x] API (`/api/accounting/*`): accounts, transactions (income / expense / transfer / journal; create, list + filters + totals, show, edit, void), settings, sync-sales, overview, statements
- [x] Tests (46, `tests/Feature/Accounting`): ledger invariants, transactions API + validation + permissions, statement maths (hand-checked scenario), sales-posting idempotency / adjustment / closed-day. Found & fixed a real bug on the way (12-month trend lost a month on a 31st via Carbon month overflow)

**Backend — restock & reports**
- [x] Restock API: products out of stock (or low), variant view, sales velocity, suggested quantity, filters
- [x] Report registry — `app/Services/Reports`: `Report` base class + `Filter`/`Column` spec builders, one generic `ReportCenterController` (`GET reports/catalog`, `reports/run/{key}`, `reports/export/{key}`). 14 reports: sales summary (day/week/month), sales by product / category / customer, orders, stock levels, out of stock, new customers, income & expenses, expenses by category, P&L, balance sheet, trial balance, general ledger. Filters are declared once and validated server-side (bad value = 422); the financial statements need `view accounting`, the rest `view reports`
- [x] Streamed CSV for every report (UTF-8 BOM, formula-injection guard, totals row included, no size cap) + a JSON export capped at 25,000 rows that feeds the client-side Excel download
- [x] Tests (31: `tests/Feature/Reports`, `tests/Feature/Stock/RestockListTest`): a hand-calculated business runs through all 14 reports, every filter, paging stability, CSV/JSON export, permissions. Sales report ties to `Order::revenue()` and to the ledger; income & expenses ties to the P&L. Full suite: 187 tests pass

**Frontend**
- [x] Icons + shared pieces — 13 new icons in the registry; `PeriodPicker` (standard periods: today … last year), `ReportTable` (formatting by column type, statement-style rows, status tags), `ReportRunner` (filters drawn from each report's own spec, summary tiles, paging, CSV / Excel / print); pure formatting + Excel-row logic in `utils/reportFormat.js` with 10 unit tests; the request helper now shows the server's real message when a file download fails
- [x] Accounting → Overview — profit / loss verdict with previous-period comparison, KPI cards (income, costs, gross profit, cash and bank, owed to you, you owe), 12-month income vs costs chart (hand-drawn SVG, no new dependency), where-the-money-went bars, cash accounts; warns when income exists but no costs are recorded (so a revenue figure is never mistaken for profit)
- [x] Accounting → Income and Expenses — filterable list (period, type, account, search, amount range, status, source) with totals for exactly what is filtered; one dialog for expense / income / transfer / journal (a journal shows a live balanced check); correct (void + repost), void with a required reason, detail drawer with the double-entry lines
- [x] Accounting → Chart of Accounts (add / edit / deactivate / delete, system and in-use accounts protected), Financial Statements (P and L, balance sheet, trial balance, general ledger as tabs on the shared runner), Settings (books start date, period lock, sales deposit account, manual sync)
- [x] Manage Products → Out of Stock — counts (out / oversold / low), filters (out / low / both, category, search, product vs size-and-colour level, low threshold, days of cover, sales window, include disabled), suggested order quantity, per-row Restock (opens the existing add-stock screen in place), CSV download
- [x] Reports — catalogue grouped Sales / Inventory / Customers / Finance (a dropdown on phones), the chosen report kept in the URL, filters generated per report, summary tiles, table with totals row, CSV / Excel / Print (print hides the controls and adds a heading with the period)
- [x] Sidebar, routes and permissions wired — Accounting group (`view accounting`; Settings needs `manage accounting`), Reports (`view reports` or `view accounting`), Out of Stock under Manage Products (`create menu`); write buttons hidden without `manage accounting` (the API enforces it regardless)

**Verification**
- [x] Full PHPUnit + JS suites and production build pass — 187 PHP tests (1,405 assertions), 22 JS tests (10 new), `npm run build` clean
- [x] Migrations applied to the dev database; sales synced; numbers reconciled — ledger revenue ₦591,537,288.00 equals the direct SQL sum of the 33,504 paid, non-cancelled orders since 1 Jan 2026 (261 daily entries, ~4 s). Same definition as the dashboard (`Order::revenue()`)
- [x] Real-browser walk-through on the real data (headless Chromium, 55 checks, no page / console / API errors): recorded an expense through the form → list, totals, overview profit and P and L all moved; corrected it and voided it (totals back to zero, original kept as void); balance sheet and trial balance balance; all 14 reports run; CSV + Excel downloaded and inspected; print layout; restock list + Restock opens the add-stock screen + CSV; no horizontal scroll on a phone. All test transactions, notifications and the temporary admin were removed afterwards
- [x] Production runbook (in order; back up first — none of this touches existing tables, it only adds):
  1. `php artisan migrate --force` — creates `accounts`, `journal_entries`, `journal_lines`, seeds the 40-account chart and the settings, adds the permissions `view accounting`, `manage accounting`, `view reports` (admins get them automatically)
  2. `php artisan accounting:post-sales` — books sales from the start date into the ledger (about 4 s per 30,000 orders); safe to re-run, it only posts differences
  3. Accounting → Settings: set **Books start on** (defaults to 1 January of the year the migration ran), confirm the sales deposit account, then record **opening balances** (bank / cash, stock, loans, owner capital) as a journal dated the day before the start date
  4. Give bookkeepers / managers the new permissions on the Roles page (`view accounting` to read, `manage accounting` to record, `view reports` for sales, stock and customer reports)
  5. Make sure the scheduler runs (`* * * * * php artisan schedule:run`): sales are synced hourly, and whenever a finance report is opened
  6. `npm run build`; after go-live record real expenses and stock purchases — until then "profit" equals revenue (the Overview says so)

**Follow-ups (deliberately not built)**
- [ ] Perpetual inventory with a unit cost per product (needs cost data that does not exist yet) — would let cost of sales follow what was *sold* rather than what was *bought*
- [ ] Receipt / invoice attachments on transactions; recurring expenses; VAT
- [ ] Year-end close (roll the year's profit into retained earnings) — the balance sheet shows the un-rolled profit as "Profit to date", so it still balances
- [ ] Timezone: the app runs in UTC, the business is in Nigeria (UTC+1), so daily sales are bucketed by UTC day; decide whether to set `APP_TIMEZONE=Africa/Lagos` (affects every timestamp, so it needs its own change)
- [ ] The Out of Stock report takes ~2.5 s on the live-size data (the all-time "last sold" lookup); fine for now, index or cache it if it becomes annoying

## Phase 7 — Product Cost & Real Profit (FIFO, per size)

Goal: know what every product cost, so profit is real instead of "revenue minus whatever expenses were typed in".

**Decisions (confirmed by the owner)**: FIFO costing · cost held **per size** (colours of a size share a cost) · freight and customs are **capitalised** into the product's landed cost · cut over at a month start after a **physical stock count** · full perpetual-inventory approach (not the single-cost shortcut).

**What the code does today (drives the design)**
- `item_stocks` is ONE running-total row per product + colour + size. Stock-up (`ItemsController::stock`) only adds to `quantity_stocked`; no cost, supplier, invoice or date is kept, so there are no batches to run FIFO over.
- Stock moves in exactly five places: reserved when an order is placed; reserved → sold when the order leaves Pending (`OrdersController::sellOutFromStock`); released on cancel (only possible while still open); re-reserved by "reverse cancellation"; two scheduled commands release stale reservations. A delivered order cannot be cancelled, so cost consumed at sell-out never has to be un-consumed.
- `item_size_prices` already prices by size, so cost by size mirrors it. There is no returns, damage or stock-count adjustment flow yet.
- The books currently book stock purchases straight to expense (account 5000, cash basis). After cut-over that must stop, or cost is counted twice.

**Design**
- **Cost layers**: each receipt of goods creates a layer per product + size (date, quantity received, quantity remaining, landed unit cost). Selling consumes the oldest layer first and records exactly which layers (and how many units of each) went into each order line, so the cost on a sale is auditable and never changes afterwards.
- **Landed cost**: extra costs on a delivery (freight, customs, handling) are spread across its lines in proportion to value, in whole kobo, with the rounding remainder on the last line so the total is exact.
- **Timing**: cost is taken when the goods leave (the existing sell-out step). The daily sales posting then books cost of sales the same way it already books revenue — compare per day, post only the difference — so late dispatches correct themselves. Known, accepted effect: an order paid but not yet dispatched shows revenue a few days before its cost.
- **Shortfall**: selling more than the layers hold (oversold) uses the latest known cost and is flagged for correction when the delivery is recorded; it never blocks a sale.
- **Books**: receipt → Dr Inventory (1100), Cr Bank or Payables; sale → Dr Cost of Goods Sold (5000), Cr Inventory; damage / count loss → Dr Inventory shrinkage, Cr Inventory. Control check: the Inventory account must equal the sum of remaining layers × cost, and layers remaining must equal (stocked − sold) per product + size.

**Owner's answers (19 Sep)**: cut-over **1 October 2026** · opening costs come from **supplier invoices** · no stock purchases have been booked as expenses yet · timing rule accepted (cost when goods are dispatched, revenue when paid).

**7.0 Cut-over preparation (data — needs the owner; in-app wizard: Accounting → Product Costing)**
- [ ] 30 Sept evening: freeze stock-ups, count the shelves, correct differences (Products page or Stock Adjustments — both only move quantities until costing is live)
- [ ] Fill in the cost sheet from supplier invoices (Accounting → Product Costing → download; one line per product size, ~3,350 lines on the current shelf; include freight/customs in the unit cost for stock already on the shelf)
- [ ] 1 October: upload the sheet, check it, set the start date and switch on (do it when the shop is quiet)

**7.1 Schema and costing service** — done
- [x] Migration `2026_09_21_100000`: `stock_receipts` (+ lines), `cost_layers`, `cost_consumptions`, `stock_adjustments`, `order_items.cost_total / costed_at`, settings `costing_enabled` / `costing_start_date`, permission `view cost`, accounts 5000 / 1100 / 6960 / 3900 / 2000 become system accounts (5000 renamed "Cost of Goods Sold")
- [x] `AppServicesCostingCosting` — receive (landed-cost allocation in whole kobo), FIFO consume, provisional shortfall + true-up, adjustments, void delivery; `CostingSetup` — cost-sheet check, go-live, reconciliation; `CogsPoster`; `CostLookup`

**7.2 Receive stock** — done
- [x] Receive Stock screen (Manage Products): supplier, invoice, date, paid-from or owed, freight/customs, products with colour/size lines and ONE cost per size, live landed-cost preview; posts Dr Inventory / Cr Bank or Payables; quantities, layers and journal in one transaction
- [x] Deliveries list + detail (landed cost per line) + void (only while untouched); Stock Adjustments screen (loss at FIFO cost → Stock Loss & Damages; found stock at a stated cost)
- [x] The old cost-less stock-up (Products page, Out of Stock → Restock) hands over to Receive Stock once costing is live; the old endpoint refuses with a clear message

**7.3 Costing at sale** — done
- [x] Layers consumed inside the existing sell-out transaction; cost frozen on each order line and never sent to browsers (`OrderItem::$hidden`); cancel / reverse-cancel paths untouched (a delivered order cannot be cancelled)
- [x] Opening layers from the cost sheet + opening journal (Dr Inventory / Cr Opening Balance Equity, dated the day before)

**7.4 Accounting** — done
- [x] Daily cost-of-sales posting (`CogsPoster`, idempotent, difference-based, runs with the sales posting; dated the order day but never before go-live; closed periods corrected dated today)
- [x] System entries (sales, cost of sales, deliveries, adjustments, opening stock) cannot be edited or voided by hand; typing a stock purchase in as a "Cost of Goods Sold" expense is refused once costing is live (and hidden in the form)
- [x] Books-match-the-shelf check: Accounting → Product Costing and the Overview strip (units per size and value; provisional units flagged)

**7.5 Reports** — done
- [x] Gross margin (by product / size / category, with "sales not costed yet"), Inventory valuation (at cost, oldest delivery, days unsold filter for dead stock, agrees-with-books check); restock list and Out of Stock report show the last unit cost and the cost of the suggested order

**7.6 Controls** — done
- [x] `view cost` hides every cost, margin and valuation (reports, restock columns and CSV, adjustment costs, delivery list); recording a delivery needs only `create menu`; go-live / voiding a delivery needs `manage accounting`; audit notifications on deliveries, voids, adjustments and go-live
- [ ] Warning when a selling price is at or below cost plus delivery (not built — see follow-ups)

**7.7 Verification** — done
- [x] 47 new PHP tests (`tests/Feature/Costing`): FIFO across layers, landed-cost rounding, kobo-exact splits, oversold shortfall + true-up flowing into cost of sales, adjustments, void rules, cut-over guardrails, books = shelf after a mixed sequence, permissions, privacy of costs; 7 new JS tests (cost sheet + landed preview). Suite: 234 PHP + 29 JS tests pass, production build clean
- [x] Rehearsal on the live-size dev database (then restored exactly, verified against a snapshot): 3,354 product sizes costed through the real download → upload → check → switch-on flow, an incomplete sheet refused with the gaps listed, receive → detail → void, adjustment, a real dispatch costed and booked (₦9,600 sales, ₦3,900 cost, books and shelf still in step), gross margin / valuation / restock cost columns, no page, console or API errors

**1 October runbook** (back up the database first)
1. Deploy; `php artisan migrate --force` (additive: new tables, account renames, `view cost` permission); `npm run build`
2. Roles page: give `view cost` to whoever should see costs, `create menu` to whoever receives stock (admins have both)
3. 30 Sept: freeze stock-ups, count, correct differences
4. Accounting → Product Costing: download the sheet, fill the costs, upload, fix anything flagged, set the start date to 1 October, switch on
5. From then on every delivery goes through Receive Stock; check Product Costing / the Overview strip for "In step" daily for the first weeks
6. Keep booking non-stock costs (rent, transport, salaries…) as expenses as before

**Follow-ups (not built)**: selling-price-below-cost warning on the product form · bulk cost import for ongoing deliveries (a supplier-invoice spreadsheet) · costs by colour (currently per size) · perpetual inventory history "as at" a past date

**Risks to manage**: the order and stock-movement code is the most delicate part of the system (every change is covered by tests and a checkout regression run) · going live must happen at a period boundary, right after the count · someone must own entering costs on every delivery, or layers go stale · imports must be checked before the first month is reported.


## Progress Tracking

| Phase | Status |
|---|---|
| 0 — Security & Stability | Done — only the production `.env` check remains (needs prod access) |
| 1 — Schema Reconciliation | Done — schema hardened (FKs, decimal money, indexes, uniqueness), dead warehouse/invoice subsystem and Customer concept removed, factories/seeder added. `sku` column deferred (no current need). |
| 2 — Test Safety Net | Done — isolated test DB, 31 passing tests covering auth/orders/permissions/stock, CI wired up, broken deploy stage disabled |
| 3 — Backend Framework Upgrade | Done — Laravel 9 → 10 → 11 → 12.63.0 (scope expanded past the original 11/12 target once security advisories on older versions came to light; user confirmed), FormRequest classes added for order/user creation. Service layer extraction deferred to Phase 4. |
| 4 — Frontend Modernization | Done — Vite + Vue 3 + Pinia + Element Plus, dual UI library removed, storefront/admin re-verified. Only a cosmetic follow-up remains: ~31 files still reference Element UI's old icon-font CSS classes instead of `@element-plus/icons-vue` components. |
| 5 — Payment Integration | Done (pending real credentials) — Paystack integrated alongside manual bank-transfer, server-side verification (webhook + callback), 6 new tests (37/37 total passing), checkout UI toggle. Live round-trip through Paystack's hosted page not yet exercised — needs real API keys. |

| 6 — Accounting, Restock, Reports | Done — double-entry books with automatic daily sales postings, Income & Expenses, financial statements, Out of Stock list, 14-report centre with CSV / Excel / print. 187 PHP + 22 JS tests, reconciled to the live-size orders, browser-verified. Follow-ups listed above |
| 7 — Product Cost and Real Profit | Built and verified — FIFO per size, landed cost, Receive Stock, cost of sales in the books, gross margin and valuation reports. Waiting only on the 1 Oct cut-over (stock count + cost sheet from supplier invoices); runbook above |

Update the phase status row and check off individual items as work completes.
