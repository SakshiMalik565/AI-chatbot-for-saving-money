<?php
$host = 'localhost';
$username = 'root';
$password = '';

// Create connection without database
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS ai_saver";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db("ai_saver");

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'user') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create categories table
$sql = "CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Categories table created successfully<br>";
} else {
    echo "Error creating categories table: " . $conn->error . "<br>";
}

// Create transactions table
$sql = "CREATE TABLE IF NOT EXISTS transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    category_id INT,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Transactions table created successfully<br>";
} else {
    echo "Error creating transactions table: " . $conn->error . "<br>";
}

// Create budgets table
$sql = "CREATE TABLE IF NOT EXISTS budgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    category_id INT,
    amount DECIMAL(10,2) NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Budgets table created successfully<br>";
} else {
    echo "Error creating budgets table: " . $conn->error . "<br>";
}

// Create ai_recommendations table
$sql = "CREATE TABLE IF NOT EXISTS ai_recommendations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    recommendation TEXT NOT NULL,
    category VARCHAR(50),
    priority ENUM('low', 'medium', 'high') NOT NULL,
    status ENUM('pending', 'implemented', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "AI recommendations table created successfully<br>";
} else {
    echo "Error creating AI recommendations table: " . $conn->error . "<br>";
}

// Insert default categories
$categories = [
    ['Salary', 'income', 'Monthly salary income'],
    ['Freelance', 'income', 'Freelance work income'],
    ['Investments', 'income', 'Investment returns'],
    ['Food & Dining', 'expense', 'Groceries and dining out'],
    ['Transportation', 'expense', 'Commuting and travel expenses'],
    ['Housing', 'expense', 'Rent or mortgage payments'],
    ['Utilities', 'expense', 'Electricity, water, internet bills'],
    ['Entertainment', 'expense', 'Movies, games, and leisure activities'],
    ['Healthcare', 'expense', 'Medical expenses and insurance'],
    ['Shopping', 'expense', 'General shopping expenses']
];

$stmt = $conn->prepare("INSERT IGNORE INTO categories (name, type, description) VALUES (?, ?, ?)");
foreach ($categories as $category) {
    $stmt->bind_param("sss", $category[0], $category[1], $category[2]);
    $stmt->execute();
}
echo "Default categories inserted successfully<br>";

// Insert default admin user
$admin_username = 'admin';
$admin_email = 'admin@example.com';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$admin_role = 'admin';

$stmt = $conn->prepare("INSERT IGNORE INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $admin_username, $admin_email, $admin_password, $admin_role);
if ($stmt->execute()) {
    echo "Default admin user created successfully<br>";
} else {
    echo "Error creating admin user: " . $stmt->error . "<br>";
}

$conn->close();
echo "Database setup completed!";
?> 