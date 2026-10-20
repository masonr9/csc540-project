CREATE OR REPLACE TABLE Roles (
        role_id int NOT NULL AUTO_INCREMENT,
        role_type varchar(255) NOT NULL,
        PRIMARY KEY(role_id)
);
CREATE OR REPLACE TABLE Contacts (
        contact_id int NOT NULL AUTO_INCREMENT,
        last_name varchar(255) NOT NULL,
        first_name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(255) NOT NULL,
        street_1 varchar(255),
        street_2 varchar(255),
        city varchar(255),
        state_code varchar(255),
        post_code int(5),
        updated timestamp,
        PRIMARY KEY(contact_id)
);
CREATE OR REPLACE TABLE Users (
        user_id int NOT NULL AUTO_INCREMENT,
        role_id int NOT NULL,
        contact_id int NOT NULL,
	    creation_date timestamp,
	    PRIMARY KEY(user_id),
        FOREIGN KEY(role_id) REFERENCES Roles(role_id),
        FOREIGN KEY(contact_id) REFERENCES Contacts(contact_id)
);
CREATE OR REPLACE TABLE Credentials(
        username varchar(255) NOT NULL,
	    user_id int NOT NULL,
        password_salted varchar(255) NOT NULL,
	    PRIMARY KEY(username),
        FOREIGN KEY(user_id) REFERENCES Users(user_id)
);
CREATE TABLE categories (
    category_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    category_name varchar(30)
);
CREATE TABLE merchant (
    merchant_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    merchant_name varchar(50)
);
CREATE TABLE payment_method (
    payment_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    method_name varchar(50) NOT NULL
);
CREATE TABLE currencies (
    currency_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    currency varchar(30)
);
CREATE TABLE expenses (
    expense_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    payment_id INT NOT NULL,
    merchant_id INT NOT NULL,
    currency_id INT NOT NULL,
    amount decimal(10,2),
    expense_date date,

    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (payment_id) REFERENCES payment_method(payment_id),
    FOREIGN KEY (merchant_id) REFERENCES merchant(merchant_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id)
);
CREATE TABLE budget (
    budget_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    limit_amount decimal(10,2),
    month varchar(255),
    user_id INT NOT NULL,
    category_id INT NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);
CREATE TABLE recurring_expense (
    recurring_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    payment_id INT NOT NULL,
    merchant_id INT NOT NULL,
    currency_id INT NOT NULL,
    frequency varchar(50) NOT NULL,
    amount decimal(10,2) NOT NULL,
    start_date date NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (payment_id) REFERENCES payment_method(payment_id),
    FOREIGN KEY (merchant_id) REFERENCES merchant(merchant_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id)
);
CREATE TABLE budget_recommendations (
    recommendation_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    category_id INT NOT NULL,
    amount decimal(10,2),

    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);
CREATE TABLE savings_goals (
    savings_id int AUTO_INCREMENT PRIMARY KEY NOT NULL,
    user_id INT NOT NULL,
    due_date date,
    amount decimal(10,2),
    
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);