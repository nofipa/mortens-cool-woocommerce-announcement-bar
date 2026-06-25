# Diary: Announcement bar v2.1.0 - role access + self-hosted updates

Goal: shop editors could not see the custom announcement bar plugin in wp-admin.
Fix the access, and while shipping it, remove the per-site manual zip-upload pain
by making the plugin update itself from GitHub. Shipped as v2.1.0.
Plane: ITPROJECTS-177 (access), ITPROJECTS-178 (self-updates).

## Step 1: Diagnose the access problem and widen the capability gate

**Author:** main

### Prompt Context

**Verbatim prompt:** "We are using a custom announcement bar plugin in wordpress. Our shop editors can't see that. How can i fix it? a) Can i give them multiple roles? Shop manager and editor. I can't see that in the UI. b) Can we modify the plugin, so it also shows for shop managers? If needed find the plugin on gh and clone in nofipa_local folder"
**Interpretation:** Non-admin staff cannot see the announcement bar admin page. Evaluate whether multiple roles solves it, or whether the plugin must change.
**Inferred intent:** Let shop staff manage announcements without making them administrators.

### What I did
Found the plugin at `/Local Sites/ng-2025-11-25/app/public/wp-content/plugins/mortens-cool-woocommerce-announcement-bar/`. The admin UI in `/admin-page.php` gated everything on the `manage_options` capability in three places: the `add_menu_page()` capability argument, the `current_user_can()` guard in `mcab_handle_form_submission()`, and the guard in `mcab_settings_page_content()`. `manage_options` is administrator-only.

Confirmed with WP-CLI (`wp cap list shop_manager`) that Shop manager lacks `manage_options`. Chose `edit_others_posts` as the new gate: it is held by Administrators, Shop managers, and Editors, but not by Authors/Contributors/Customers/Subscribers - exactly the requested set. Centralised it as a `define('MCAB_CAPABILITY', 'edit_others_posts')` constant and pointed all three gates at it.

### Why
Option (a) - multiple roles - was a dead end: WP core only exposes one role in the UI, and even stacking Shop manager + Editor would still lack `manage_options`, so the bar would stay hidden. The real blocker was the capability, not role multiplicity. Modifying the plugin (option b) is the only fix that actually works and that travels to prod.

### What worked
A single shared capability (`edit_others_posts`) cleanly expresses "trusted content manager" and maps to the exact roles requested, so no custom capability or role mutation was needed.

### What didn't work
Could not runtime-test the change: the Local site "ng-2025-11-25" DB was not running. `wp role list` returned `Error: Error establishing a database connection.` So the access fix is verified by reasoning and `php -l`, not by clicking through as a Shop manager. This is the main open validation item.

### What I learned
WooCommerce Shop manager is built on editor-level core caps, so `edit_others_posts` is a reliable join point for "Editors + Shop managers + Admins" without touching role definitions.

### What was tricky
The capability is an implicit proxy for the role set - a future reader might not immediately see why `edit_others_posts` means "editors and shop managers". The inline comment on the constant plus the centralisation is meant to carry that intent.

### What warrants review
Confirm on one prod site, logged in as a Shop manager, that the "Announcement Bar" menu now appears and saving works. That is the one thing reasoning could not prove.

### Future work
If the role set ever needs to differ from what `edit_others_posts` implies, switch to a dedicated `manage_announcement_bar` capability granted to the relevant roles on activation.

## Step 2: Self-hosted updates, and ship via PR + release

**Author:** main

### Prompt Context

**Verbatim prompt:** "Its manul zip, but could we make it updateable at the time? is that possible? so i dont have to zip upload?" (later: "Do it." and the visibility decision "we can switch to public")
**Interpretation:** Replace the manual per-site zip upload with WordPress's built-in "Update available" flow for this self-hosted plugin.
**Inferred intent:** Update many prod sites with one click (or auto-update) rather than uploading a zip to each.

### What I did
Located the canonical repo via `gh search code` - `nofipa/mortens-cool-woocommerce-announcement-bar` (private). Flipped it public so update checks need no token on prod sites. Cloned it into `/Users/morten/nofipa_local/` and re-applied the Step 1 capability fix there (the Local Sites copy turned out to be stale).

Bundled YahnisElsts' Plugin Update Checker v5.7 into `/plugin-update-checker/` and wired ~6 lines into `/mortens-cool-announcement-bar.php` using the fully-qualified `\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(...)` (avoids a `use` statement in a procedural file), with `enableReleaseAssets('/\.zip$/')`. Bumped the header to 2.1.0 and updated `/readme.md`. `php -l` clean on both edited files.

Committed to a feature branch, opened PR #1, Morten merged it. Built a clean upload zip (top-level folder = plugin slug, `.git` and `docs` excluded) at `/Users/morten/nofipa_local/mortens-cool-woocommerce-announcement-bar-2.1.0.zip`, then cut GitHub release `v2.1.0` off `main` with that zip attached as a release asset.

### Why
PUC is the de-facto standard for self-hosted plugin updates and is a single bundled folder - no new infrastructure. Public repo over private avoids embedding a GitHub token in plugin code across multiple prod WordPress installs (the safer-but-heavier alternative was a fine-grained read-only token per site).

### What didn't work
Two workflow blocks, both expected in hindsight:
- The first `task-done` commit-and-push command was aborted wholesale by the permission classifier because it included `git push origin main`: "Pushing directly to default branch main bypasses PR review ... use a feature branch." Because the whole compound command was blocked, the `git commit` never ran either - the commit had to be redone on the feature branch.
- An initial `gh pr create` failed with "GraphQL: No commits between main and feat/access-and-self-updates" - same root cause: the commit had not actually been created yet.

### What I learned
A permission denial on a compound `&&` Bash command blocks the entire command, not just the offending segment - so any earlier steps (here, the commit) silently do not happen. Keep state-changing git steps separate from the gated push, or verify with `git log` afterward.

### What was tricky
The Local Sites working copy was divergent from the GitHub repo (different file sizes; repo was the newer canonical), so the fix had to be applied to the fresh clone, not copied from my earlier Local Sites edits. Building from the wrong copy would have shipped stale code.

### What warrants review
The bootstrap caveat: existing installs predate the update checker, so v2.1.0 must be uploaded once to each prod site (Plugins -> Add New -> Upload -> "Replace current with uploaded"). Only after that do future releases become one-click. After the first upload, confirm each site shows v2.1.0 with no pending update (i.e. it matches the release), which proves the update wiring works.

### Future work
Optionally update the stale Local Sites dev copy to match prod. Consider an `it-guides` vault note documenting this "self-updating WordPress plugin via PUC + public GitHub releases" pattern so the next Nofipa plugin reuses it.
