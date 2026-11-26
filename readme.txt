=== HexaSync – ERP, Accounting & POS Integration for WooCommerce ===
Contributors: beehexa
Tags: woocommerce, erp, quickbooks, inventory sync, order sync
Requires at least: 6.2
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WooCommerce products, inventory and orders with QuickBooks, Xero, NetSuite, Business Central and more. Monitor every sync log in wp-admin.

== Description ==

**HexaSync** is an integration platform by [Beehexa](https://www.beehexa.com/about/) that connects WooCommerce to ERP, accounting, POS, CRM and marketplace software. Products, inventory, orders, customers and shipments stay in sync automatically, without manual data entry or custom code.

This plugin is the WordPress side of that connection. It gives store owners a place inside wp-admin to set up the WooCommerce connection and to see exactly what HexaSync synchronized, what failed, and why.

= What the plugin adds to your WordPress site =

* **Connection guide** – a step-by-step page that walks you through creating WooCommerce REST API keys and connecting your store to a HexaSync profile.
* **Sync log monitor** – every sync transaction is listed under **HexaSync → Logs** with profile, task, status, message, error detail and retry count.
* **Filters** – narrow the log list by profile name, task name or reference info, such as an order number or SKU.
* **Email notifications** – get an email when new sync logs arrive, sent to the recipients you choose.
* **Log retention** – optionally delete logs older than a set number of days, so the log table stays small.
* **One-click drill down** – each log links to the matching job in your HexaSync workspace for the full sync history.
* **REST endpoint for HexaSync** – a secured endpoint that lets your HexaSync profiles write logs to your site.

= WooCommerce QuickBooks and Xero integration =

Keep your books in step with your store. HexaSync sends WooCommerce orders, customers and products to [QuickBooks](https://www.beehexa.com/hexasync/profile/woocommerce/quickbooks/) or [Xero](https://www.beehexa.com/hexasync/profile/woocommerce/xero/) so your accounting team stops re-keying sales by hand.

= WooCommerce ERP integration: Business Central, NetSuite, SAP Business One, Odoo =

Use your ERP as the single source of truth for products, prices and stock, and push WooCommerce orders back for fulfillment. Prebuilt profiles are available for [Microsoft Dynamics 365 Business Central](https://www.beehexa.com/hexasync/profile/microsoft-dynamics-365-business-central/woocommerce/), [NetSuite](https://www.beehexa.com/hexasync/profile/netsuite/woocommerce/), [SAP Business One](https://www.beehexa.com/hexasync/profile/sap-business-one/woocommerce/) and [Odoo](https://www.beehexa.com/hexasync/profile/odoo/woocommerce/).

= WooCommerce Square POS integration =

Sell in store and online from one catalog. The [WooCommerce and Square](https://www.beehexa.com/hexasync/profile/woocommerce/square/) profile keeps inventory accurate across both channels, so you don't oversell.

= WooCommerce HubSpot and Salesforce integration =

Send WooCommerce customers and orders to [HubSpot](https://www.beehexa.com/hexasync/profile/woocommerce/hubspot/) or [Salesforce](https://www.beehexa.com/hexasync/profile/woocommerce/salesforce/) so sales and marketing see every purchase.

See all [WooCommerce integration profiles](https://www.beehexa.com/hexasync/profile/woocommerce/) and the full list of [HexaSync connectors](https://www.beehexa.com/hexasync/connectors/).

= How HexaSync works =

* **Middleware** – HexaSync sits between your systems and moves data between them, so neither side needs custom code.
* **Cell-based mapping** – map any data point from one system to another.
* **Customizable tasks** – adapt each sync task to your business rules.
* **Monitoring** – know exactly when a sync transaction succeeded or failed, and why.

= Benefits for WooCommerce stores =

* Keep inventory accurate across every sales channel
* Automate order fulfillment and accounting entries
* Cut the time spent on manual catalog and order data entry
* Improve data quality and consistency between systems
* Catch and fix sync errors before customers notice them

= Getting started =

A HexaSync account is required. Sign in at [app.hexasync.com](https://app.hexasync.com/), see [pricing](https://www.beehexa.com/help/pricing/), read the [documentation](https://www.beehexa.com/docs/), or [contact the Beehexa team](https://www.beehexa.com/contact-us/) for help with your integration.

= External services =

This plugin works with the HexaSync platform, a service provided by Beehexa.

* Your HexaSync profiles send sync log records (profile name, task name, status, messages, errors and reference info such as order numbers) to your site through the plugin's REST endpoint. This happens only after you connect a HexaSync profile to your store.
* The "View on HexaSync" links on the Logs page open the matching job at `https://app.hexasync.com` in your browser. The plugin itself does not send your site data to HexaSync.

Service terms: [Beehexa Terms of Service](https://www.beehexa.com/legal/beehexa-term-of-service/). Privacy: [Beehexa Privacy Policy](https://www.beehexa.com/legal/privacy-policy/).

== Installation ==

1. Install the plugin from **Plugins → Add New** or upload it to `wp-content/plugins`, then activate it.
2. Open **HexaSync** in the WordPress admin menu and follow the connection guide.
3. In **WooCommerce → Settings → Advanced → REST API**, create an API key with Read/Write permission.
4. In your HexaSync workspace, create a profile, choose WooCommerce, and enter your store URL, consumer key and consumer secret.
5. Optional: open **HexaSync → Settings** to turn on email alerts for new sync logs and choose how long logs are kept.

Once a profile is running, its sync history appears under **HexaSync → Logs**.

== Frequently Asked Questions ==

= Do I need a HexaSync account? =

Yes. The plugin shows and stores the sync logs that your HexaSync profiles send to your site. The synchronization itself runs on the HexaSync platform.

= Which systems can I connect to WooCommerce? =

ERP, accounting, POS, CRM and marketplace software, including QuickBooks, Xero, Microsoft Dynamics 365 Business Central, NetSuite, SAP Business One, Odoo, Square, HubSpot and Salesforce. See the [WooCommerce integration profiles](https://www.beehexa.com/hexasync/profile/woocommerce/) for the current list.

= Can HexaSync sync WooCommerce inventory in real time? =

HexaSync runs sync tasks on the schedule you set in your profile, so stock levels stay aligned between WooCommerce and your ERP or POS. For near real-time updates, add WooCommerce webhooks that point to your HexaSync flow, as described in step 4 of the connection guide. Check the [documentation](https://www.beehexa.com/docs/) for the options available to each connector.

= Does it require WooCommerce? =

WooCommerce is needed to sync products, orders and customers, and the connection guide is written for WooCommerce stores. The log monitor and notifications work on any WordPress site that HexaSync sends logs to.

= Where are the logs stored? =

Sync logs are stored in a dedicated table in your own WordPress database, not in posts, so the log history does not slow down your content. Logs are kept until you delete them, or for the number of days set under **HexaSync → Settings → Log Retention**. Deleting the plugin from the Plugins screen removes the table and the plugin's settings.

= How does HexaSync authenticate to my site? =

HexaSync writes logs through a REST endpoint that uses HTTP Basic Authentication for a WordPress user with administrator capability. We recommend creating a dedicated user for the integration and using a WordPress Application Password (Users → Profile → Application Passwords) instead of the account password, so access can be revoked at any time. Always use HTTPS.

= Where can I get help? =

Use the support forum for this plugin, or [contact Beehexa](https://www.beehexa.com/contact-us/).

== Screenshots ==

1. HexaSync Logs: sync history with filters by profile name, task name and reference info.
2. Notification settings: enable email alerts and set the recipients for new logs.
3. Sign in to your Beehexa account to manage HexaSync profiles.
4. Connection guide: step-by-step setup to connect WooCommerce to HexaSync.

== Changelog ==

= 1.2.5 =
* Tested with WordPress 7.2 and PHP 8.6.
* Fix a PHP 8.6 deprecation notice: the REST API class no longer declares a constant named `NAMESPACE`.
* The database upgrade check now passes its column name as a query parameter, as Plugin Check requires.

= 1.2.4 =
* Fix: Application Passwords now work for the plugin's REST API. They were rejected as incorrect, which made the recommended setup fail.
* Fix: wrong credentials return `401` with a plain-text message instead of `500`.
* Fix: account passwords containing characters such as `<` or `%` were altered before being checked.

= 1.2.3 =
* Add log retention: delete logs older than a set number of days, once a day (off by default).
* Database changes and scheduled tasks are now applied after an automatic plugin update, not only on activation.
* The plugin's Basic Authentication handler now applies only to its own `beehexa/v1` REST routes.
* The Notifications page is now **HexaSync → Settings**.
* Deleting the plugin removes its log table, settings and scheduled tasks.
* Fix a fatal error when the notification settings were saved from code rather than the settings form.

= 1.2.2 =
* Add Vietnamese translation; all admin pages, notices and notification emails are now translatable.
* Logs page shows "0 to 0" instead of "1 to 0" when there are no logs.
* Plugin name updated to match the WordPress.org listing.

= 1.2.1 =
* Add email notifications for new sync logs, with a Notifications settings page.
* Add filter by reference info on the HexaSync Logs page.
* Add a WooCommerce connection guide as the main HexaSync admin page.
* "View on HexaSync" links now open app.hexasync.com.

= 1.1.0 =
* Initial release of the HexaSync plugin for WordPress.
* Basic logging to monitor synchronization activity and troubleshoot issues.
