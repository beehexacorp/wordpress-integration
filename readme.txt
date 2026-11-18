=== HexaSync SaaS Integration Platform by Beehexa ===
Contributors: beehexa
Tags: woocommerce, integration, erp, sync, automation
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce to ERP, accounting, POS and CRM with HexaSync, and monitor every sync log right inside WordPress.

== Description ==

**HexaSync** is a SaaS integration platform by [Beehexa](https://www.beehexa.com/about/) that connects WooCommerce to ERP, accounting, POS, CRM and marketplace software, so products, inventory, orders, customers and shipments stay in sync without manual work.

This plugin is the WordPress side of that connection. It gives store owners a place inside wp-admin to set up the WooCommerce connection and to see exactly what HexaSync synchronized, what failed, and why.

= What the plugin adds to your WordPress site =

* **Connection guide** – a step-by-step page that walks you through creating WooCommerce REST API keys and connecting your store to a HexaSync profile.
* **Sync log monitor** – every sync transaction HexaSync reports is listed under **HexaSync → Logs** with profile, task, status, message, error detail and retry count.
* **Filters** – narrow the log list by profile name, task name or reference info (for example an order number or SKU).
* **Email notifications** – get an email when new sync logs arrive, sent to the recipients you choose.
* **One-click drill down** – each log links to the matching job in your HexaSync workspace for the full sync history.
* **REST endpoint for HexaSync** – a secured endpoint that lets your HexaSync profiles write logs to your site.

= What you can integrate with WooCommerce =

HexaSync ships prebuilt [WooCommerce integration profiles](https://www.beehexa.com/hexasync/profile/woocommerce/) for the systems stores use most, including:

* Accounting – [WooCommerce and QuickBooks](https://www.beehexa.com/hexasync/profile/woocommerce/quickbooks/), [WooCommerce and Xero](https://www.beehexa.com/hexasync/profile/woocommerce/xero/)
* ERP – [Microsoft Dynamics 365 Business Central](https://www.beehexa.com/hexasync/profile/microsoft-dynamics-365-business-central/woocommerce/), [NetSuite](https://www.beehexa.com/hexasync/profile/netsuite/woocommerce/), [SAP Business One](https://www.beehexa.com/hexasync/profile/sap-business-one/woocommerce/), [Odoo](https://www.beehexa.com/hexasync/profile/odoo/woocommerce/)
* POS – [WooCommerce and Square](https://www.beehexa.com/hexasync/profile/woocommerce/square/)
* CRM – [WooCommerce and HubSpot](https://www.beehexa.com/hexasync/profile/woocommerce/hubspot/), [WooCommerce and Salesforce](https://www.beehexa.com/hexasync/profile/woocommerce/salesforce/)

See the full list of [HexaSync connectors](https://www.beehexa.com/hexasync/connectors/).

= How HexaSync works =

* **Middleware** – HexaSync sits between your systems and transfers data between them, so neither side needs custom code.
* **Cell-based mapping** – map any data point from one system to another.
* **Customizable tasks** – adapt each sync task to your business rules.
* **Monitoring** – know exactly when a sync transaction is SUCCESSFUL or FAILED, and why.

= Benefits for WooCommerce stores =

* Normalize data management processes
* Improve data quality and consistency
* Reduce 90% of catalog data management time
* Automate order fulfillment
* Automate accounting processes
* Improve customer satisfaction
* Keep inventory accurate across every channel

= Getting started =

A HexaSync account is required. Sign in at [app.hexasync.com](https://app.hexasync.com/), see [pricing](https://www.beehexa.com/help/pricing/), read the [documentation](https://www.beehexa.com/docs/), or [contact the Beehexa team](https://www.beehexa.com/contact-us/) for help with your integration.

== Installation ==

1. Install the plugin from **Plugins → Add New** or upload it to `wp-content/plugins`, then activate it.
2. Open **HexaSync** in the WordPress admin menu and follow the connection guide.
3. In **WooCommerce → Settings → Advanced → REST API**, create an API key with Read/Write permission.
4. In your HexaSync workspace, create a profile, choose WooCommerce, and enter your store URL, consumer key and consumer secret.
5. Optional: open **HexaSync → Notifications** to turn on email alerts for new sync logs.

Once a profile is running, its sync history appears under **HexaSync → Logs**.

== Frequently Asked Questions ==

= Do I need a HexaSync account? =

Yes. The plugin shows and stores the sync logs that your HexaSync profiles send to your site. The synchronization itself runs on the HexaSync platform.

= Does it require WooCommerce? =

The connection guide is written for WooCommerce stores, and WooCommerce is needed to sync products, orders and customers. The log monitor and notifications work on any WordPress site that HexaSync sends logs to.

= Where are the logs stored? =

In your own WordPress database, in a dedicated table. Nothing is stored in posts or options, so the log history does not slow down your content.

= How does HexaSync authenticate to my site? =

HexaSync writes logs through a REST endpoint that accepts HTTP Basic Authentication for a WordPress user with administrator capability. Use HTTPS, and create a dedicated user for the integration.

= Which systems can I connect to WooCommerce? =

ERP, accounting, POS, CRM and marketplace software. See the [WooCommerce integration profiles](https://www.beehexa.com/hexasync/profile/woocommerce/) for the current list.

= Where can I get help? =

Use the support forum for this plugin, or [contact Beehexa](https://www.beehexa.com/contact-us/).

== External services ==

This plugin works with the HexaSync platform, a service provided by Beehexa.

* Your HexaSync profiles send sync log records (profile name, task name, status, messages, errors and reference info such as order numbers) to your site through the plugin's REST endpoint. This happens only after you connect a HexaSync profile to your store.
* The "View on HexaSync" links on the Logs page open the matching job at `https://app.hexasync.com` in your browser. The plugin itself does not send your site data to HexaSync.

Service terms: [Beehexa Terms of Service](https://www.beehexa.com/legal/beehexa-term-of-service/). Privacy: [Beehexa Privacy Policy](https://www.beehexa.com/legal/privacy-policy/).

== Screenshots ==

1. HexaSync Logs: sync history with filters by profile name, task name and reference info.
2. Notification settings: enable email alerts and set the recipients for new logs.
3. Sign in to your Beehexa account to manage HexaSync profiles.
4. Connection guide: step-by-step setup to connect WooCommerce to HexaSync.

== Changelog ==

= 1.2.1 =
* Add email notifications for new sync logs, with a Notifications settings page.
* Add filter by reference info on the HexaSync Logs page.
* Add a WooCommerce connection guide as the main HexaSync admin page.
* "View on HexaSync" links now open app.hexasync.com.

= 1.1.0 =
* Initial release of the HexaSync plugin for WordPress.
* Basic logging to monitor synchronization activity and troubleshoot issues.
