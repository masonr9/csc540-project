
--Roles
INSERT INTO Roles (role_id, role_type) VALUES
(1, 'Admin'),
(2, 'User');

--Contacts
INSERT INTO Contacts
(contact_id, last_name, first_name, email, phone, city, state_code, post_code)
VALUES
(1, 'Smith', 'John', 'john@example.com', '2035550101', 'New Haven', 'CT', 06511),
(2, 'Johnson', 'Emily', 'emily@example.com', '2035550102', 'Hartford', 'CT', 06103),
(3, 'Brown', 'Michael', 'michael@example.com', '2035550103', 'Houston', 'TX', 77001);

--Users
INSERT INTO Users (user_id, role_id, contact_id) VALUES
(1, 2, 1),
(2, 2, 2),
(3, 1, 3);

--Credentials
-- RWe will have to replace placeholders with properly generated password hashes
-- before testing authentication.
INSERT INTO Credentials (username, user_id, password_salted)
VALUES
('johnsmith', 1, 'TEST_HASH_PLACEHOLDER_1'),
('emilyjohnson', 2, 'TEST_HASH_PLACEHOLDER_2'),
('michaelbrown', 3, 'TEST_HASH_PLACEHOLDER_3');

--Categories
INSERT INTO categories (category_id, category_name) VALUES
(1, 'Food'),
(2, 'Transportation'),
(3, 'Shopping'),
(4, 'Entertainment'),
(5, 'Bills'),
(6, 'Healthcare');

--Merchants
INSERT INTO merchant (merchant_id, merchant_name) VALUES
(1, 'Walmart'),
(2, 'Uber'),
(3, 'Amazon'),
(4, 'Netflix'),
(5, 'Target'),
(6, 'CVS');

--Payment Methods
INSERT INTO payment_method (payment_id, method_name) VALUES
(1, 'Credit Card'),
(2, 'Debit Card'),
(3, 'Cash'),
(4, 'Bank Transfer');

--Currencies
INSERT INTO currencies (currency_id, currency) VALUES
(1, 'USD'),
(2, 'CAD'),
(3, 'EUR');

--Expenses
INSERT INTO expenses
(user_id, category_id, payment_id, merchant_id,
 currency_id, amount, expense_date)
VALUES
(1, 1, 2, 1, 1, 45.50, '2026-10-01'),
(1, 2, 1, 2, 1, 18.00, '2026-10-03'),
(1, 3, 1, 3, 1, 75.99, '2026-10-05'),
(1, 4, 2, 4, 1, 15.99, '2026-10-07'),
(2, 1, 2, 5, 1, 32.75, '2026-10-02'),
(2, 6, 1, 6, 1, 22.50, '2026-10-06'),
(3, 2, 2, 2, 1, 25.00, '2026-10-08'),
(3, 3, 1, 3, 1, 120.00, '2026-10-10');

--Budget
INSERT INTO budget
(limit_amount, month, user_id, category_id)
VALUES
(300.00, '2026-10', 1, 1),
(150.00, '2026-10', 1, 2),
(200.00, '2026-10', 1, 3),
(250.00, '2026-10', 2, 1),
(100.00, '2026-10', 2, 6),
(200.00, '2026-10', 3, 2);

--Recurring Expenses
INSERT INTO recurring_expense
(user_id, category_id, payment_id, merchant_id,
 currency_id, frequency, amount, start_date)
VALUES
(1, 4, 1, 4, 1, 'Monthly', 15.99, '2026-10-01'),
(2, 4, 2, 4, 1, 'Monthly', 15.99, '2026-10-01'),
(3, 2, 1, 2, 1, 'Weekly', 25.00, '2026-10-01');

--Budget Recommendations
INSERT INTO budget_recommendations
(category_id, amount)
VALUES
(1, 300.00),
(2, 150.00),
(3, 200.00),
(4, 100.00),
(5, 500.00),
(6, 100.00);

--Savings Goals
INSERT INTO savings_goals
(user_id, due_date, amount)
VALUES
(1, '2027-06-01', 2000.00),
(2, '2027-03-01', 1500.00),
(3, '2027-12-01', 5000.00);
