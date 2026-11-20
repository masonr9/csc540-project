1. Start Apache and MySQL in XAMPP.
2. Open `C:\xampp\htdocs\csc540-project` in VS Code and select `luka`.
3. For an existing login database, run `C:\xampp\php\php.exe scripts/setup_expenses.php` from this project folder. This creates missing tracker tables and seeds lookup choices without replacing login tables or adding sample expenses. It can be run again safely.
4. Open http://localhost/csc540-project/ and sign in as a standard user. The starter user is `janesmith` / `SCSU2025` if those credentials have not been changed.
5. Choose Expenses or Budgets from the navigation.

Do not rerun `php/start.php` on a database with data to keep: that original script drops all tables. The new setup script expects the existing login tables and database credentials in `php/config.php`.


`user/expenses.php` handles the expense form, ownership checks, prepared statements, POST-only mutations with session CSRF tokens, list filters and monthly category totals. It uses the existing categories, merchant, payment_method, currencies and expenses schema. User identity comes from the logged-in username, never a form field. Only standard users may access the page.

Each expense has a category, merchant, payment method, currency, positive amount with at most two decimals, and a date. The history defaults to the current month; use the month selector to find older entries. Search matches merchant/category names. Monthly totals ignore the list's category/search filters and group by currency; no exchange-rate conversion is performed. Successful changes redirect to the current-month list.

The Budgets link opens Faozia's existing page. Expense and budget records share user/category IDs. Budget-versus-spending comparisons are deferred because the existing budget schema has no currency field. Add an agreed budget currency before calculating remaining balances across these records.

## Verification

Local HTTP checks cover add/edit/delete, positive amount and calendar-date validation, invalid lookup IDs, CSRF rejection, monthly/category/search filtering, category totals, guest access redirect, admin role denial, and cross-user edit/delete denial. Temporary test expenses are removed. Existing budget page also loads for the standard user.

## Next team steps

Review these changes in VS Code Source Control, commit them on `luka`, and push that branch when ready. No push is performed by this setup. Category, merchant, payment-method and currency administration remain separate team features; expenses use their existing lookup tables.
