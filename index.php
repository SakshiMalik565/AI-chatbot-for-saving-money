<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user's financial summary
$user_id = $_SESSION['user_id'];
$current_month = date('m');
$current_year = date('Y');

// Get total income for current month
$income_query = "SELECT SUM(amount) as total_income FROM transactions 
                 WHERE user_id = ? AND type = 'income' 
                 AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
$stmt = $conn->prepare($income_query);
$stmt->bind_param("iii", $user_id, $current_month, $current_year);
$stmt->execute();
$income_result = $stmt->get_result();
$total_income = $income_result->fetch_assoc()['total_income'] ?? 0;

// Get total expenses for current month
$expense_query = "SELECT SUM(amount) as total_expense FROM transactions 
                  WHERE user_id = ? AND type = 'expense' 
                  AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
$stmt = $conn->prepare($expense_query);
$stmt->bind_param("iii", $user_id, $current_month, $current_year);
$stmt->execute();
$expense_result = $stmt->get_result();
$total_expense = $expense_result->fetch_assoc()['total_expense'] ?? 0;

// Get savings rate
$savings = $total_income - $total_expense;
$savings_rate = $total_income > 0 ? ($savings / $total_income) * 100 : 0;

// Get recent transactions
$transactions_query = "SELECT t.*, c.name as category_name 
                      FROM transactions t 
                      LEFT JOIN categories c ON t.category_id = c.id 
                      WHERE t.user_id = ? 
                      ORDER BY t.transaction_date DESC 
                      LIMIT 5";
$stmt = $conn->prepare($transactions_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get AI recommendations
$recommendations_query = "SELECT * FROM ai_recommendations 
                         WHERE user_id = ? AND status = 'pending' 
                         ORDER BY priority DESC, created_at DESC 
                         LIMIT 3";
$stmt = $conn->prepare($recommendations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Savings Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .stat-icon {
            font-size: 2rem;
            color: #00b894;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #00b894;
        }
        .savings-rate {
            font-size: 1.5rem;
            font-weight: bold;
            color: #00b894;
        }
        .transaction-card {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .income {
            color: #00b894;
        }
        .expense {
            color: #e74c3c;
        }
        .recommendation-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .priority-high {
            border-left: 4px solid #e74c3c;
        }
        .priority-medium {
            border-left: 4px solid #f39c12;
        }
        .priority-low {
            border-left: 4px solid #00b894;
        }
        
        /* Chatbot Styles */
        #chat-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        #chat-button i {
            font-size: 24px;
        }

        #chat-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            z-index: 999;
        }

        #chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
        }

        .chat-message {
            margin-bottom: 10px;
            max-width: 80%;
        }

        .user-message {
            margin-left: auto;
            background: #00b894;
            color: white;
            border-radius: 15px 15px 0 15px;
            padding: 10px 15px;
        }

        .bot-message {
            margin-right: auto;
            background: #f1f1f1;
            color: #2d3436;
            border-radius: 15px 15px 15px 0;
            padding: 10px 15px;
        }

        .chat-input-container {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        #chat-input {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 15px;
            outline: none;
        }

        #send-button {
            background: #00b894;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-robot"></i> AI Savings Assistant
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="budgets.php">
                            <i class="fas fa-wallet"></i> Budgets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-money-bill-wave stat-icon"></i>
                    <h3>Total Income</h3>
                    <div class="stat-number income">₹<?php echo number_format($total_income, 2); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-shopping-cart stat-icon"></i>
                    <h3>Total Expenses</h3>
                    <div class="stat-number expense">₹<?php echo number_format($total_expense, 2); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-piggy-bank stat-icon"></i>
                    <h3>Savings Rate</h3>
                    <div class="savings-rate"><?php echo number_format($savings_rate, 1); ?>%</div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Transactions</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="transaction-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($transaction['description']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($transaction['category_name']); ?></small>
                                    </div>
                                    <div class="<?php echo $transaction['type'] === 'income' ? 'income' : 'expense'; ?>">
                                        <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?>
                                        ₹<?php echo number_format($transaction['amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <a href="transactions.php" class="btn btn-outline-primary mt-3">View All Transactions</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">AI Recommendations</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recommendations as $recommendation): ?>
                            <div class="recommendation-card priority-<?php echo $recommendation['priority']; ?>">
                                <p class="mb-0"><?php echo htmlspecialchars($recommendation['recommendation']); ?></p>
                                <?php if ($recommendation['category']): ?>
                                    <small class="text-muted">Category: <?php echo htmlspecialchars($recommendation['category']); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($recommendations)): ?>
                            <p class="text-muted">No recommendations at the moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chatbot Button and Container -->
    <button id="chat-button">
        <i class="fas fa-robot"></i>
    </button>

    <div id="chat-container" class="d-none">
        <div id="chat-messages"></div>
        <div class="chat-input-container">
            <input type="text" id="chat-input" placeholder="Type your message...">
            <button id="send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chat.js"></script>
</body>
</html> 