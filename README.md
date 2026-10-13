# Product Extra Tabs (sbproducttabs)

PrestaShop module by **Sotiris Betsos**: adds extra tabs (title + HTML content) to the product page.

## Compatibility

- PrestaShop **9.x** (tested on 9.2.0), as well as 8.x and 1.7.7+
- PHP 7.2 – 8.4

## Features

- Multilingual title and content (WYSIWYG editor)
- Internal name (back office only) to tell apart tabs that share the same title
- Show a tab on:
  - all products
  - selected products
  - products of selected categories
  - products of selected brands
- Search for products / categories / brands by name, reference or ID
- Ordering via drag & drop in the list
- Enable / disable per tab
- Multistore support
- Automatically removes a deleted product from the tabs

## Installation

1. Download `sbproducttabs.zip` (or zip the `sbproducttabs/` folder yourself).
2. Back office → **Modules → Module Manager → Upload a module** → choose the zip.
3. The tabs are managed from **Catalog → Product Extra Tabs**
   (or from the module's "Configure" button).

Upgrading from an earlier version: upload the new zip over the old one; the
module upgrades automatically and keeps your existing tabs.

The tabs are displayed through the `displayProductExtraContent` hook, which is
supported by the Classic and Hummingbird themes as well as most third-party themes.

## Structure

```
sbproducttabs/
├── sbproducttabs.php                         # main module class
├── classes/SbProductTab.php                  # ObjectModel + display logic
├── controllers/admin/AdminSbProductTabsController.php
├── sql/install.php, sql/uninstall.php
└── views/
    ├── css/admin.css
    ├── js/admin.js
    └── templates/admin/sb_product_tabs/helpers/form/form.tpl
```
