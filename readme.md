# WordPress Post and User Manager

**Contributors:** Darren Kandekore
**Tags:** posts, users, export, delete, csv, bulk, admin, utility
**Requires at least:** 5.0
**Tested up to:** 6.5
**Stable tag:** 1.5.0
**License:** GPLv2 or later
**License URI:** [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

A powerful and intuitive utility plugin for WordPress administrators. Easily export posts and users to a CSV file or perform bulk deletions based on date ranges, specific dates, or user roles.

## Description

The **WordPress Post and User Manager** provides a simple interface within the WordPress dashboard to handle common but cumbersome bulk operations. Whether you are migrating content, cleaning up an old site, or just need a backup of your data, this plugin streamlines the process.

### Key Features:

* **Export Posts to CSV:** Export posts of any public post type, including custom post types.
* **Export Users to CSV:** Export users based on their role.
* **Flexible Date Filtering:** Filter exports and deletions by a specific date (before/after) or a date range.
* **Bulk Delete Posts:** Securely delete posts in bulk based on post type and date filters.
* **Bulk Delete Users:** Securely delete users in bulk based on their role and registration date.
* **Safety First:** All bulk actions feature a confirmation dialog to prevent accidental clicks.
* **High-Performance:** Built to handle large operations on sites with thousands of posts or users by increasing PHP time and memory limits during operations.

This plugin is an essential tool for any site administrator looking to efficiently manage their content and user base.

## Installation

1.  Download the plugin `.zip` file.
2.  Navigate to your WordPress admin dashboard and go to **Plugins > Add New**.
3.  Click the **Upload Plugin** button at the top of the page.
4.  Choose the `.zip` file you downloaded and click **Install Now**.
5.  Once installed, click **Activate Plugin**.
6.  You will find the plugin's menu, labeled **WP Manager**, in the main admin sidebar.

## How to Use

After installation, you will see a new **WP Manager** menu item in your WordPress dashboard. This menu contains two sub-menus: "Manage Posts" and "Manage Users."

### Manage Posts

Navigate to **WP Manager > Manage Posts** to access the post management tools.

#### To Export Posts:

1.  In the "Export Posts" section, **select the Post Type** you wish to export from the dropdown menu. This includes all public post types like Posts, Pages, and any Custom Post Types you have.
2.  Choose your **date filter**:
    * **All Dates:** Exports all posts of the selected type.
    * **Before/After Specific Date:** Select this, then choose a date and specify whether to export posts published *before* or *after* that date.
    * **Between Date Range:** Select this and specify a start and end date for the export.
3.  Click the **Export Posts to CSV** button.
4.  A confirmation dialog will appear. Click **OK** to proceed.
5.  Your browser will download a `.csv` file containing the exported posts.

#### To Delete Posts:

1.  In the "Delete Posts" section, **select the Post Type** you wish to delete.
2.  Choose your **date filter**:
    * Select a date range or a specific before/after date to target specific posts.
    * To delete **ALL** posts of the selected type, check the **"Delete ALL items, regardless of date"** checkbox.
3.  Click the **Delete Posts** button.
4.  A **warning confirmation dialog** will appear. This is your final chance to cancel the operation.
5.  Click **OK** to permanently delete the posts.

---

### Manage Users

Navigate to **WP Manager > Manage Users** to access the user management tools.

#### To Export Users:

1.  In the "Export Users" section, **select the User Role** you wish to export from the dropdown menu (e.g., Subscriber, Author).
2.  Choose your **date filter** based on the user's registration date.
3.  Click the **Export Users to CSV** button.
4.  A confirmation dialog will appear. Click **OK** to proceed.
5.  Your browser will download a `.csv` file containing the exported users.

#### To Delete Users:

1.  In the "Delete Users" section, **select the User Role** you wish to delete.
2.  Choose the **date filter** based on when the users registered.
    * To delete **ALL** users in the selected role, check the **"Delete ALL items, regardless of date"** checkbox.
3.  Click the **Delete Users** button.
4.  A **warning confirmation dialog** will appear. Note: The currently logged-in administrator cannot be deleted.
5.  Click **OK** to permanently delete the users and reassign their content.

---