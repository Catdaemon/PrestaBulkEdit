# PrestaBulkEdit
A bulk product editor for PrestaShop. Written because some people charge silly money for such a thing. It's a quick and dirty single-file solution to the problem of bulk adding prices or editing other product data.

## Problems/Limitations
- May misbehave with multi-store enabled (not tested)
- Image editing supports only one per product, and will remove others when set
- Editing a product's category will remove it from all categories but the one specified
- Takes a while to load as it fetches all products. Could be optimised but it's Fast Enough for my purposes
- Does not integrate with the PrestaShop back-office interface, and does not authenticate users, so place with care
    + If you *must* use it in a production environment, please password protect the directory at a minimum

## Dependancies
- SimpleSite/class.db.php (from my SimpleSite repository).
    + Alternatively, replace $db with an initialised PDO of your choice
- jQuery (configurable path)
- jQuery DataTables (configurable paths)

## Usage
Once configured, usage should be quite straight-forward. You can search for products or browse through pages. Click their fields to edit them inline. This includes images.

## License
You are free to use this code for any purposes, including commercial, but you are not allowed to charge for it or any derivatives.